<?php

use App\Exports\SelectedDataExport;
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
            'status' => User::ACCOUNT_STATUS_SUSPENDED,
        ]))
        ->assertOk();

    Excel::assertDownloaded('accounts_20260728_103000.xlsx', function (SelectedDataExport $export): bool {
        return $export->headings() === ['Name', 'Email', 'Account Status'];
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
        ->assertSessionHasErrors('fields.accounts.0');

    $this->actingAs($athlete)
        ->get(route('admin.data-export.download', [
            'datasets' => ['accounts'],
            'fields' => ['accounts' => ['name']],
        ]))
        ->assertForbidden();
});
