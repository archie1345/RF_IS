<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('generates sequential member numbers per join date', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Member Number Dojang',
        'location' => 'Test Hall',
        'is_active' => true,
    ]);
    $category = TrainingGroup::query()->create([
        'name' => 'Member Number Category',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'group_name' => 'Member Number Class',
        'branch_id' => $branch->branch_id,
        'training_group_id' => $category->id,
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => false,
    ]);

    $createAthlete = function (string $name, string $joinedAt) use ($branch, $category, $group): Athlete {
        $user = User::factory()->create([
            'name' => $name,
            'role' => 'athlete',
        ]);

        return Athlete::query()->create([
            'id' => $user->id,
            'joined_at' => $joinedAt,
            'group_id' => $group->group_id,
            'training_group_id' => $category->id,
            'branch_id' => $branch->branch_id,
            'height_cm' => 150,
            'weight_kg' => 45,
            'nik_hash' => hash('sha256', $name.'-nik'),
            'bpjs_hash' => hash('sha256', $name.'-bpjs'),
            'geup' => 'GEUP_10',
        ]);
    };

    $first = $createAthlete('Member One', '2026-07-23');
    $second = $createAthlete('Member Two', '2026-07-23');
    $nextDay = $createAthlete('Member Three', '2026-07-24');

    expect($first->member_number)->toBe('G202607230001')
        ->and($second->member_number)->toBe('G202607230002')
        ->and($nextDay->member_number)->toBe('G202607240001');
});

it('seeds the current application schema with representative role data', function () {
    $this->seed();

    $multiRoleUser = User::query()
        ->with(['roleAssignments', 'athleteProfile'])
        ->where('email', 'multirole@rfis.test')
        ->firstOrFail();

    expect($multiRoleUser->roleAssignments->pluck('role')->sort()->values()->all())
        ->toBe(['athlete', 'coach', 'parent'])
        ->and($multiRoleUser->athleteProfile?->member_number)->toBe('G202201150001')
        ->and($multiRoleUser->athleteProfile?->joined_at?->toDateString())->toBe('2022-01-15');

    $allRoleUser = User::query()
        ->with(['roleAssignments', 'athleteProfile', 'coachProfile', 'parentProfile'])
        ->where('email', 'allroles@rfis.test')
        ->firstOrFail();

    expect($allRoleUser->roleAssignments->pluck('role')->sort()->values()->all())
        ->toBe(['admin', 'athlete', 'coach', 'parent'])
        ->and($allRoleUser->primaryRole())->toBe('admin')
        ->and($allRoleUser->athleteProfile)->not->toBeNull()
        ->and($allRoleUser->coachProfile)->not->toBeNull()
        ->and($allRoleUser->parentProfile)->not->toBeNull()
        ->and($allRoleUser->athleteProfile?->member_number)->toBe('G202303010001')
        ->and($allRoleUser->athleteProfile?->joined_at?->toDateString())->toBe('2023-03-01');

    expect(Athlete::query()->where('parent_id', $allRoleUser->parentProfile?->parent_id)->count())->toBeGreaterThan(0);

    $this->assertDatabaseHas('athletes', [
        'member_number' => 'G202407010001',
    ]);
    $this->assertDatabaseHas('athletes', [
        'member_number' => 'G202407010002',
    ]);
    $this->assertDatabaseHas('athletes', [
        'id' => $allRoleUser->id,
        'member_number' => 'G202303010001',
    ]);

    expect(DB::table('class_group_coaches')->count())->toBeGreaterThanOrEqual(8)
        ->and(DB::table('training_session_coaches')->count())->toBeGreaterThan(0)
        ->and(TrainingSession::query()->where('metadata->class_schedule_mode', 'one_day')->exists())->toBeTrue();

    $this->assertDatabaseHas('payments', ['bill_kind' => 'PAYROLL', 'payee_user_id' => $allRoleUser->id]);
    $this->assertDatabaseHas('events', ['status' => 'SCHEDULED']);
    $this->assertDatabaseHas('announcements', ['target_role' => 'ALL']);
});
