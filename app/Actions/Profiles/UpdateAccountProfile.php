<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Models\UserProfile;
use App\Support\Profile\ProfileMedia;
use Illuminate\Http\Request;

class UpdateAccountProfile
{
    public function __construct(private readonly ProfileMedia $profileMedia) {}

    public function handle(User $user, array $data, Request $request): void
    {
        $payload = ['bio' => $data['bio'] ?? null];

        if ($path = $this->profileMedia->storeProfilePictureFromRequest($request, $user)) {
            $payload['profile_picture_path'] = $path;
        }

        UserProfile::query()->updateOrCreate(['user_id' => $user->id], $payload);
    }
}
