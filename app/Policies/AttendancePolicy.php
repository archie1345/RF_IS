<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceVisibilityService;

class AttendancePolicy
{
    public function __construct(private readonly AttendanceVisibilityService $attendanceVisibility) {}

    public function update(User $user, Attendance $attendance): bool
    {
        return $this->attendanceVisibility->userCanUpdate($user, $attendance);
    }
}
