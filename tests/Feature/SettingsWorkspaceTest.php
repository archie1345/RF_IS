<?php

use App\Models\User;
use App\Models\UserRoleAssignment;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated user can open the settings overview and profile workspace', function () {
    $user = User::factory()->create([
        'name' => 'Settings User',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Overview')
            ->where('account.name', 'Settings User')
            ->where('account.roles.0', 'athlete'));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ProfileWorkspace')
            ->where('user.id', $user->uuid)
            ->where('user.name', 'Settings User')
            ->where('accountUpdateUrl', '/settings/profile')
            ->has('branches')
            ->has('groups'));
});

test('admin user profile details expose route key uuid for profile action endpoints', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $target = User::factory()->create([
        'name' => 'Target Athlete',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('users.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('profiles/ProfileDetailsPage')
            ->where('user.id', $target->uuid)
            ->where('user.name', 'Target Athlete'));
});

test('user directory rows include branch and group ids for client-side roster filtering', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $branch = \App\Models\Branch::query()->create([
        'branch_name' => 'Filter Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $category = \App\Models\TrainingGroup::query()->create([
        'name' => 'Filter Category',
        'is_active' => true,
    ]);
    $group = \App\Models\Group::query()->create([
        'branch_id' => $branch->branch_id,
        'training_group_id' => $category->id,
        'group_name' => 'Filter Class',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $athleteUser = User::factory()->create([
        'name' => 'Filtered Athlete',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    \App\Models\Athlete::query()->create([
        'id' => $athleteUser->id,
        'joined_at' => '2026-07-24',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'training_group_id' => $category->id,
        'height_cm' => 165,
        'weight_kg' => 55,
        'geup' => 'GEUP_8',
    ]);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AthletesPage')
            ->where('rows.0.user_id', $athleteUser->uuid)
            ->where('rows.0.branch_id', (string) $branch->branch_id)
            ->where('rows.0.group_id', (string) $group->group_id));
});

test('settings overview exposes all assigned roles for a multi role account', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'admin']);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'athlete']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Overview')
            ->where('account.roles', ['admin', 'athlete'])
            ->has('account.roles', 2));
});

test('championship detail and event history expose uuid route keys for athlete profiles and registrations', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $athleteUser = User::factory()->create([
        'name' => 'Championship Athlete',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $branch = \App\Models\Branch::query()->create([
        'branch_name' => 'Championship Branch',
        'location' => 'Bandung',
        'is_active' => true,
    ]);
    $category = \App\Models\TrainingGroup::query()->create([
        'name' => 'Championship Category',
        'is_active' => true,
    ]);
    $group = \App\Models\Group::query()->create([
        'branch_id' => $branch->branch_id,
        'training_group_id' => $category->id,
        'group_name' => 'Championship Class',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $athlete = \App\Models\Athlete::query()->create([
        'id' => $athleteUser->id,
        'joined_at' => '2026-07-24',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'training_group_id' => $category->id,
        'height_cm' => 165,
        'weight_kg' => 55,
        'geup' => 'GEUP_8',
    ]);
    $event = \App\Models\Event::query()->create([
        'e_name' => 'UUID Route Key Cup',
        'e_date' => now()->subDay()->toDateString(),
        'location' => 'Bandung',
        'entry_fee' => 150000,
        'max_slots' => 24,
        'status' => 'COMPLETED',
        'level' => 'NATIONAL',
    ]);
    $registration = \App\Models\EventRegistration::query()->create([
        'event_id' => $event->event_id,
        'athlete_id' => $athlete->athlete_id,
        'category' => 'KYORUGI',
        'status' => 'CONFIRMED',
    ]);

    $this->actingAs($admin)
        ->get(route('championships.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ChampionshipDetailPage')
            ->where('athleteRows.0.athlete_user_id', $athleteUser->uuid));

    $this->actingAs($admin)
        ->get(route('admin.events.history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EventHistoryPage')
            ->where('events.0.id', $event->uuid)
            ->where('events.0.registrations.0.id', $registration->uuid));
});

test('admin people compatibility summaries format member and instructor rows accurately', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $athleteUser = User::factory()->create([
        'name' => 'Summary Athlete',
        'phone' => '081234567890',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
        'email_verified_at' => now(),
    ]);
    $branch = \App\Models\Branch::query()->create([
        'branch_name' => 'Summary Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = \App\Models\Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Summary Class',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    $athlete = \App\Models\Athlete::query()->create([
        'id' => $athleteUser->id,
        'joined_at' => '2026-07-24',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 165,
        'weight_kg' => 55,
        'geup' => 'GEUP_6',
    ]);

    $coachUser = User::factory()->create([
        'name' => 'Summary Coach',
        'email' => 'summary.coach@example.com',
        'role' => 'coach',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $coach = \App\Models\Coach::query()->create([
        'id' => $coachUser->id,
        'specialization' => 'Kyorugi',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.members'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AdminFeaturePage')
            ->where('mode', 'members')
            ->where('rows.0.Anggota', "Summary Athlete\n".$athlete->athlete_id)
            ->where('rows.0.Sabuk', 'GEUP 6')
            ->where('rows.0.Kontak', '081234567890')
            ->where('rows.0.Status', 'Suspended')
            ->where('rows.0.Aksi', '/users/'.$athleteUser->uuid));

    $this->actingAs($admin)
        ->get(route('admin.instructors'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AdminFeaturePage')
            ->where('mode', 'instructors')
            ->where('rows.0.Pelatih', "Summary Coach\n".$coach->coach_id)
            ->where('rows.0.Spesialisasi', 'Kyorugi')
            ->where('rows.0.Kontak', 'summary.coach@example.com')
            ->where('rows.0.Status', 'Active'));
});
