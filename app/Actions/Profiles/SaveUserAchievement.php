<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Models\UserAchievement;
use App\Support\Profile\ProfileMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SaveUserAchievement
{
    public function __construct(private readonly ProfileMedia $profileMedia) {}

    public function store(User $user, array $data, Request $request): UserAchievement
    {
        $userFile = $this->profileMedia->storeUserFileFromRequest($request, $user, 'EVENT_DOCUMENT');
        $payload = collect($data)->except('file')->all() + ['is_auto_recorded' => false];

        if (Schema::hasColumn('user_achievements', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        return $user->achievements()->create($payload);
    }

    public function update(User $user, UserAchievement $achievement, array $data, Request $request): void
    {
        $payload = collect($data)->except('file')->all();

        if ($request->hasFile('file') && Schema::hasColumn('user_achievements', 'user_file_id')) {
            $payload['user_file_id'] = $this->profileMedia->storeUserFileFromRequest($request, $user, 'EVENT_DOCUMENT')?->id;
        }

        $achievement->update($payload);
    }
}
