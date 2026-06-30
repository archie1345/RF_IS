<?php

namespace App\Actions\Payments\Concerns;

use App\Models\Athlete;

trait NormalizesPaymentInput
{
    protected function normalizePaymentData(array $validated): array
    {
        $validated['bill_kind'] = $validated['bill_kind'] ?? 'INVOICE';
        $validated['athlete_id'] = $validated['athlete_id'] ?? null;
        $validated['paid_amount'] = $validated['paid_amount'] ?? 0;
        $validated['payment_date'] = $validated['payment_date'] ?? now()->toDateString();

        if ($validated['bill_kind'] === 'PAYROLL') {
            $validated['athlete_id'] = null;
            $validated['billable_user_id'] = null;

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

    protected function notesFrom(array $validated): string
    {
        return trim(collect([
            $validated['collection_method'] ?? null,
            $validated['notes'] ?? null,
        ])->filter()->implode(' | '));
    }

    protected function appendNote(?string $existing, string $incoming): ?string
    {
        $existing = trim((string) $existing);
        $incoming = trim($incoming);

        if ($incoming === '') {
            return $existing !== '' ? $existing : null;
        }

        if ($existing === '') {
            return $incoming;
        }

        return $existing.' | '.$incoming;
    }
}
