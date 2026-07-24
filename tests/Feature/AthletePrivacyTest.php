<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\User;

function athletePrivacyFixture(array $overrides = []): Athlete
{
    $branch = Branch::query()->create([
        'branch_name' => 'Privacy Test Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $category = TrainingGroup::query()->create([
        'name' => 'Privacy Test Category',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'training_group_id' => $category->id,
        'group_name' => 'Privacy Test Class',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $user = User::factory()->create(['role' => 'athlete']);

    return Athlete::query()->create(array_merge([
        'id' => $user->id,
        'joined_at' => '2026-07-24',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'training_group_id' => $category->id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'geup' => 'GEUP_10',
    ], $overrides));
}

it('does not allow callers to mass assign a member number', function () {
    $athlete = athletePrivacyFixture([
        'member_number' => 'G199901010001',
    ]);

    expect($athlete->member_number)
        ->toBe('G202607240001')
        ->not->toBe('G199901010001');
});

it('never serializes sensitive hashes or encrypted identifiers', function () {
    $athlete = athletePrivacyFixture([
        'nik_hash' => hash('sha256', '1234567890123456'),
        'nik_ciphertext' => '1234567890123456',
        'bpjs_hash' => hash('sha256', '1234567890123'),
        'bpjs_ciphertext' => '1234567890123',
    ]);

    $serialized = $athlete->fresh()->toArray();

    expect($serialized)
        ->not->toHaveKeys(['nik_hash', 'nik_ciphertext', 'bpjs_hash', 'bpjs_ciphertext']);
    expect($athlete->fresh()->displayValue('nik'))->toBe('1234567890123456');
    expect($athlete->fresh()->displayValue('bpjs'))->toBe('1234567890123');
});
