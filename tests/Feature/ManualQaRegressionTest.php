<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\CoachAttendance;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function qaAthlete(string $name = 'QA Athlete'): array
{
    $branch = Branch::create(['branch_name' => $name.' Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => $name.' Group']);
    $user = User::factory()->create(['name' => $name, 'role' => 'athlete']);
    $athlete = Athlete::create([
        'id' => $user->id,
        'group_id' => $group->group_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', $name.' nik'),
        'bpjs_hash' => hash('sha256', $name.' bpjs'),
        'geup' => 'GEUP_1',
    ]);

    return [$user, $athlete, $branch, $group];
}

function qaSession(Branch $branch, Group $group): array
{
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);
    $session = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'QA Session',
        'location' => 'Dojo',
        'session_date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i:s'),
        'end_time' => now()->addHours(2)->format('H:i:s'),
        'status' => 'CONFIRMED',
    ]);

    return [$coachUser, $coach, $session];
}

test('manual attendance update uses athlete and session identity after date changes', function () {
    [$athleteUser, $athlete, $branch, $group] = qaAthlete('Manual Identity');
    [$coachUser, $coach, $session] = qaSession($branch, $group);
    $admin = User::factory()->create(['role' => 'admin']);

    $attendance = Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'date' => now()->subDay()->toDateString(),
        'status' => 'EXCUSED',
    ]);

    $this->actingAs($admin)->post(route('attendance.store'), [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'date' => now()->toDateString(),
        'status' => 'LATE',
    ])->assertRedirect(route('attendance.index'));

    expect(Attendance::query()->where('athlete_id', $athlete->athlete_id)->where('training_session_id', $session->training_session_id)->count())->toBe(1)
        ->and($attendance->refresh()->status)->toBe('LATE');
});

test('coach attendance page shows assigned sessions instead of athlete rows', function () {
    [$athleteUser, $athlete, $branch, $group] = qaAthlete('Coach Access');
    [$coachUser, $coach, $session] = qaSession($branch, $group);

    Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'date' => now()->toDateString(),
        'status' => 'ABSENT',
    ]);

    $this->actingAs($coachUser)
        ->get(route('attendance.index', ['mode' => 'coach']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendancePage')
            ->where('role', 'coach')
            ->has('rows', 0)
            ->has('coachSessions', 1)
            ->where('coachSessions.0.session_id', $session->training_session_id)
            ->where('coachSessions.0.can_attend', true));
});

test('coach can self check in to an assigned session idempotently', function () {
    [$athleteUser, $athlete, $branch, $group] = qaAthlete('Coach Self Attendance');
    [$coachUser, $coach, $session] = qaSession($branch, $group);

    $this->actingAs($coachUser)
        ->post(route('attendance.coach-attend', $session))
        ->assertRedirect(route('attendance.index', ['mode' => 'coach']));
    $this->actingAs($coachUser)
        ->post(route('attendance.coach-attend', $session))
        ->assertRedirect(route('attendance.index', ['mode' => 'coach']));

    $record = CoachAttendance::query()
        ->where('training_session_id', $session->training_session_id)
        ->where('coach_id', $coach->coach_id)
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('TEACH')
        ->and($record->checked_at)->not->toBeNull()
        ->and(CoachAttendance::query()
            ->where('training_session_id', $session->training_session_id)
            ->where('coach_id', $coach->coach_id)
            ->count())->toBe(1);
});

