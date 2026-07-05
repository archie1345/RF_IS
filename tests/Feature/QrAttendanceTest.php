<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Domain\AttendanceStatus;
use App\Support\Domain\SessionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

function makeQrUser(string $role, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'email_verified_at' => now(),
    ], $attributes));
}

function makeQrSession(array $overrides = []): array
{
    $branch = Branch::create(['branch_name' => $overrides['branch_name'] ?? 'QR Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => $overrides['group_name'] ?? 'QR Group']);
    $coachUser = makeQrUser('coach', ['name' => $overrides['coach_name'] ?? 'QR Coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);

    $session = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => $overrides['title'] ?? 'QR Session',
        'location' => 'Dojo',
        'session_date' => $overrides['session_date'] ?? now()->toDateString(),
        'start_time' => $overrides['start_time'] ?? now()->subHour()->format('H:i:s'),
        'end_time' => $overrides['end_time'] ?? now()->addHour()->format('H:i:s'),
        'status' => $overrides['status'] ?? SessionStatus::CONFIRMED,
    ]);

    return [$session, $branch, $group, $coachUser, $coach];
}

function makeQrAthlete(Branch $branch, Group $group, array $overrides = []): array
{
    $user = makeQrUser('athlete', ['name' => $overrides['name'] ?? 'QR Athlete']);
    $athlete = Athlete::create([
        'id' => $user->id,
        'group_id' => $overrides['group_id'] ?? $group->group_id,
        'parent_id' => $overrides['parent_id'] ?? null,
        'branch_id' => $overrides['branch_id'] ?? $branch->branch_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', $user->email.'-nik'),
        'bpjs_hash' => hash('sha256', $user->email.'-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    return [$user, $athlete];
}

function generateQrForSession(mixed $testCase, User $user, TrainingSession $session, array $overrides = []): string
{
    $response = $testCase->actingAs($user)->post(route('sessions.attendance-qr.store', $session), array_merge([
        'attendance_opens_at' => $session->session_date.' '.$session->start_time,
        'attendance_closes_at' => $session->session_date.' '.$session->end_time,
    ], $overrides));

    $response->assertRedirect();

    return $response->getSession()->get('attendanceQr')['token'];
}

beforeEach(function () {
    Carbon::setTestNow('2026-06-30 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows admins and assigned coaches to generate unique hashed QR tokens', function () {
    [$session, , , $coachUser] = makeQrSession();
    $admin = makeQrUser('admin');

    $adminToken = generateQrForSession($this, $admin, $session);
    $session->refresh();

    expect($session->attendance_token_hash)->not->toBeNull()
        ->and($session->attendance_token_hash)->not->toBe($adminToken)
        ->and(hash('sha256', $adminToken))->toBe($session->attendance_token_hash);

    $coachToken = generateQrForSession($this, $coachUser, $session);
    $session->refresh();

    expect($coachToken)->not->toBe($adminToken)
        ->and(hash('sha256', $coachToken))->toBe($session->attendance_token_hash);
});

it('prevents unrelated coaches from generating QR tokens', function () {
    [$session] = makeQrSession();
    [, , , $otherCoachUser] = makeQrSession(['coach_name' => 'Other Coach']);

    $this->actingAs($otherCoachUser)
        ->post(route('sessions.attendance-qr.store', $session), [
            'attendance_opens_at' => now()->subMinute()->toDateTimeString(),
            'attendance_closes_at' => now()->addHour()->toDateTimeString(),
        ])
        ->assertForbidden();
});

it('regenerates and revokes QR tokens', function () {
    [$session, $branch, $group] = makeQrSession();
    $admin = makeQrUser('admin');
    [$athleteUser] = makeQrAthlete($branch, $group);

    $oldToken = generateQrForSession($this, $admin, $session);
    $newToken = generateQrForSession($this, $admin, $session);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $oldToken))
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('sessions.attendance-qr.destroy', $session))
        ->assertRedirect();

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $newToken))
        ->assertNotFound();
});

it('requires authentication and prevents parents from recording QR attendance', function () {
    [$session, $branch, $group] = makeQrSession();
    $admin = makeQrUser('admin');
    $token = generateQrForSession($this, $admin, $session);
    Auth::logout();

    $this->post(route('attendance.scan.store', $token))->assertRedirect('/login');

    $parentUser = makeQrUser('parent');
    $parentProfile = ParentProfile::create(['id' => $parentUser->id, 'relation' => 'guardian']);
    makeQrAthlete($branch, $group, ['parent_id' => $parentProfile->parent_id]);

    $this->actingAs($parentUser)
        ->post(route('attendance.scan.store', $token))
        ->assertForbidden();
});

it('shows a valid scan confirmation page for an eligible athlete', function () {
    [$session, $branch, $group] = makeQrSession();
    $admin = makeQrUser('admin');
    [$athleteUser, $athlete] = makeQrAthlete($branch, $group);
    $token = generateQrForSession($this, $admin, $session);

    $this->actingAs($athleteUser)
        ->get(route('attendance.scan.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendanceScanPage')
            ->where('session.id', $session->training_session_id)
            ->where('athlete.athlete_id', $athlete->athlete_id)
            ->where('canSubmit', true));
});

it('updates an existing default absent row during QR check in without inserting a duplicate', function () {
    [$session, $branch, $group] = makeQrSession();
    $admin = makeQrUser('admin');
    [$athleteUser, $athlete] = makeQrAthlete($branch, $group);
    $token = generateQrForSession($this, $admin, $session);

    Attendance::where('athlete_id', $athlete->athlete_id)
        ->where('training_session_id', $session->training_session_id)
        ->update(['status' => AttendanceStatus::ABSENT, 'checked_in_at' => null]);

    $this->actingAs($athleteUser)
        ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile')
        ->post(route('attendance.scan.store', $token))
        ->assertRedirect()
        ->assertSessionHas('attendanceScan.status', 'recorded');

    expect(Attendance::where('athlete_id', $athlete->athlete_id)
        ->where('training_session_id', $session->training_session_id)
        ->count())->toBe(1);

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'status' => AttendanceStatus::PRESENT,
    ]);
});

