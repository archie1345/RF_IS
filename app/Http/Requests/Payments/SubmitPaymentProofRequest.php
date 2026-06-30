<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,heic,heif', 'max:10240'],
        ];
    }
}
