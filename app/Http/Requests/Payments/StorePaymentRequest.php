<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Payments\Concerns\PaymentMutationRules;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    use PaymentMutationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return $this->paymentRules();
    }
}
