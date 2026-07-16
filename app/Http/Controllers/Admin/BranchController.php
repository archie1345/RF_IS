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

        return response()->json([
            'google_maps_url' => $expandedUrl,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'name' => null,
            'address' => null,
            'city' => null,
            'province' => null,
        ]);
    }

    public function lookupOpenStreetMap(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        $query = trim($validated['query']);

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'RhinoFighterManagement/1.0 (location lookup)',
                    'Accept' => 'application/json',
                    'Accept-Language' => 'id,en;q=0.8',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'q' => $query,
                    'countrycodes' => 'id',
                    'addressdetails' => 1,
                    'limit' => 1,
                ]);
        } catch (Throwable) {
            return response()->json(['message' => 'OpenStreetMap lookup failed.'], 422);
        }

        if (! $response->ok()) {
            return response()->json(['message' => 'OpenStreetMap lookup failed.'], 422);
        }

        $result = collect($response->json())->first();
        if (! is_array($result)) {
            return response()->json(['message' => 'No OpenStreetMap result found.'], 404);
        }

        $address = $result['address'] ?? [];
        $displayName = (string) ($result['display_name'] ?? '');
        $name = (string) ($result['name'] ?? '');

        return response()->json([
            'name' => $name !== '' ? $name : str($displayName)->before(',')->toString(),
            'location' => $name !== '' ? $name : null,
            'address' => $displayName !== '' ? $displayName : null,
            'city' => $this->firstAddressValue($address, ['city', 'town', 'village', 'municipality', 'county', 'state_district']),
            'province' => $this->firstAddressValue($address, ['state', 'province', 'region']),
            'latitude' => isset($result['lat']) ? number_format((float) $result['lat'], 7, '.', '') : null,
            'longitude' => isset($result['lon']) ? number_format((float) $result['lon'], 7, '.', '') : null,
        ]);
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
        if (! $this->looksLikeGoogleMapsUrl($url)) {
            return null;
        }

        $current = $url;
        $visited = [];

        for ($attempt = 0; $attempt < 8; $attempt++) {
            if (isset($visited[$current])) {
                break;
            }

            $visited[$current] = true;

            try {
                $response = Http::timeout(8)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 RhinoFighterLocationLookup/1.0',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->withOptions([
                        'allow_redirects' => false,
                        'http_errors' => false,
                    ])
                    ->get($current);
            } catch (Throwable) {
                break;
            }

            $location = $response->header('Location');
            if (! is_string($location) || trim($location) === '') {
                break;
            }

            $next = $this->absoluteRedirectUrl($location, $current);
            if (! $next || $next === $current) {
                break;
            }

            $current = $next;

            if ($this->extractCoordinatesFromGoogleMapsUrl($current)) {
                return $current;
            }
        }

        if ($current !== $url) {
            return $current;
        }

        return $this->expandGoogleMapsUrlWithRedirectHistory($url);
    }

    private function expandGoogleMapsUrlWithRedirectHistory(string $url): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 RhinoFighterLocationLookup/1.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 8,
                        'track_redirects' => true,
                    ],
                    'http_errors' => false,
                ])
                ->get($url);

            $history = $response->header('X-Guzzle-Redirect-History');
            if (! is_string($history) || trim($history) === '') {
                return null;
            }

            $redirects = collect(explode(',', $history))
                ->map(fn (string $item) => trim($item))
                ->filter()
                ->values();

            return $redirects->last() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function looksLikeGoogleMapsUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        $host = strtolower($host);

        return str_contains($host, 'google.')
            || $host === 'maps.app.goo.gl'
            || $host === 'goo.gl';
    }

    private function absoluteRedirectUrl(string $location, string $baseUrl): ?string
    {
        $location = html_entity_decode(trim($location));
        if ($location === '') {
            return null;
        }

        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$location;
        }

        return $scheme.'://'.$host.'/'.ltrim($location, '/');
    }

    private function extractCoordinatesFromGoogleMapsUrl(string $url): ?array
    {
        $decoded = urldecode($url);
        $patterns = [
            '/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/[?&](?:q|query|ll)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
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

    private function firstAddressValue(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($address[$key]) && is_string($address[$key]) && trim($address[$key]) !== '') {
                return $address[$key];
            }
        }

        return null;
    }
}
