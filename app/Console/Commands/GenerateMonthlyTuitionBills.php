<?php

namespace App\Console\Commands;

use App\Models\Athlete;
use App\Models\BillingSetting;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyTuitionBills extends Command
{
    protected $signature = 'tuition:generate-monthly {--month=} {--amount=} {--force}';

    protected $description = 'Generate monthly tuition invoices for active athletes.';

    public function handle(): int
    {
        $setting = BillingSetting::monthlyTuition();
        $now = now();

        if (! $this->option('force')) {
            if (! $setting->is_active) {
                $this->info('Monthly tuition generation is disabled.');

                return self::SUCCESS;
            }

            if ((int) $now->day !== (int) $setting->invoice_day) {
                $this->info('Monthly tuition generation is not scheduled for today.');

                return self::SUCCESS;
            }
        }

        $month = $this->option('month')
            ? Carbon::parse((string) $this->option('month'))->startOfMonth()
            : $now->startOfMonth();
        $amount = (float) ($this->option('amount') ?: $setting->default_amount ?: 150000);

        if ($amount <= 0) {
            $this->error('Tuition amount must be greater than zero.');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $referencePrefix = 'TUITION-'.$month->format('Ym').'-';

        Athlete::query()
            ->with('user:id,name')
            ->whereNull('deleted_at')
            ->orderBy('athlete_id')
            ->chunkById(100, function ($athletes) use ($amount, $month, $referencePrefix, &$created, &$skipped): void {
                foreach ($athletes as $athlete) {
                    $referenceId = $referencePrefix.$athlete->athlete_id;
                    $exists = Payment::query()
                        ->where('payment_type', 'TUITION')
                        ->where('reference_id', $referenceId)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    Payment::query()->create([
                        'athlete_id' => $athlete->athlete_id,
                        'billable_user_id' => $athlete->id,
                        'payer_user_id' => null,
                        'payee_user_id' => null,
                        'bill_kind' => 'INVOICE',
                        'payment_type' => 'TUITION',
                        'amount' => $amount,
                        'reference_id' => $referenceId,
                        'total_amount' => $amount,
                        'paid_amount' => 0,
                        'remaining_amount' => $amount,
                        'payment_date' => $month->toDateString(),
                        'status' => 'PENDING',
                        'proof_status' => 'NONE',
                        'notes' => 'Auto-generated monthly tuition for '.$month->format('F Y'),
                    ]);

                    $created++;
                }
            }, 'athlete_id');

        $this->info("Generated {$created} monthly tuition bills. Skipped {$skipped} existing bills.");

        return self::SUCCESS;
    }
}
