<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PaymentVisibilityService
{
    public function __construct(
        private readonly ParentChildContextService $childContext,
        private readonly ActiveRoleContextService $activeRoleContext,
    ) {}

    public function visiblePaymentsQuery(Request $request, ?string $mode = null): Builder
    {
        $query = Payment::query();
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $mode = $this->resolveMode($user, $request, $mode);

        if ($mode === 'admin') {
            return $query;
        }

        if ($mode === 'parent') {
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

        if ($mode === 'athlete') {
            $athleteId = $user->athleteProfile?->athlete_id;

            return $this->tuitionOnly($query)
                ->where(function ($inner) use ($user, $athleteId): void {
                    $inner->where('billable_user_id', $user->id)
                        ->when($athleteId, fn ($athleteQuery) => $athleteQuery->orWhere('athlete_id', $athleteId));
                });
        }

        if ($mode === 'coach') {
            return $query->where(function ($inner) use ($user): void {
                $inner->where('billable_user_id', $user->id)
                    ->orWhere('payee_user_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function userCanSubmitProof(?User $user, Payment $payment, ?string $mode = null): bool
    {
        if (! $user || ! $this->isTuitionPayment($payment)) {
            return false;
        }

        $request = $this->currentRequestFor($user);
        $mode = $this->resolveMode($user, $request, $mode);

        if (! in_array($mode, ['athlete', 'parent'], true)) {
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

        if ($mode === 'athlete') {
            return (string) $payment->athlete_id === (string) $user->athleteProfile?->athlete_id;
        }

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

    private function resolveMode(User $user, ?Request $request, ?string $mode): string
    {
        $normalizedMode = strtolower(trim((string) $mode));
        if ($normalizedMode !== '' && $user->hasRole($normalizedMode)) {
            return $normalizedMode;
        }

        if ($request) {
            return $this->activeRoleContext->activeRole($request, $user);
        }

        return $user->primaryRole();
    }

    private function currentRequestFor(User $user): ?Request
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return null;
        }

        $request = request();

        return (int) $request->user()?->id === (int) $user->id ? $request : null;
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
