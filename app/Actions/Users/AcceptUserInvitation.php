<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptUserInvitation
{
    public function handle(UserInvitation $invitation, string $password): User
    {
        return DB::transaction(function () use ($invitation, $password): User {
            /** @var UserInvitation $lockedInvitation */
            $lockedInvitation = UserInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());
            $this->ensureAcceptable($lockedInvitation);

            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($lockedInvitation->user_id);

            if (! $user->isInvited()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This account invitation is no longer pending.',
                ]);
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                'email_verified_at' => now(),
            ])->save();

            $lockedInvitation->forceFill(['accepted_at' => now()])->save();

            UserInvitation::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($lockedInvitation->getKey())
                ->whereNull('accepted_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            return $user->refresh();
        });
    }

    public function ensureAcceptable(UserInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isInvalidated()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is no longer active.']);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation has expired.']);
        }
    }
}
