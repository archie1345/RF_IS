<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Decides which payments a user may see or act on.
 *
 * With no `$mode` the visible set is the UNION of what each held role may see
 * (admin sees everything). A mode narrows it to one role for the stateless
 * `?mode=` view switch on the payments page.
 */
class PaymentVisibilityService
{
    public function visiblePaymentsQuery(Request $request, ?string $mode = null): Builder
    {
        $query = Payment::query();
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $roles = $this->rolesFor($user, $mode);

        if ($roles === []) {
            // Accounts without a recognised role can only ever see bills addressed to them.
            return $this->ownInvoices($query, $user, null);
        }

        if (in_array('admin', $roles, true)) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user, $roles): void {
            foreach ($roles as $role) {
                if ($role === 'athlete') {
                    $scope->orWhere(fn (Builder $own) => $this->ownInvoices($own, $user, $user->athleteProfile?->athlete_id));
                } elseif ($role === 'coach') {
                    $scope->orWhere(fn (Builder $payroll) => $payroll
                        ->where('bill_kind', 'PAYROLL')
                        ->where('payee_user_id', $user->id));
                }
            }
        });
    }

    public function userCanViewPayment(Request $request, Payment $payment): bool
    {
        return $this->visiblePaymentsQuery($request)
            ->whereKey($payment->payment_id)
            ->exists();
    }

    /** Only the athlete (or whoever the invoice names as payer) may upload proof; admin records payments directly. */
    public function userCanSubmitProof(?User $user, Payment $payment): bool
    {
        if (! $user || ! $this->isMemberInvoice($payment) || $user->isAdmin() || ! $user->isAthlete()) {
            return false;
        }

        $directUserIds = collect([
            $payment->billable_user_id,
            $payment->payer_user_id,
            $payment->athlete?->id,
        ])->filter()->map(fn ($id) => (int) $id)->unique();

        if ($directUserIds->contains((int) $user->id)) {
            return true;
        }

        $athleteId = $user->athleteProfile?->athlete_id;

        return $athleteId !== null && (string) $payment->athlete_id === (string) $athleteId;
    }

    /** @return array<int, string> */
    private function rolesFor(User $user, ?string $mode): array
    {
        $mode = strtolower(trim((string) $mode));

        if ($mode === '') {
            return $user->assignedRoles();
        }

        return $user->hasRole($mode) ? [$mode] : [];
    }

    private function ownInvoices(Builder $query, User $user, ?string $athleteId): Builder
    {
        return $query
            ->where('bill_kind', 'INVOICE')
            ->where(function (Builder $inner) use ($user, $athleteId): void {
                $inner->where('billable_user_id', $user->id)
                    ->orWhere('payer_user_id', $user->id)
                    ->when($athleteId, fn (Builder $athleteQuery) => $athleteQuery->orWhere('athlete_id', $athleteId));
            });
    }

    private function isMemberInvoice(Payment $payment): bool
    {
        return strtoupper((string) ($payment->bill_kind ?? 'INVOICE')) === 'INVOICE';
    }
}
