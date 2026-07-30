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
        if (! app()->bound('request') || (int) request()->user()?->id !== (int) $user->id) {
            return false;
        }

        return $this->paymentVisibility->userCanViewPayment(request(), $payment);
    }
}
