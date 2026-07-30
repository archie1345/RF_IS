<?php

namespace App\Actions\Profiles;

use App\Models\ParentProfile;
use App\Models\User;

class UpdateParentProfile
{
    public function handle(User $user, array $data): void
    {
        ParentProfile::query()->updateOrCreate(
            ['id' => $user->id],
            [
                'relation' => $data['relation'],
                'occupation' => $data['occupation'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
        );
    }
}
