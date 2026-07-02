<?php

namespace App\Http\Requests\Attendance;

use App\Models\TrainingSession;
use Illuminate\Foundation\Http\FormRequest;

class GenerateSessionAttendanceQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $this->user() !== null
            && $session instanceof TrainingSession
            && $this->user()->can('manageAttendanceQr', $session);
    }

    public function rules(): array
    {
        return [
            'attendance_opens_at' => ['nullable', 'date'],
            'attendance_closes_at' => ['nullable', 'date', 'after:attendance_opens_at'],
        ];
    }
}
