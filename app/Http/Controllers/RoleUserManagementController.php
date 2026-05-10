<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleUserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $users = User::query()
            ->whereNull('deleted_at')
            ->with(['roleAssignments', 'profile', 'certifications', 'achievements'])
            ->orderBy('name')
            ->get();

        return Inertia::render('RoleUserManagementPage', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?? 'Unnamed user',
                'email' => $user->email,
                'roles' => $user->assignedRoles(),
                'profile_picture_url' => $user->profile?->profile_picture_path ? Storage::url($user->profile->profile_picture_path) : null,
                'bio' => $user->profile?->bio,
                'certifications' => $user->certifications->map(fn (UserCertification $cert) => [
                    'id' => $cert->id,
                    'cert_type' => $cert->cert_type,
                    'title' => $cert->title,
                    'issuer' => $cert->issuer,
                    'certified_at' => $cert->certified_at?->format('Y-m-d'),
                    'expires_at' => $cert->expires_at?->format('Y-m-d'),
                    'notes' => $cert->notes,
                ])->values(),
                'achievements' => $user->achievements->map(fn (UserAchievement $ach) => [
                    'id' => $ach->id,
                    'championship_name' => $ach->championship_name,
                    'medal' => $ach->medal,
                    'location' => $ach->location,
                    'event_date' => $ach->event_date?->format('Y-m-d'),
                    'class_name' => $ach->class_name,
                    'division' => $ach->division,
                    'category' => $ach->category,
                    'is_auto_recorded' => $ach->is_auto_recorded,
                    'notes' => $ach->notes,
                ])->values(),
            ])->values(),
        ]);
    }

    public function upsertProfile(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'bio' => ['nullable', 'string'],
            'profile_picture' => ['nullable', 'image', 'max:4096'],
        ]);

        $payload = ['bio' => $validated['bio'] ?? null];

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('profiles', 'public');
            $payload['profile_picture_path'] = $path;
        }

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            $payload,
        );

        return redirect()->route('role-users.index');
    }

    public function storeCertification(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $user->certifications()->create($validated);

        return redirect()->route('role-users.index');
    }

    public function storeAchievement(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'championship_name' => ['required', 'string', 'max:120'],
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'location' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $user->achievements()->create($validated + ['is_auto_recorded' => false]);

        return redirect()->route('role-users.index');
    }
}

