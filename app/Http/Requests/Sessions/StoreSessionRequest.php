<?php

namespace App\Http\Requests\Sessions;

use App\Http\Requests\Sessions\Concerns\SessionMutationRules;
use App\Models\Session;
use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    use SessionMutationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Session::class);
    }

    public function rules(): array
    {
        return $this->sessionRules();
    }
}
