<?php

namespace App\Http\Requests\ParentChild;

use Illuminate\Foundation\Http\FormRequest;

class SwitchActiveChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isParent();
    }

    public function rules(): array
    {
        return [
            'athlete_id' => ['required', 'exists:athletes,athlete_id'],
        ];
    }
}
