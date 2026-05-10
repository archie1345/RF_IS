<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\UserFile;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'certifications' => $user->certifications()->latest('id')->get()->map(fn ($cert) => [
                'id' => (string) $cert->id,
                'cert_type' => $cert->cert_type,
                'title' => $cert->title,
                'issuer' => $cert->issuer ?? '-',
                'certified_at' => $cert->certified_at?->format('Y-m-d') ?? '-',
                'expires_at' => $cert->expires_at?->format('Y-m-d') ?? '-',
                'notes' => $cert->notes ?? '-',
            ])->values(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function storeCertification(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $user = $request->user();
        $user->certifications()->create(collect($validated)->except('file')->all());

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'CERTIFICATE',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        return to_route('profile.edit');
    }
}
