<?php

namespace App\Actions\Profiles;

use App\Models\Coach;
use App\Models\User;

class UpdateCoachProfile
{
    public function handle(User $user, array $data): void
    {
        Coach::query()->updateOrCreate(
            ['id' => $user->id],
            [
                'status' => $data['status'],
                'specialization' => $data['specialization'] ?? null,
                'bio' => $data['bio'] ?? null,
            ],
        );
    }
}
