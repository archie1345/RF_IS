<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class BranchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedBranch($request);
        $branch = Branch::create($this->payload($validated));

        ActivityLogger::log($request, 'admin.branch.created', 'admin', 'Created branch', $branch, ['branch_name' => $branch->branch_name]);

        return back()->with('status', 'Location saved.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedBranch($request);
        $branch->update($this->payload($validated));

        ActivityLogger::log($request, 'admin.branch.updated', 'admin', 'Updated branch', $branch, ['branch_name' => $branch->branch_name]);

        return back()->with('status', 'Location updated.');
    }

    public function lookupGoogleMaps(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'google_maps_url' => ['required', 'string', 'max:2000'],
        ]);

        $url = trim($validated['google_maps_url']);
        $expandedUrl = $this->expandGoogleMapsUrl($url) ?? $url;
        $coordinates = $this->extractCoordinatesFromGoogleMapsUrl($expandedUrl) ?? $this->extractCoordinatesFromGoogleMapsUrl($url);
        $details = [
            'google_maps_url' => $expandedUrl,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'name' => null,
            'address' => null,
            'city' => null,
            'province' => null,
        ];

        if ($coordinates && filled(config('services.google.maps_api_key'))) {
            $details = array_merge($details, $this->reverseGeocode($coordinates['latitude'], $coordinates['longitude']));
        }

        return response()->json($details);
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $hasGroups = $branch->groups()->exists();
        $hasSchedules = WeeklyTrainingSchedule::query()->where('branch_id', $branch->branch_id)->exists();
        $hasSessions = TrainingSession::query()->where('branch_id', $branch->branch_id)->exists();

        if ($hasGroups || $hasSchedules || $hasSessions) {
            $branch->update(['is_active' => false]);

            return back()->with('status', 'Location has linked classes, schedules, or sessions, so it was deactivated instead of deleted.');
        }

        ActivityLogger::log($request, 'admin.branch.deleted', 'admin', 'Deleted branch', $branch, ['branch_name' => $branch->branch_name]);
        $branch->delete();

        return back()->with('status', 'Location deleted.');
    }

    private function validatedBranch(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_maps_url' => ['nullable', 'string', 'max:2000'],
            'attendance_radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);
    }

    private function payload(array $validated): array
    {
        return [
            'branch_name' => $validated['name'],
            'location' => $validated['location'] ?? $validated['address'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'google_maps_url' => $validated['google_maps_url'] ?? null,
            'attendance_radius_meters' => $validated['attendance_radius_meters'],
            'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function expandGoogleMapsUrl(string $url): ?string
    {
        if (! str_contains($url, 'maps.app.goo.gl') && ! str_contains($url, 'goo.gl/maps')) {
            return null;
        }

        try {
            $response = Http::timeout(6)->withOptions(['allow_redirects' => false])->get($url);
            $location = $response->header('Location');

            return is_string($location) && $location !== '' ? $location : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function extractCoordinatesFromGoogleMapsUrl(string $url): ?array
    {
        $decoded = urldecode($url);
        $patterns = [
            '/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/[?&](?:q|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $matches)) {
                return [
                    'latitude' => number_format((float) $matches[1], 7, '.', ''),
                    'longitude' => number_format((float) $matches[2], 7, '.', ''),
                ];
            }
        }

        return null;
    }

    private function reverseGeocode(string $latitude, string $longitude): array
    {
        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $latitude.','.$longitude,
                'key' => config('services.google.maps_api_key'),
            ]);

            if (! $response->ok()) {
                return [];
            }

            $result = collect($response->json('results'))->first();
            if (! is_array($result)) {
                return [];
            }

            $components = collect($result['address_components'] ?? []);

            return [
                'address' => $result['formatted_address'] ?? null,
                'city' => $this->componentName($components, ['locality', 'administrative_area_level_2']),
                'province' => $this->componentName($components, ['administrative_area_level_1']),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private function componentName($components, array $types): ?string
    {
        $component = $components->first(fn ($component) => count(array_intersect($types, $component['types'] ?? [])) > 0);

        return is_array($component) ? ($component['long_name'] ?? null) : null;
    }
}
