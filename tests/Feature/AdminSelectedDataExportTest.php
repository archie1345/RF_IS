<?php

use App\Exports\MultiSheetExport;
use App\Models\User;
use App\Services\AdminDataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

test('only admins can open the selected data export builder', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $athlete = User::factory()->create([
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.data-export.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AdminDataExportPage')
            ->has('datasets', 8)
            ->where('datasets.0.key', 'accounts')
            ->where('datasets.0.label', 'Accounts')
            ->has('datasets.0.fields'));

    $this->actingAs($athlete)
        ->get(route('admin.data-export.index'))
        ->assertForbidden();
});

test('admin can choose the exact Excel columns to export', function () {
    Excel::fake();
    $this->travelTo(now()->setDate(2026, 7, 28)->setTime(10, 30, 0));

    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    User::factory()->create([
        'name' => 'Exported Athlete',
        'email' => 'exported@example.com',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.data-export.download', [
            'datasets' => ['accounts'],
            'fields' => ['accounts' => ['name', 'email', 'status']],
            'status' => [User::ACCOUNT_STATUS_SUSPENDED],
        ]))
        ->assertOk();

    Excel::assertDownloaded('System_Data_Export_2026-07-28_1030.xlsx', function (MultiSheetExport $export): bool {
        $sheets = $export->sheets();

        return count($sheets) === 1 && $sheets[0]->headings() === ['Name', 'Email', 'Account Status'];
    });
});

test('selected account export maps only filtered rows and selected columns', function () {
    User::factory()->create([
        'name' => 'Active Member',
        'email' => 'active@example.com',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
    ]);
    $notActive = User::factory()->create([
        'name' => 'Not Active Member',
        'email' => 'not-active@example.com',
        'role' => 'coach',
        'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
    ]);

    $export = app(AdminDataExportService::class)->makeExport(
        'accounts',
        ['name', 'email', 'status'],
        ['status' => User::ACCOUNT_STATUS_SUSPENDED],
    );
    $records = $export->query()->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()->is($notActive))->toBeTrue()
        ->and($export->map($records->first()))->toBe([
            'Not Active Member',
            'not-active@example.com',
            'Not active',
        ]);
});

test('selected data export rejects unknown columns and non-admin downloads', function () {
    Excel::fake();

    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $athlete = User::factory()->create([
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.data-export.download', [
            'datasets' => ['accounts'],
            'fields' => ['accounts' => ['password', 'two_factor_secret']],
        ]))
        ->assertSessionHasErrors('fields');

    $this->actingAs($athlete)
        ->get(route('admin.data-export.download', [
            'datasets' => ['accounts'],
            'fields' => ['accounts' => ['name']],
        ]))
        ->assertForbidden();
});

test('selected athletes export maps profile address without missing relation errors', function () {
    $branch = \App\Models\Branch::query()->create([
        'branch_name' => 'Export Athlete Branch',
        'location' => 'Malang',
    ]);
    $group = \App\Models\Group::query()->create([
        'group_name' => 'Export Athlete Group',
    ]);
    $athleteUser = User::factory()->create([
        'name' => 'Address Athlete',
        'email' => 'address-athlete@example.com',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
    ]);
    $athleteUser->profile()->updateOrCreate(
        ['user_id' => $athleteUser->id],
        ['address' => 'Jl. Soekarno Hatta No. 10, Malang'],
    );
    \App\Models\Athlete::query()->create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 168,
        'weight_kg' => 58,
        'geup' => 'GEUP_4',
    ]);

    $export = app(AdminDataExportService::class)->makeExport(
        'athletes',
        ['athlete_name', 'address'],
        [],
    );
    $records = $export->query()->get();

    expect($records)->toHaveCount(1)
        ->and($export->map($records->first()))->toBe([
            'Address Athlete',
            'Jl. Soekarno Hatta No. 10, Malang',
        ]);
});
