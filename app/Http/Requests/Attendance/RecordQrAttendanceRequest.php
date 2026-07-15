<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class RecordQrAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAthlete() === true && $this->user()->athleteProfile !== null;
    }

    public function rules(): array
    {
        return [];
    }
}
