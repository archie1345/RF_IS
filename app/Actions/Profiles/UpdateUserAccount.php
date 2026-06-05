<?php

namespace App\Actions\Profiles;

use App\Models\User;

class UpdateUserAccount
{
    public function handle(User $user, array $data): void
    {
        $user->update($data);
    }
}