it('records QR attendance as present with checked in time and is idempotent', function () {
    [$session, $branch, $group] = makeQrSession();
    $admin = makeQrUser('admin');
    [$athleteUser, $athlete] = makeQrAthlete($branch, $group);
    $token = generateQrForSession($this, $admin, $session);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $token))
        ->assertRedirect()
        ->assertSessionHas('attendanceScan.status', 'recorded');

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'status' => AttendanceStatus::PRESENT,
    ]);

    expect(Attendance::where('athlete_id', $athlete->athlete_id)->where('training_session_id', $session->training_session_id)->first()->checked_in_at)->not->toBeNull();

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $token))
        ->assertRedirect()
        ->assertSessionHas('attendanceScan.status', 'already_recorded');

    expect(Attendance::where('athlete_id', $athlete->athlete_id)->where('training_session_id', $session->training_session_id)->count())->toBe(1);
});

it('rejects invalid, early, closed, and canceled scans', function () {
    [$session, $branch, $group] = makeQrSession();
    $admin = makeQrUser('admin');
    [$athleteUser] = makeQrAthlete($branch, $group);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', 'invalid-token'))
        ->assertNotFound();

    $session->update([
        'session_date' => now()->toDateString(),
        'start_time' => now()->addHour()->format('H:i:s'),
        'end_time' => now()->addHours(2)->format('H:i:s'),
    ]);
    $earlyToken = generateQrForSession($this, $admin, $session);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $earlyToken))
        ->assertSessionHasErrors('attendance');

    $session->update([
        'session_date' => now()->toDateString(),
        'start_time' => now()->subHours(2)->format('H:i:s'),
        'end_time' => now()->subHour()->format('H:i:s'),
    ]);
    $closedToken = generateQrForSession($this, $admin, $session);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $closedToken))
        ->assertSessionHasErrors('attendance');

    $session->update(['status' => SessionStatus::CANCELED]);
    $canceledToken = generateQrForSession($this, $admin, $session, [
        'attendance_opens_at' => $session->session_date.' '.$session->start_time,
        'attendance_closes_at' => $session->session_date.' '.$session->end_time,
    ]);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $canceledToken))
        ->assertSessionHasErrors('attendance');
});

it('rejects branch and group ineligible athletes', function () {
    [$session, $branch, $group] = makeQrSession();
    $otherBranch = Branch::create(['branch_name' => 'Other Branch', 'location' => 'Bandung']);
    $otherGroup = Group::create(['group_name' => 'Other Group']);
    $admin = makeQrUser('admin');
    $token = generateQrForSession($this, $admin, $session);

    [$wrongBranchUser] = makeQrAthlete($branch, $group, ['branch_id' => $otherBranch->branch_id, 'name' => 'Wrong Branch']);
    $this->actingAs($wrongBranchUser)
        ->post(route('attendance.scan.store', $token))
        ->assertSessionHasErrors('attendance');

    [$wrongGroupUser] = makeQrAthlete($branch, $group, ['group_id' => $otherGroup->group_id, 'name' => 'Wrong Group']);
    $this->actingAs($wrongGroupUser)
        ->post(route('attendance.scan.store', $token))
        ->assertSessionHasErrors('attendance');
});

it('does not overwrite locked non-present attendance through QR scans', function () {
    [$session, $branch, $group] = makeQrSession([
        'session_date' => now()->subDay()->toDateString(),
        'start_time' => now()->subDay()->subHours(2)->format('H:i:s'),
        'end_time' => now()->subDay()->subHour()->format('H:i:s'),
    ]);
    $admin = makeQrUser('admin');
    [$athleteUser, $athlete] = makeQrAthlete($branch, $group);

    Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'date' => $session->session_date,
        'status' => AttendanceStatus::ABSENT,
    ]);

    $token = generateQrForSession($this, $admin, $session, [
        'attendance_opens_at' => $session->session_date.' '.$session->start_time,
        'attendance_closes_at' => $session->session_date.' '.$session->end_time,
    ]);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $token))
        ->assertSessionHasErrors('attendance');

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'status' => AttendanceStatus::ABSENT,
    ]);
});

it('lets parents view the resulting attendance through active-child filtering', function () {
    [$session, $branch, $group] = makeQrSession();
    $admin = makeQrUser('admin');
    $parentUser = makeQrUser('parent');
    $parentProfile = ParentProfile::create(['id' => $parentUser->id, 'relation' => 'guardian']);
    [$athleteUser, $athlete] = makeQrAthlete($branch, $group, ['parent_id' => $parentProfile->parent_id]);
    $token = generateQrForSession($this, $admin, $session);

    $this->actingAs($athleteUser)->post(route('attendance.scan.store', $token))->assertRedirect();

    $this->actingAs($parentUser)
        ->withSession(['active_child_id' => $athlete->athlete_id])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendancePage')
            ->where('rows.0.athlete_id', $athlete->athlete_id)
            ->where('rows.0.status_value', AttendanceStatus::PRESENT));
});

it('keeps existing public route URLs available outside the QR feature', function () {
    expect(route('attendance.index', absolute: false))->toBe('/attendance')
        ->and(route('sessions.index', absolute: false))->toBe('/sessions')
        ->and(route('users.index', absolute: false))->toBe('/users');
});
