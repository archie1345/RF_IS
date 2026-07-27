<?php

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\CoachAttendance;
use App\Models\Event;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserCertification;
use App\Models\UserRoleAssignment;
use Database\Seeders\FullSystemSimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a complete role and status simulation for the application', function () {
    $this->seed(FullSystemSimulationSeeder::class);

    expect(User::query()->whereIn('email', [
        'admin@rfis.test',
        'coach@rfis.test',
        'parent@rfis.test',
        'athlete@rfis.test',
        'multirole@rfis.test',
        'allroles@rfis.test',
    ])->count())->toBe(6);

    $allRoleUser = User::query()->where('email', 'allroles@rfis.test')->firstOrFail();
    expect(UserRoleAssignment::query()
        ->where('user_id', $allRoleUser->id)
        ->whereIn('role', ['admin', 'coach', 'parent', 'athlete'])
        ->count())->toBe(4);

    expect(Attendance::query()->where('status', 'PRESENT')->exists())->toBeTrue()
        ->and(Attendance::query()->where('status', 'LATE')->exists())->toBeTrue()
        ->and(Attendance::query()->where('status', 'EXCUSED')->exists())->toBeTrue()
        ->and(Attendance::query()->where('status', 'ABSENT')->exists())->toBeTrue();

    expect(CoachAttendance::query()->where('status', 'TEACH')->whereNotNull('checked_at')->exists())->toBeTrue()
        ->and(CoachAttendance::query()->where('status', 'NOT_TEACH')->exists())->toBeTrue();

    expect(TrainingSession::query()->where('title', 'Simulation Payroll Session A')->exists())->toBeTrue()
        ->and(TrainingSession::query()->where('title', 'Simulation Payroll Session B')->exists())->toBeTrue();

    foreach (['SCHEDULED', 'ONGOING', 'COMPLETED', 'CANCELED'] as $status) {
        expect(Event::query()->where('status', $status)->exists())->toBeTrue();
    }

    expect(Payment::query()->where('bill_kind', 'INVOICE')->where('remaining_amount', '>', 0)->exists())->toBeTrue()
        ->and(Payment::query()->where('paid_amount', '>', 0)->where('remaining_amount', '>', 0)->exists())->toBeTrue()
        ->and(Payment::query()->where('bill_kind', 'PAYROLL')->where('payroll_bonus_amount', '>', 0)->exists())->toBeTrue()
        ->and(Payment::query()->where('bill_kind', 'PAYROLL')->where('status', 'COMPLETED')->exists())->toBeTrue();

    foreach (['ALL', 'ADMIN', 'COACH', 'PARENT', 'ATHLETE'] as $target) {
        expect(Announcement::query()->where('target_role', $target)->exists())->toBeTrue();
    }

    expect(UserCertification::query()->whereDate('expires_at', '<', today())->exists())->toBeTrue();
});

it('keeps table row actions icon based and accessible', function () {
    $source = file_get_contents(resource_path('js/components/shared/ActionButtonsRow.vue'));

    expect($source)
        ->toContain('table-action-icon-only')
        ->toContain("target.setAttribute('aria-label', label)")
        ->toContain("target.setAttribute('title', label)")
        ->toContain('ClipboardPenLine')
        ->toContain('CircleDollarSign')
        ->toContain('Trash2')
        ->toContain('UserPlus');
});
