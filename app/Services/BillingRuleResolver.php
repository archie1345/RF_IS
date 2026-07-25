<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\BillingRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BillingRuleResolver
{
    public function monthlyRuleFor(Athlete $athlete, Carbon $month): ?BillingRule
    {
        return BillingRule::query()
            ->with(['branch', 'group'])
            ->where('charge_kind', BillingRule::KIND_MONTHLY)
            ->where(function (Builder $scope): void {
                $scope->whereNotNull('branch_id')->orWhereNotNull('group_id');
            })
            ->effectiveOn($month)
            ->where(function (Builder $query) use ($athlete): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $athlete->branch_id);
            })
            ->where(function (Builder $query) use ($athlete): void {
                $query->whereNull('group_id')->orWhere('group_id', $athlete->group_id);
            })
            ->orderByRaw(
                'CASE
                    WHEN branch_id IS NOT NULL AND group_id IS NOT NULL THEN 3
                    WHEN group_id IS NOT NULL THEN 2
                    ELSE 1
                END DESC'
            )
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function applyAthleteScope(Builder $query, BillingRule $rule): Builder
    {
        return $query
            ->when(
                $rule->branch_id !== null,
                fn (Builder $athletes): Builder => $athletes->where('branch_id', $rule->branch_id),
            )
            ->when(
                $rule->group_id !== null,
                fn (Builder $athletes): Builder => $athletes->where('group_id', $rule->group_id),
            );
    }
}
