<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdminAccountSafetyService
{
    public function ensureUpdateIsSafe(User $actor, User $target, array $nextRoles, string $nextStatus): void
    {
        $keepsAdminRole = in_array('admin', $nextRoles, true);
        $keepsActiveStatus = $nextStatus === User::ACCOUNT_STATUS_ACTIVE;

        if ($actor->is($target) && (! $keepsAdminRole || ! $keepsActiveStatus)) {
            throw ValidationException::withMessages([
                'account' => 'You cannot remove your own admin role or suspend your own account.',
            ]);
        }

        if ($target->hasRole('admin') && (! $keepsAdminRole || ! $keepsActiveStatus)) {
            $this->ensureAnotherActiveAdminExists($target);
        }
    }

    public function ensureDeletionIsSafe(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'account' => 'You cannot delete your own account.',
            ]);
        }

        if ($target->hasRole('admin') && $target->isActiveAccount()) {
            $this->ensureAnotherActiveAdminExists($target);
        }
    }

    private function ensureAnotherActiveAdminExists(User $target): void
    {
        $anotherActiveAdminExists = User::query()
            ->withRole('admin')
            ->whereKeyNot($target->getKey())
            ->where('account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->exists();

        if (! $anotherActiveAdminExists) {
            throw ValidationException::withMessages([
                'account' => 'At least one active administrator must remain in the system.',
            ]);
        }
    }
}
