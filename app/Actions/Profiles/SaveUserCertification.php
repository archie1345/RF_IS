<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Models\UserCertification;
use App\Support\Profile\ProfileMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $oldFile = $certification->file;
        $newFile = null;

        if ($request->hasFile('file') && Schema::hasColumn('user_certifications', 'user_file_id')) {
            $newFile = $this->profileMedia->storeUserFileFromRequest($request, $user, 'CERTIFICATE');
            $payload['user_file_id'] = $newFile?->id;
        }

        DB::transaction(function () use ($certification, $payload): void {
            $certification->update($payload);
        });

        if ($newFile && $oldFile) {
            $this->profileMedia->deleteUserFile($oldFile);
        }
    }
}
