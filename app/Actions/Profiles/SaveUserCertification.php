<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Models\UserCertification;
use App\Support\Profile\ProfileMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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

        try {
            return DB::transaction(fn () => $user->certifications()->create($payload));
        } catch (Throwable $exception) {
            $this->profileMedia->deleteUserFile($userFile);

            throw $exception;
        }
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

        try {
            DB::transaction(function () use ($certification, $payload): void {
                $certification->update($payload);
            });
        } catch (Throwable $exception) {
            $this->profileMedia->deleteUserFile($newFile);

            throw $exception;
        }

        if ($newFile && $oldFile) {
            $this->profileMedia->deleteUserFile($oldFile);
        }
    }
}
