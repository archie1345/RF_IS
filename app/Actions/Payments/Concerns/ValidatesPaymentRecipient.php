<?php

namespace App\Actions\Payments\Concerns;

use App\Models\User;
use Illuminate\Validation\ValidationException;

trait ValidatesPaymentRecipient
{
    protected function validatePaymentRecipient(array $validated): void
    {
        if ($validated['bill_kind'] === 'PAYROLL') {
            $isActiveCoach = User::query()
                ->withRole('coach')
                ->whereKey($validated['payee_user_id'] ?? null)
                ->where('account_status', User::ACCOUNT_STATUS_ACTIVE)
                ->exists();

            if (! $isActiveCoach) {
                throw ValidationException::withMessages([
                    'payee_user_id' => 'Choose an active coach account for payroll.',
                ]);
            }

            return;
        }

        if (empty($validated['athlete_id']) && empty($validated['billable_user_id'])) {
            throw ValidationException::withMessages([
                'athlete_id' => 'Choose an athlete or another account for this bill.',
                'billable_user_id' => 'Choose an athlete or another account for this bill.',
            ]);
        }
    }
}
