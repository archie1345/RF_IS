<?php

namespace App\Actions\Users;

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateUserInvitation
{
    public const EXPIRATION_HOURS = 72;

    public function handle(User $user): UserInvitation
    {
        [$invitation, $token] = DB::transaction(function () use ($user): array {
            UserInvitation::query()
                ->where('user_id', $user->id)
                ->whereNull('accepted_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $token = Str::random(96);
            $invitation = UserInvitation::query()->create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(self::EXPIRATION_HOURS),
            ]);

            return [$invitation, $token];
        });

        Mail::to($user->email)->send(new UserInvitationMail($user, route('invitations.show', $token)));

        return $invitation;
    }
}