test('coach athlete account can switch attendance modes without mixing records', function () {
    $branch = Branch::create(['branch_name' => 'Dual Role Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Dual Role Group']);
    $user = User::factory()->create(['name' => 'Dual Role User', 'role' => 'coach']);
    $coach = Coach::create(['id' => $user->id, 'status' => 'active']);
    $athlete = Athlete::create([
        'id' => $user->id,
        'group_id' => $group->group_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 170,
        'weight_kg' => 65,
        'nik_hash' => hash('sha256', 'dual role nik'),
        'bpjs_hash' => hash('sha256', 'dual role bpjs'),
        'geup' => 'GEUP_1',
    ]);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'coach']);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'athlete']);

    $session = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Dual Role Session',
        'location' => 'Dojo',
        'session_date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i:s'),
        'end_time' => now()->addHour()->format('H:i:s'),
        'status' => 'CONFIRMED',
    ]);
    Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'date' => now()->toDateString(),
        'status' => 'PRESENT',
    ]);

    $this->actingAs($user)
        ->get(route('attendance.index', ['mode' => 'coach']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('role', 'coach')
            ->where('availableModes', ['coach', 'athlete'])
            ->has('rows', 0)
            ->has('coachSessions', 1));

    $this->actingAs($user)
        ->get(route('attendance.index', ['mode' => 'athlete']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('role', 'athlete')
            ->where('availableModes', ['coach', 'athlete'])
            ->has('rows', 1)
            ->has('coachSessions', 0));
});

test('duplicate coach attend clicks are idempotent', function () {
    [$athleteUser, $athlete, $branch, $group] = qaAthlete('Coach Attend');
    $group->update(['class_type' => 'private']);
    [$coachUser, $coach, $session] = qaSession($branch, $group);

    $payload = ['coach_id' => $coach->coach_id];
    $this->actingAs($coachUser)->post(route('sessions.coach-attendance.store', $session), $payload)->assertRedirect();
    $this->actingAs($coachUser)->post(route('sessions.coach-attendance.store', $session), $payload)->assertRedirect();

    expect(CoachAttendance::query()->where('training_session_id', $session->training_session_id)->where('coach_id', $coach->coach_id)->count())->toBe(1);
});

test('payment proof actions are hidden for admin and coach dashboards', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $coachUser = User::factory()->create(['role' => 'coach']);
    Coach::create(['id' => $coachUser->id, 'status' => 'active']);
    Payment::create(['billable_user_id' => $coachUser->id, 'bill_kind' => 'PAYROLL', 'payment_type' => 'OTHER', 'amount' => 1000, 'total_amount' => 1000, 'paid_amount' => 0, 'remaining_amount' => 1000, 'payment_date' => now()->toDateString(), 'status' => 'PENDING']);

    $this->actingAs($admin)->get(route('payments.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canSubmitPaymentProof', false));
    $this->actingAs($coachUser)->get(route('payments.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canSubmitPaymentProof', false));
});

test('parent can upload linked child proof but unrelated child access is blocked', function () {
    Storage::fake('public');
    [$childUser, $childAthlete] = qaAthlete('Linked Child');
    [$otherUser, $otherAthlete] = qaAthlete('Other Child');
    $parentUser = User::factory()->create(['role' => 'parent']);
    $parent = ParentProfile::create(['id' => $parentUser->id, 'relation' => 'mother']);
    $childAthlete->update(['parent_id' => $parent->parent_id]);

    $linked = Payment::create(['athlete_id' => $childAthlete->athlete_id, 'bill_kind' => 'INVOICE', 'payment_type' => 'TUITION', 'amount' => 1000, 'total_amount' => 1000, 'paid_amount' => 0, 'remaining_amount' => 1000, 'payment_date' => now()->toDateString(), 'status' => 'PENDING']);
    $unrelated = Payment::create(['athlete_id' => $otherAthlete->athlete_id, 'bill_kind' => 'INVOICE', 'payment_type' => 'TUITION', 'amount' => 1000, 'total_amount' => 1000, 'paid_amount' => 0, 'remaining_amount' => 1000, 'payment_date' => now()->toDateString(), 'status' => 'PENDING']);

    $this->actingAs($parentUser)->post(route('payments.proof.submit', $linked), ['proof_file' => UploadedFile::fake()->image('linked.jpg')])->assertRedirect(route('payments.index'));
    $this->actingAs($parentUser)->post(route('payments.proof.submit', $unrelated), ['proof_file' => UploadedFile::fake()->image('blocked.jpg')])->assertForbidden();
});
