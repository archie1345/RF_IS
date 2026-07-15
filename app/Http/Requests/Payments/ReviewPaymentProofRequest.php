<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'notes' => ['nullable', 'string'],
            'approved_amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
