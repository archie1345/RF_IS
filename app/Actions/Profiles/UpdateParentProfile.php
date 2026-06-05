<?php

namespace App\Actions\Profiles;

use App\Models\Parents;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateParentProfile
{
    public function handle(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $user->update(['phone' => $data['phone'] ?? null]);

            Parents::query()->updateOrCreate(
                ['id' => $user->id],
                [
                    'relation' => $data['relation'],
                    'occupation' => $data['occupation'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
            );
        });
    }
}
