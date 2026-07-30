<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;

function sessionIntegrityCoach(string $name): array
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'coach',
        'email_verified_at' => now(),
    ]);
    $coach = Coach::query()->create([
        'id' => $user->id,
        'status' => 'active',
    ]);

    return [$user, $coach];
}

it('does not promote an assistant coach to primary coach when editing a session', function () {
    [, $primaryCoach] = sessionIntegrityCoach('Primary Integrity Coach');
    [$assistantUser, $assistantCoach] = sessionIntegrityCoach('Assistant Integrity Coach');
    $branch = Branch::query()->create([
        'branch_name' => 'Session Integrity Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $primaryCoach->coach_id,
        'group_name' => 'Session Integrity Group',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $session = TrainingSession::query()->create([
        'coach_id' => $primaryCoach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Primary Ownership Session',
        'location' => 'Old Dojang',
        'session_date' => today()->addDay()->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => 'CONFIRMED',
    ]);
    $session->assignedCoaches()->attach([$primaryCoach->coach_id, $assistantCoach->coach_id]);

    $this->actingAs($assistantUser)
        ->put(route('sessions.update', $session), [
            'title' => 'Updated by Assistant',
            'branch_id' => $branch->branch_id,
            'group_id' => $group->group_id,
            'location' => 'New Dojang',
            'session_date' => $session->session_date->toDateString(),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'status' => 'CONFIRMED',
        ])
        ->assertRedirect(route('sessions.index'));

    expect((string) $session->fresh()->coach_id)->toBe((string) $primaryCoach->coach_id)
        ->and($session->fresh()->title)->toBe('Updated by Assistant');
});

it('locks branch class date and time after athlete attendance history exists', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
    [, $coach] = sessionIntegrityCoach('Historical Session Coach');
    $branch = Branch::query()->create([
        'branch_name' => 'Historical Session Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $otherBranch = Branch::query()->create([
        'branch_name' => 'Other Historical Branch',
        'location' => 'Surabaya',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Historical Session Group',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $session = TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Historical Identity Session',
        'location' => 'Dojang',
        'session_date' => today()->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => 'CONFIRMED',
    ]);
    $athleteUser = User::factory()->create(['role' => 'athlete']);
    $athlete = Athlete::query()->create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 160,
        'weight_kg' => 55,
        'geup' => 'GEUP_5',
        'nik_hash' => hash('sha256', 'session-history-nik'),
        'bpjs_hash' => hash('sha256', 'session-history-bpjs'),
    ]);
    Attendance::query()->create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'date' => $session->session_date,
        'status' => 'PRESENT',
        'checked_in_at' => now(),
    ]);

    $this->actingAs($admin)
        ->put(route('sessions.update', $session), [
            'title' => 'Attempted Reassignment',
            'branch_id' => $otherBranch->branch_id,
            'group_id' => $group->group_id,
            'location' => 'Other Dojang',
            'session_date' => today()->addDay()->toDateString(),
            'start_time' => '17:00',
            'end_time' => '19:00',
            'status' => 'CONFIRMED',
        ])
        ->assertSessionHasErrors(['session', 'branch_id', 'session_date', 'start_time', 'end_time']);

    $session->refresh();
    expect((string) $session->branch_id)->toBe((string) $branch->branch_id)
        ->and($session->session_date->toDateString())->toBe(today()->toDateString())
        ->and(substr((string) $session->start_time, 0, 5))->toBe('16:00');
});
