<?php

namespace App\Http\Requests\Attendance;

use App\Support\Domain\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isAdmin() || $this->user()?->isCoach());
    }

    public function rules(): array
    {
        return [
            'attendance_ids' => ['required', 'array', 'min:1'],
            'attendance_ids.*' => ['required', 'integer', 'exists:athlete_attendance,athlete_attendance_id'],
            'status' => ['required', Rule::in(AttendanceStatus::ALL)],
        ];
    }
}
