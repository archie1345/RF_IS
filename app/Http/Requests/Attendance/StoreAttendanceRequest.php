<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'athlete_id' => ['nullable', 'exists:athletes,athlete_id'],
            'coach_session_id' => ['nullable', 'exists:coach_sessions,csid'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['PRESENT', 'ABSENT', 'EXCUSED'])],
            'checked_in_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
            'follow_up_owner' => ['nullable', 'string', 'max:120'],
        ];
    }
}
