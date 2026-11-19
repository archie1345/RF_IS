<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('preserves guardian contacts when rolling back the parent-role migration', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Rollback Branch',
        'location' => 'Jakarta',
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Rollback Group',
        'is_active' => true,
    ]);
    $user = User::factory()->create(['role' => 'athlete']);
    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 160,
        'weight_kg' => 50,
        'geup' => 'GEUP_1',
        'guardian_name' => 'Guardian Contact',
        'guardian_phone' => '+628123456789',
        'guardian_relation' => 'Parent',
    ]);

    $migration = require database_path('migrations/2026_10_20_000001_remove_parent_role_add_athlete_guardian_fields.php');
    $migration->down();

    expect(Schema::hasColumn('athletes', 'guardian_phone'))->toBeTrue()
        ->and(DB::table('athletes')->where('athlete_id', $athlete->athlete_id)->value('guardian_phone'))
        ->toBe('+628123456789');
});
