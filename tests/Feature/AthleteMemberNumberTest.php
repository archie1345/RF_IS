<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Carbon;

function athleteMemberNumberTestContext(string $suffix): array
{
    $branch = Branch::query()->create([
        'branch_name' => "Member Number Branch {$suffix}",
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $category = TrainingGroup::query()->create([
        'name' => "Member Number Category {$suffix}",
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'training_group_id' => $category->id,
        'group_name' => "Member Number Class {$suffix}",
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    return [$branch, $category, $group];
}

it('generates sequential member numbers per join date', function () {
    Carbon::setTestNow('2026-07-23 10:00:00');

    [$branch, $category, $group] = athleteMemberNumberTestContext('Sequential');

    $makeAthlete = function (string $email, string $joinedAt) use ($branch, $category, $group): Athlete {
        $user = User::factory()->create(['email' => $email, 'role' => 'athlete']);

        return Athlete::query()->create([
            'id' => $user->id,
            'joined_at' => $joinedAt,
            'branch_id' => $branch->branch_id,
            'group_id' => $group->group_id,
            'training_group_id' => $category->id,
            'height_cm' => 150.00,
            'weight_kg' => 45.00,
            'nik_hash' => hash('sha256', $email.'-nik'),
            'bpjs_hash' => hash('sha256', $email.'-bpjs'),
            'geup' => 'GEUP_10',
        ]);
    };

    $first = $makeAthlete('member-one@example.test', '2026-07-23');
    $second = $makeAthlete('member-two@example.test', '2026-07-23');
    $nextDay = $makeAthlete('member-next-day@example.test', '2026-07-24');

    expect($first->member_number)->toBe('G202607230001')
        ->and($second->member_number)->toBe('G202607230002')
        ->and($nextDay->member_number)->toBe('G202607240001');
});

it('uses today when no join date is supplied', function () {
    Carbon::setTestNow('2026-08-01 09:00:00');

    [$branch, $category, $group] = athleteMemberNumberTestContext('Default Date');
    $user = User::factory()->create(['role' => 'athlete']);

    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'training_group_id' => $category->id,
        'height_cm' => 150.00,
        'weight_kg' => 45.00,
        'nik_hash' => hash('sha256', 'default-date-nik'),
        'bpjs_hash' => hash('sha256', 'default-date-bpjs'),
        'geup' => 'GEUP_10',
    ]);

    expect($athlete->joined_at?->toDateString())->toBe('2026-08-01')
        ->and($athlete->member_number)->toBe('G202608010001');
});
