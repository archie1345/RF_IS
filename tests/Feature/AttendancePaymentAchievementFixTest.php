<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\Parents;
use App\Models\Payment;
use App\Models\Session;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function makeAthleteWithProfile(string $name = 'Athlete User'): array
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

test('athlete can mark their own open attendance record', function () {
    [$athleteUser, $athlete, $branch, $group] = makeAthleteWithProfile();
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);

    $session = Session::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Evening class',
        'session_date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i'),
        'end_time' => now()->addHours(2)->format('H:i'),
        'status' => 'CONFIRMED',
    ]);

    $attendance = Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'coach_session_id' => $session->csid,
        'date' => now()->toDateString(),
        'status' => 'ABSENT',
    ]);

    $this->actingAs($athleteUser)
        ->put(route('attendance.update', $attendance), ['status' => 'PRESENT'])
        ->assertRedirect(route('attendance.index'));

    expect($attendance->refresh()->status)->toBe('PRESENT');
});

test('parent can see and upload proof for a tuition bill issued to their child user account', function () {
    Storage::fake('public');

    [$childUser, $childAthlete] = makeAthleteWithProfile('Child User');
    $parentUser = User::factory()->create(['role' => 'parent']);
    $parent = Parents::create(['id' => $parentUser->id, 'relation' => 'mother']);
    $childAthlete->update(['parent_id' => $parent->parent_id]);

    $payment = Payment::create([
        'billable_user_id' => $childUser->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 300000,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'remaining_amount' => 300000,
        'payment_date' => now()->toDateString(),
        'status' => 'PENDING',
    ]);

    $this->actingAs($parentUser)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('rows.0.payment_id', $payment->payment_id));

    $this->actingAs($parentUser)
        ->post(route('payments.proof.submit', $payment), [
            'notes' => 'Tuition transfer receipt',
            'proof_file' => UploadedFile::fake()->image('tuition-receipt.jpg'),
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->proof_status)->toBe('SUBMITTED');
    Storage::disk('public')->assertExists($payment->proof_path);
});

test('achievement upload is linked and visible on the achievements page', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($user)
        ->post(route('achievements.store'), [
            'championship_name' => 'Jakarta Open',
            'medal' => 'GOLD',
            'location' => 'Jakarta',
            'event_date' => now()->toDateString(),
            'file' => UploadedFile::fake()->create('result.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('achievements.index'));

    $achievement = UserAchievement::query()->with('file')->firstOrFail();

    expect($achievement->file?->original_name)->toBe('result.pdf');
    Storage::disk('public')->assertExists($achievement->file->file_path);

    $this->actingAs($user)
        ->get(route('achievements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AchievementsPage')
            ->where('achievements.0.file_name', 'result.pdf')
            ->where('achievements.0.file_url', Storage::url($achievement->file->file_path)));
});
