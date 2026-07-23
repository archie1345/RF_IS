<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentVisibilityService;

class PaymentPolicy
{
    public function __construct(private readonly PaymentVisibilityService $paymentVisibility) {}

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function recordPayment(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function submitProof(User $user, Payment $payment): bool
    {
        return $this->paymentVisibility->userCanSubmitProof($user, $payment);
    }

    public function reviewProof(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function updateStatus(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function exportInvoice(User $user, Payment $payment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (($payment->bill_kind ?? 'INVOICE') === 'PAYROLL') {
            return $payment->payee_user_id !== null && (int) $payment->payee_user_id === (int) $user->id;
        }

        return $this->paymentVisibility->userCanSubmitProof($user, $payment)
            || ($payment->billable_user_id !== null && (int) $payment->billable_user_id === (int) $user->id)
            || ($payment->payer_user_id !== null && (int) $payment->payer_user_id === (int) $user->id)
            || ($user->isAthlete() && (string) $payment->athlete_id === (string) $user->athleteProfile?->athlete_id)
            || ($user->isParent() && $user->children()->where('athletes.athlete_id', $payment->athlete_id)->exists());
    }
}
