<?php

namespace App\Http\Requests\Profiles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoachProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'nik' => ['nullable', 'string', 'max:50']
        ];
    }
}
