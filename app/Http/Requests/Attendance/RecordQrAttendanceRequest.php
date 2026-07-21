<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class RecordQrAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user
            && (($user->isAthlete() && $user->athleteProfile !== null) || $user->isParent());
    }

    public function rules(): array
    {
        return [
            'athlete_id' => ['nullable', 'string'],
        ];
    }
}
