<?php

namespace App\Http\Requests\Profiles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAthleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'geup' => ['required', Rule::in(['GEUP_1', 'GEUP_2', 'GEUP_3', 'GEUP_4', 'GEUP_5', 'GEUP_6', 'GEUP_7', 'GEUP_8', 'GEUP_9', 'GEUP_10', 'DAN'])],
            'gender' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'bday' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'nik' => ['nullable', 'string', 'max:50'],
            'bpjs' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'exists:branches,branch_id'],
            'group_id' => ['nullable', 'exists:class_groups,group_id'],
        ];
    }
}
