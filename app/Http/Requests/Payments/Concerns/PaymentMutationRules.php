<?php

namespace App\Http\Requests\Payments\Concerns;

use Illuminate\Validation\Rule;

trait PaymentMutationRules
{
    protected function paymentRules(): array
    {
        return [
            'athlete_id' => ['nullable', 'exists:athletes,athlete_id'],
            'payment_type' => ['required', Rule::in(['TUITION', 'UNIFORM', 'LICENSE', 'CHAMPIONSHIP', 'OTHER', 'UNKNOWN'])],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'collection_method' => ['nullable', Rule::in(['CASH', 'TRANSFER', 'OTHER'])],
            'bill_kind' => ['nullable', Rule::in(['INVOICE', 'PAYROLL'])],
            'billable_user_id' => ['nullable', 'exists:users,id'],
            'payee_user_id' => ['nullable', 'required_if:bill_kind,PAYROLL', 'exists:users,id'],
        ];
    }
}
