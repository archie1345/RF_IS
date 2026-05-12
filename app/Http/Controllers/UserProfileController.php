<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserFile;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Laravel\Facades\Image;

class UserProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('UserProfilePage', [
            'user' => [
                'name' => $user->name ?? 'User',
                'email' => $user->email,
                'bio' => $user->profile?->bio,
                'profile_picture_url' => $user->profile?->profile_picture_path ? Storage::url($user->profile->profile_picture_path) : null,
            ],
            'certifications' => $user->certifications->map(fn (UserCertification $cert) => [
                'id' => $cert->id,
                'cert_type' => $cert->cert_type,
                'title' => $cert->title,
                'issuer' => $cert->issuer,
                'certified_at' => $cert->certified_at?->format('Y-m-d'),
                'expires_at' => $cert->expires_at?->format('Y-m-d'),
                'notes' => $cert->notes,
            ])->values(),
            'achievements' => $user->achievements()->with('file')->get()->map(fn (UserAchievement $ach) => [
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
                'file_name' => $ach->file?->original_name,
                'file_url' => $ach->file?->file_path ? Storage::url($ach->file->file_path) : null,
            ])->values(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
{
    $user = $request->user();

    $validated = $request->validate([
        'bio' => ['nullable', 'string'],

        'profile_picture' => [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:2048',
        ],
    ]);

    $payload = [
        'bio' => $validated['bio'] ?? null,
    ];

    if ($request->hasFile('profile_picture')) {

        if ($user->profile?->profile_picture_path) {

            Storage::disk('public')->delete(
                $user->profile->profile_picture_path
            );
        }

        $image = Image::read(
            $request->file('profile_picture')
        )
            ->cover(512, 512)
            ->toJpeg(85);

        $filename = 'profiles/' . uniqid() . '.jpg';

        Storage::disk('public')->put(
            $filename,
            $image->toString()
        );

        $payload['profile_picture_path'] = $filename;
    }

    UserProfile::query()->updateOrCreate(
        [
            'user_id' => $user->id,
        ],
        $payload,
    );

    return redirect()->route('profile.show');
}

    public function storeCertification(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $cert = $user->certifications()->create(collect($validated)->except('file')->all());

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $path = $uploaded->store('user-files', 'public');
            UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'CERTIFICATE',
                'original_name' => $uploaded->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $uploaded->getMimeType(),
                'size_bytes' => $uploaded->getSize(),
            ]);
        }

        return redirect()->route('profile.show');
    }

    public function storeAchievement(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'championship_name' => ['required', 'string', 'max:120'],
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'location' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $userFile = null;

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $path = $uploaded->store('user-files', 'public');
            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'EVENT_DOCUMENT',
                'original_name' => $uploaded->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $uploaded->getMimeType(),
                'size_bytes' => $uploaded->getSize(),
            ]);
        }

        $user->achievements()->create(
            collect($validated)->except('file')->all() + [
                'is_auto_recorded' => false,
                'user_file_id' => $userFile?->id,
            ],
        );

        return redirect()->route('profile.show');
    }
}
