<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PaymentVisibilityService
{
    public function __construct(private readonly ParentChildContextService $childContext)
    {
    }

    public function visiblePaymentsQuery(Request $request): Builder
    {
        $query = Payment::query();
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isParent()) {
            $childIds = $this->childContext->visibleChildAthleteIds($request, false);
            $childUserIds = $this->childContext->visibleChildUserIds($request, false);

            return $this->tuitionOnly($query)
                ->where(function ($inner) use ($user, $childIds, $childUserIds): void {
                    $inner->where('billable_user_id', $user->id)
                        ->orWhere('payee_user_id', $user->id)
                        ->when(count($childIds) > 0, fn ($childQuery) => $childQuery->orWhereIn('athlete_id', $childIds))
                        ->when(count($childUserIds) > 0, fn ($childQuery) => $childQuery->orWhereIn('billable_user_id', $childUserIds));
                });
        }

        if ($user->isAthlete()) {
            $athleteId = $user->athleteProfile?->athlete_id;

            return $this->tuitionOnly($query)
                ->where(function ($inner) use ($user, $athleteId): void {
                    $inner->where('billable_user_id', $user->id)
                        ->when($athleteId, fn ($athleteQuery) => $athleteQuery->orWhere('athlete_id', $athleteId));
                });
        }

        if ($user->isCoach()) {
            return $query->where(function ($inner) use ($user): void {
                $inner->where('billable_user_id', $user->id)
                    ->orWhere('payee_user_id', $user->id);
            });
        }

        return $this->tuitionOnly($query)->where('billable_user_id', $user->id);
    }

    public function userCanSubmitProof(?User $user, Payment $payment): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->isCoach()) {
            return false;
        }

        if (! $this->isTuitionPayment($payment)) {
            return false;
        }

        $directUserIds = collect([
            $payment->billable_user_id,
            $payment->payee_user_id,
            $payment->payer_user_id,
            $payment->athlete?->id,
        ])->filter()->map(fn ($id) => (int) $id)->unique();

        if ($directUserIds->contains((int) $user->id)) {
            return true;
        }

        if ($user->isAthlete()) {
            return (string) $payment->athlete_id === (string) $user->athleteProfile?->athlete_id;
        }

        if ($user->isParent()) {
            $childAthletes = $user->children()
                ->with('user:id')
                ->get(['athletes.athlete_id', 'athletes.id', 'athletes.parent_id'])
                ->map(fn (Athlete $athlete) => [
                    'athlete_id' => (string) $athlete->athlete_id,
                    'user_id' => (int) $athlete->id,
                ]);

            return $childAthletes->contains('athlete_id', (string) $payment->athlete_id)
                || $childAthletes->contains('user_id', (int) $payment->billable_user_id);
        }

        return false;
    }

    private function tuitionOnly(Builder $query): Builder
    {
        return $query
            ->where('bill_kind', 'INVOICE')
            ->where('payment_type', 'TUITION');
    }

    private function isTuitionPayment(Payment $payment): bool
    {
        return strtoupper((string) ($payment->bill_kind ?? 'INVOICE')) === 'INVOICE'
            && strtoupper((string) $payment->payment_type) === 'TUITION';
    }
}
