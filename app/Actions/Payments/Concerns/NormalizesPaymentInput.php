<?php

namespace App\Actions\Payments\Concerns;

use App\Models\Athlete;
use Illuminate\Support\Carbon;

trait NormalizesPaymentInput
{
    protected function normalizePaymentData(array $validated): array
    {
        $validated['bill_kind'] = strtoupper((string) ($validated['bill_kind'] ?? 'INVOICE'));
        $validated['athlete_id'] = $validated['athlete_id'] ?? null;
        $validated['paid_amount'] = $validated['paid_amount'] ?? 0;
        $validated['payment_date'] = $validated['payment_date'] ?? now()->toDateString();
        $validated['collection_method'] = strtoupper((string) ($validated['collection_method'] ?? 'TRANSFER'));
        $validated['due_date'] = $validated['due_date'] ?? $this->defaultDueDate(
            $validated['payment_date'],
            $validated['bill_kind'],
        );

        if ($validated['bill_kind'] === 'PAYROLL') {
            $validated['athlete_id'] = null;
            $validated['billable_user_id'] = null;
            $validated['payment_type'] = 'OTHER';

            return $validated;
        }

        $validated['payee_user_id'] = null;

        return $this->normalizeInvoiceRecipient($validated);
    }

    protected function normalizeInvoiceRecipient(array $validated): array
    {
        $athleteId = $validated['athlete_id'] ?? null;
        $billableUserId = $validated['billable_user_id'] ?? null;

        if (! empty($billableUserId)) {
            $athlete = Athlete::query()->where('id', $billableUserId)->first();
            $validated['athlete_id'] = $athlete?->athlete_id;

            return $validated;
        }

        if (! empty($athleteId)) {
            $athlete = Athlete::query()->find($athleteId);
            $validated['billable_user_id'] = $athlete?->id;
        }

        return $validated;
    }

    protected function notesFrom(array $validated): ?string
    {
        $notes = trim((string) ($validated['notes'] ?? ''));

        return $notes !== '' ? $notes : null;
    }

    private function defaultDueDate(string $paymentDate, string $billKind): string
    {
        $issuedOn = Carbon::parse($paymentDate);

        return $billKind === 'PAYROLL'
            ? $issuedOn->toDateString()
            : $issuedOn->copy()->addDays(14)->toDateString();
    }
}
