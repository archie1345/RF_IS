<?php

namespace App\Http\Requests\Attendance;

use App\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'attendance_opens_at' => ['required', 'date'],
            'attendance_closes_at' => ['required', 'date', 'after:attendance_opens_at'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $session = $this->route('session');

                if (! $session instanceof TrainingSession || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $sessionStartsAt = Carbon::parse($session->session_date.' '.$session->start_time);
                $sessionEndsAt = Carbon::parse($session->session_date.' '.$session->end_time);
                $opensAt = Carbon::parse($this->input('attendance_opens_at'));
                $closesAt = Carbon::parse($this->input('attendance_closes_at'));

                if ($opensAt->lt($sessionStartsAt)) {
                    $validator->errors()->add('attendance_opens_at', 'Attendance QR opening time cannot be before the session starts.');
                }

                if ($opensAt->gte($sessionEndsAt)) {
                    $validator->errors()->add('attendance_opens_at', 'Attendance QR opening time must be before the session ends.');
                }

                if ($closesAt->gt($sessionEndsAt)) {
                    $validator->errors()->add('attendance_closes_at', 'Attendance QR closing time cannot be after the session ends.');
                }
            },
        ];
    }
}
