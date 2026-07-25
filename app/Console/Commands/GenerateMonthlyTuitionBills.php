<?php

namespace App\Console\Commands;

use App\Models\BillingSetting;
use App\Services\BillingInvoiceGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class GenerateMonthlyTuitionBills extends Command
{
    protected $signature = 'tuition:generate-monthly {--month=} {--amount=} {--force}';

    protected $description = 'Generate monthly tuition invoices using the most specific active billing rule.';

    public function __construct(private readonly BillingInvoiceGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $setting = BillingSetting::query()->where('name', 'monthly_tuition')->first();
        $now = now(config('app.timezone', 'Asia/Jakarta'));

        if (! $this->option('force')) {
            if ($setting && ! $setting->is_active) {
                $this->info('Monthly tuition generation is disabled.');

                return self::SUCCESS;
            }

            if ((int) $now->day !== (int) ($setting?->invoice_day ?? 1)) {
                $this->info('Monthly tuition generation is not scheduled for today.');

                return self::SUCCESS;
            }
        }

        try {
            $month = $this->option('month')
                ? Carbon::createFromFormat('Y-m', (string) $this->option('month'))->startOfMonth()
                : $now->copy()->startOfMonth();
            $override = $this->option('amount') !== null ? (float) $this->option('amount') : null;
            $result = $this->generator->generateMonthly($month, $override);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Generated {$result['created']} monthly tuition bills. Skipped {$result['skipped']} existing bills.");

        return self::SUCCESS;
    }
}
