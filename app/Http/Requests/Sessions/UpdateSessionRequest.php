<?php

namespace App\Http\Requests\Sessions;

use App\Http\Requests\Sessions\Concerns\SessionMutationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
{
    use SessionMutationRules;

    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session && (bool) $this->user()?->can('update', $session);
    }

    public function rules(): array
    {
        return $this->sessionRules();
    }
}
