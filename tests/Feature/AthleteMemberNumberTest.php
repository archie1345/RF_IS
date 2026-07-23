<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Carbon;

it('generates sequential member numbers per join date', function () {
    Carbon::setTestNow('2026-07-23 10:00:00');

    $branch = Branch::query()->create([
        'branch_name' => 'Member Number Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $category = TrainingGroup::query()->create([
        'name' => 'Member Number Category',
        'is_active' => true,
    ]);

    $makeAthlete = function (string $email, string $joinedAt) use ($branch, $category): Athlete {
        $user = User::factory()->create(['email' => $email, 'role' => 'athlete']);

        return Athlete::query()->create([
            'id' => $user->id,
            'joined_at' => $joinedAt,
            'branch_id' => $branch->branch_id,
            'training_group_id' => $category->id,
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

    $branch = Branch::query()->create([
        'branch_name' => 'Default Join Date Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $category = TrainingGroup::query()->create([
        'name' => 'Default Join Date Category',
        'is_active' => true,
    ]);
    $user = User::factory()->create(['role' => 'athlete']);

    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'training_group_id' => $category->id,
        'nik_hash' => hash('sha256', 'default-date-nik'),
        'bpjs_hash' => hash('sha256', 'default-date-bpjs'),
        'geup' => 'GEUP_10',
    ]);

    expect($athlete->joined_at?->toDateString())->toBe('2026-08-01')
        ->and($athlete->member_number)->toBe('G202608010001');
});