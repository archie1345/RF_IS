<?php

use App\Actions\Attendance\GenerateSessionAttendanceQr;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\AttendanceQrTokenService;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeQrAttendanceContext(string $prefix = 'QR Test'): array
{
    $branch = Branch::create([
        'branch_name' => $prefix.' Branch',
        'location' => 'Jakarta',
    ]);
    $group = Group::create(['group_name' => $prefix.' Group']);
    $athleteUser = User::factory()->create([
        'name' => $prefix.' Athlete',
        'role' => 'athlete',
    ]);
    $athlete = Athlete::create([
        'id' => $athleteUser->id,
        'group_id' => $group->group_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', $prefix.' nik'),
        'bpjs_hash' => hash('sha256', $prefix.' bpjs'),
        'geup' => 'GEUP_1',
    ]);
    $session = TrainingSession::create([
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => $prefix.' Session',
        'session_date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i'),
        'end_time' => now()->addHours(2)->format('H:i'),
        'status' => 'CONFIRMED',
    ]);
    $attendance = Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'date' => now()->toDateString(),
        'status' => 'ABSENT',
    ]);

    return [$athleteUser, $athlete, $branch, $group, $session, $attendance];
}

test('attendance QR stays open without a closing time until manually closed', function () {
    Carbon::setTestNow('2026-07-24 10:00:00');

    [, , , , $session] = makeQrAttendanceContext('Persistent QR');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('sessions.attendance-qr.store', $session))
        ->assertRedirect();

    $session->refresh();
    $token = $session->attendance_qr_token;

    expect($token)->not->toBeNull()
        ->and($session->attendance_token_hash)->not->toBeNull()
        ->and($session->attendance_opens_at)->not->toBeNull()
        ->and($session->attendance_closes_at)->toBeNull()
        ->and($session->attendance_qr_revoked_at)->toBeNull()
        ->and(app(AttendanceQrTokenService::class)->findActiveSessionByToken($token)?->training_session_id)
        ->toBe($session->training_session_id);

    Carbon::setTestNow('2026-07-25 10:00:00');
    expect(app(AttendanceQrTokenService::class)->findActiveSessionByToken($token)?->training_session_id)
        ->toBe($session->training_session_id);

    $this->actingAs($admin)
        ->delete(route('sessions.attendance-qr.destroy', $session))
        ->assertRedirect();

    $session->refresh();
    expect($session->attendance_qr_token)->toBeNull()
        ->and($session->attendance_token_hash)->toBeNull()
        ->and($session->attendance_qr_revoked_at)->not->toBeNull()
        ->and(app(AttendanceQrTokenService::class)->findActiveSessionByToken($token))->toBeNull();
});

test('parent cannot bypass QR but can scan for a linked child', function () {
    Carbon::setTestNow('2026-07-24 12:00:00');

    [, $athlete, , , $session, $attendance] = makeQrAttendanceContext('Parent QR');
    $parentUser = User::factory()->create(['role' => 'parent']);
    $parent = ParentProfile::create([
        'id' => $parentUser->id,
        'relation' => 'mother',
    ]);
    $athlete->update(['parent_id' => $parent->parent_id]);

    $this->actingAs($parentUser)
        ->post(route('attendance.store'), [
            'athlete_id' => $athlete->athlete_id,
            'training_session_id' => $session->training_session_id,
            'date' => now()->toDateString(),
            'status' => 'PRESENT',
        ])
        ->assertForbidden();

    $this->actingAs($parentUser)
        ->put(route('attendance.update', $attendance), ['status' => 'PRESENT'])
        ->assertForbidden();

    expect($attendance->refresh()->status)->toBe('ABSENT');

    [, $token] = app(GenerateSessionAttendanceQr::class)->handle($session);

    $this->actingAs($parentUser)
        ->post(route('attendance.scan.store', $token), [
            'athlete_id' => $athlete->athlete_id,
        ])
        ->assertRedirect();

    expect($attendance->refresh()->status)->toBe('PRESENT');
});
