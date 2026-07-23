<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Models\UserCertification;
use App\Support\Profile\ProfileMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SaveUserCertification
{
    public function __construct(private readonly ProfileMedia $profileMedia) {}

    public function store(User $user, array $data, Request $request): UserCertification
    {
        $userFile = $this->profileMedia->storeUserFileFromRequest($request, $user, 'CERTIFICATE');
        $payload = collect($data)->except('file')->all();

        if (Schema::hasColumn('user_certifications', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        return $user->certifications()->create($payload);
    }

    public function update(User $user, UserCertification $certification, array $data, Request $request): void
    {
        $payload = collect($data)->except('file')->all();

        if ($request->hasFile('file') && Schema::hasColumn('user_certifications', 'user_file_id')) {
            $payload['user_file_id'] = $this->profileMedia->storeUserFileFromRequest($request, $user, 'CERTIFICATE')?->id;
        }

        $certification->update($payload);
    }
}
