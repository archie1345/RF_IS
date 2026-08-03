<?php

namespace App\Http\Requests\Payments\Concerns;

use Illuminate\Validation\Rule;

trait PaymentMutationRules
{
    protected function paymentRules(): array
    {
        return [
            'athlete_id' => [
                'nullable',
                Rule::exists('athletes', 'athlete_id')->whereNull('deleted_at'),
            ],
            'payment_type' => ['required', Rule::in(['TUITION', 'UNIFORM', 'LICENSE', 'CHAMPIONSHIP', 'OTHER', 'UNKNOWN'])],
            'total_amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:payment_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'collection_method' => ['nullable', Rule::in(['CASH', 'CARD', 'TRANSFER', 'OTHER'])],
            'bill_kind' => ['nullable', Rule::in(['INVOICE', 'PAYROLL'])],
            'billable_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'payee_user_id' => [
                'nullable',
                'required_if:bill_kind,PAYROLL',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
