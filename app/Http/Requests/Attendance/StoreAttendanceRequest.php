<?php

namespace App\Http\Requests\Attendance;

use App\Support\Domain\AttendanceStatus;
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
            'training_session_id' => ['required', 'exists:training_sessions,training_session_id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(AttendanceStatus::ALL)],
            'checked_in_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
            'follow_up_owner' => ['nullable', 'string', 'max:120'],
        ];
    }
}
