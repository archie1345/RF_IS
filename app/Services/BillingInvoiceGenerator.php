<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\BillingRule;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingInvoiceGenerator
{
    public function __construct(private readonly BillingRuleResolver $rules) {}

    /**
     * @return array{created:int, skipped:int}
     */
    public function generateMonthly(Carbon $month, ?float $overrideAmount = null): array
    {
        $month = $month->copy()->startOfMonth();
        $setting = BillingSetting::query()->where('name', 'monthly_tuition')->first();
        $fallbackAmount = $overrideAmount ?? (float) ($setting?->default_amount ?? 150000);

        if ($fallbackAmount <= 0) {
            throw new InvalidArgumentException('Monthly tuition amount must be greater than zero.');
        }

        $created = 0;
        $skipped = 0;

        $this->activeAthletesQuery()
            ->with(['user:id,name', 'branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->orderBy('athlete_id')
            ->chunkById(100, function ($athletes) use ($month, $overrideAmount, $fallbackAmount, &$created, &$skipped): void {
                foreach ($athletes as $athlete) {
                    $rule = $overrideAmount === null ? $this->rules->monthlyRuleFor($athlete, $month) : null;
                    $amount = $overrideAmount ?? (float) ($rule?->amount ?? $fallbackAmount);
                    $dueDays = (int) ($rule?->due_days ?? 14);
                    $runKey = 'MONTHLY:'.$month->format('Y-m').':'.$athlete->athlete_id;

                    $payment = DB::transaction(function () use ($athlete, $rule, $amount, $dueDays, $month, $runKey): Payment {
                        return Payment::query()->firstOrCreate(
                            ['billing_run_key' => $runKey],
                            [
                                'athlete_id' => $athlete->athlete_id,
                                'billable_user_id' => $athlete->id,
                                'payer_user_id' => null,
                                'payee_user_id' => null,
                                'bill_kind' => 'INVOICE',
                                'payment_type' => 'TUITION',
                                'amount' => $amount,
                                'reference_id' => null,
                                'billing_rule_id' => $rule?->id,
                                'total_amount' => $amount,
                                'paid_amount' => 0,
                                'remaining_amount' => $amount,
                                'payment_date' => $month->toDateString(),
                                'due_date' => $month->copy()->addDays($dueDays)->toDateString(),
                                'collection_method' => 'TRANSFER',
                                'status' => 'PENDING',
                                'proof_status' => 'NONE',
                                'notes' => $this->monthlyNotes($month, $rule),
                            ],
                        );
                    });

                    $payment->wasRecentlyCreated ? $created++ : $skipped++;
                }
            }, 'athlete_id');

        return compact('created', 'skipped');
    }

    /**
     * @return array{created:int, skipped:int}
     */
    public function generateOneTime(BillingRule $rule, Carbon $issueDate): array
    {
        if ($rule->charge_kind !== BillingRule::KIND_ONE_TIME || ! $rule->is_active) {
            throw new InvalidArgumentException('Only active one-time billing rules can be issued.');
        }

        $issueDate = $issueDate->copy()->startOfDay();
        $created = 0;
        $skipped = 0;

        $this->rules
            ->applyAthleteScope($this->activeAthletesQuery(), $rule)
            ->with('user:id,name')
            ->orderBy('athlete_id')
            ->chunkById(100, function ($athletes) use ($rule, $issueDate, &$created, &$skipped): void {
                foreach ($athletes as $athlete) {
                    $runKey = 'ONE_TIME:'.$rule->id.':'.$issueDate->format('Y-m-d').':'.$athlete->athlete_id;
                    $amount = (float) $rule->amount;

                    $payment = DB::transaction(function () use ($athlete, $rule, $issueDate, $runKey, $amount): Payment {
                        return Payment::query()->firstOrCreate(
                            ['billing_run_key' => $runKey],
                            [
                                'athlete_id' => $athlete->athlete_id,
                                'billable_user_id' => $athlete->id,
                                'payer_user_id' => null,
                                'payee_user_id' => null,
                                'bill_kind' => 'INVOICE',
                                'payment_type' => $rule->payment_type,
                                'amount' => $amount,
                                'reference_id' => null,
                                'billing_rule_id' => $rule->id,
                                'total_amount' => $amount,
                                'paid_amount' => 0,
                                'remaining_amount' => $amount,
                                'payment_date' => $issueDate->toDateString(),
                                'due_date' => $issueDate->copy()->addDays((int) $rule->due_days)->toDateString(),
                                'collection_method' => 'TRANSFER',
                                'status' => 'PENDING',
                                'proof_status' => 'NONE',
                                'notes' => trim($rule->name.($rule->notes ? ' — '.$rule->notes : '')),
                            ],
                        );
                    });

                    $payment->wasRecentlyCreated ? $created++ : $skipped++;
                }
            }, 'athlete_id');

        return compact('created', 'skipped');
    }

    private function activeAthletesQuery(): Builder
    {
        return Athlete::query()
            ->whereNull('athletes.deleted_at')
            ->whereHas('user', function (Builder $users): void {
                $users
                    ->whereNull('users.deleted_at')
                    ->where('account_status', User::ACCOUNT_STATUS_ACTIVE);
            });
    }

    private function monthlyNotes(Carbon $month, ?BillingRule $rule): string
    {
        $label = $month->translatedFormat('F Y');

        return $rule
            ? "Iuran bulanan {$label} — {$rule->name} ({$rule->scopeLabel()})"
            : "Iuran bulanan {$label} — tarif default";
    }
}
