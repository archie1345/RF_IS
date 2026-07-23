<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(['CASH', 'CARD', 'TRANSFER', 'OTHER'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
