<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PrivatizePaymentProofs extends Command
{
    protected $signature = 'payment-proofs:privatize {--dry-run : Report eligible files without changing storage}';

    protected $description = 'Move historical public payment proof files into private storage';

    public function handle(): int
    {
        if (! $this->schemaReady()) {
            $this->error('Payment proof disk columns are missing. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $paths = Payment::query()
            ->whereNotNull('proof_path')
            ->where(fn ($query) => $query->whereNull('proof_disk')->orWhere('proof_disk', Payment::PROOF_DISK_PUBLIC))
            ->pluck('proof_path')
            ->merge(
                PaymentTransaction::query()
                    ->whereNotNull('proof_path')
                    ->where(fn ($query) => $query->whereNull('proof_disk')->orWhere('proof_disk', Payment::PROOF_DISK_PUBLIC))
                    ->pluck('proof_path'),
            )
            ->filter()
            ->unique()
            ->values();

        $migrated = 0;
        $missing = 0;
        $failed = 0;

        foreach ($paths as $oldPath) {
            $oldPath = (string) $oldPath;

            if (! Storage::disk(Payment::PROOF_DISK_PUBLIC)->exists($oldPath)) {
                $missing++;
                $this->warn("Missing public payment proof: {$oldPath}");

                continue;
            }

            $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
            $newPath = 'payment-proofs/legacy/'.Str::uuid().($extension !== '' ? '.'.$extension : '');

            if ($dryRun) {
                $migrated++;
                $this->line("Would move {$oldPath} to private:{$newPath}");

                continue;
            }

            $stream = Storage::disk(Payment::PROOF_DISK_PUBLIC)->readStream($oldPath);
            if (! is_resource($stream)) {
                $failed++;
                $this->error("Unable to read payment proof: {$oldPath}");

                continue;
            }

            try {
                if (! Storage::disk(Payment::PROOF_DISK_PRIVATE)->put($newPath, $stream)) {
                    $failed++;
                    $this->error("Unable to write private payment proof: {$newPath}");

                    continue;
                }

                DB::transaction(function () use ($oldPath, $newPath): void {
                    Payment::query()
                        ->where('proof_path', $oldPath)
                        ->update([
                            'proof_path' => $newPath,
                            'proof_disk' => Payment::PROOF_DISK_PRIVATE,
                        ]);

                    PaymentTransaction::query()
                        ->where('proof_path', $oldPath)
                        ->update([
                            'proof_path' => $newPath,
                            'proof_disk' => Payment::PROOF_DISK_PRIVATE,
                        ]);
                });

                Storage::disk(Payment::PROOF_DISK_PUBLIC)->delete($oldPath);
                $migrated++;
            } catch (Throwable $exception) {
                Storage::disk(Payment::PROOF_DISK_PRIVATE)->delete($newPath);
                report($exception);
                $failed++;
                $this->error("Failed to migrate {$oldPath}: {$exception->getMessage()}");
            } finally {
                fclose($stream);
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Eligible' : 'Migrated').": {$migrated}");
        $this->line("Missing source files: {$missing}");
        $this->line("Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function schemaReady(): bool
    {
        return Schema::hasColumns('payments', ['proof_path', 'proof_disk'])
            && Schema::hasColumns('payment_transactions', ['proof_path', 'proof_disk']);
    }
}
