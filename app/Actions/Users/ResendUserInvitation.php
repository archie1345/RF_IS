<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Validation\ValidationException;

class ResendUserInvitation
{
    public function __construct(private readonly CreateUserInvitation $createUserInvitation) {}

    public function handle(User $user): UserInvitation
    {
        if (! $user->isInvited()) {
            throw ValidationException::withMessages([
                'account' => 'Only invited accounts can receive invitation emails.',
            ]);
        }

        return $this->createUserInvitation->handle($user);
    }
}
