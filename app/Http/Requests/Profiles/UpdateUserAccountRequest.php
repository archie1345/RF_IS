<?php

namespace App\Http\Requests\Profiles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id, 'id')],
            'gender' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'bday' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
