<?php

use App\Models\Athlete;
use App\Models\BillingRule;
use App\Models\BillingSetting;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function createBillingAthlete(string $name, Branch $branch, Group $group): Athlete
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
    ]);

    return Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 160,
        'weight_kg' => 50,
        'geup' => 'GEUP_10',
    ]);
}

test('admin can open the billing settings workspace while non admin cannot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $athlete = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($admin)
        ->get(route('admin.billing-settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/BillingSettingsPage')
            ->has('setting')
            ->has('rules')
            ->has('metrics', 2));

    $this->actingAs($athlete)
        ->get(route('admin.billing-settings.index'))
        ->assertForbidden();
});

test('monthly billing uses the most specific rule and is idempotent', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $firstBranch = Branch::query()->create(['branch_name' => 'Central', 'is_active' => true]);
    $secondBranch = Branch::query()->create(['branch_name' => 'West', 'is_active' => true]);
    $firstGroup = Group::query()->create([
        'group_name' => 'Junior Sparring',
        'branch_id' => $firstBranch->branch_id,
        'is_active' => true,
    ]);
    $secondGroup = Group::query()->create([
        'group_name' => 'General Class',
        'branch_id' => $secondBranch->branch_id,
        'is_active' => true,
    ]);
    $specificAthlete = createBillingAthlete('Specific Athlete', $firstBranch, $firstGroup);
    $fallbackAthlete = createBillingAthlete('Fallback Athlete', $secondBranch, $secondGroup);

    BillingSetting::query()->updateOrCreate(
        ['name' => 'monthly_tuition'],
        [
            'invoice_day' => 1,
            'invoice_time' => '01:10:00',
            'default_amount' => 150000,
            'is_active' => true,
        ],
    );
    BillingRule::query()->create([
        'name' => 'Central tuition',
        'charge_kind' => BillingRule::KIND_MONTHLY,
        'payment_type' => 'TUITION',
        'amount' => 200000,
        'branch_id' => $firstBranch->branch_id,
        'due_days' => 10,
        'is_active' => true,
    ]);
    $specificRule = BillingRule::query()->create([
        'name' => 'Junior Sparring tuition',
        'charge_kind' => BillingRule::KIND_MONTHLY,
        'payment_type' => 'TUITION',
        'amount' => 275000,
        'branch_id' => $firstBranch->branch_id,
        'group_id' => $firstGroup->group_id,
        'due_days' => 7,
        'is_active' => true,
    ]);

    $payload = ['month' => '2026-08'];
    $this->actingAs($admin)
        ->post(route('admin.billing-settings.monthly.generate'), $payload)
        ->assertRedirect();

    expect(Payment::query()->count())->toBe(2);

    $specificPayment = Payment::query()->where('athlete_id', $specificAthlete->athlete_id)->firstOrFail();
    $fallbackPayment = Payment::query()->where('athlete_id', $fallbackAthlete->athlete_id)->firstOrFail();

    expect((float) $specificPayment->total_amount)->toBe(275000.0)
        ->and($specificPayment->billing_rule_id)->toBe($specificRule->id)
        ->and($specificPayment->due_date?->toDateString())->toBe('2026-08-08')
        ->and((float) $fallbackPayment->total_amount)->toBe(150000.0)
        ->and($fallbackPayment->billing_rule_id)->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.billing-settings.monthly.generate'), $payload)
        ->assertRedirect();

    expect(Payment::query()->count())->toBe(2);
});

test('one time billing rule can be issued once per athlete and issue date', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $branch = Branch::query()->create(['branch_name' => 'Central', 'is_active' => true]);
    $group = Group::query()->create([
        'group_name' => 'Competition Team',
        'branch_id' => $branch->branch_id,
        'is_active' => true,
    ]);
    $athlete = createBillingAthlete('Competition Athlete', $branch, $group);
    $rule = BillingRule::query()->create([
        'name' => 'Team jacket',
        'charge_kind' => BillingRule::KIND_ONE_TIME,
        'payment_type' => 'UNIFORM',
        'amount' => 350000,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'due_days' => 14,
        'is_active' => true,
    ]);

    $payload = ['issue_date' => '2026-08-15'];
    $this->actingAs($admin)
        ->post(route('admin.billing-rules.generate', $rule), $payload)
        ->assertRedirect();
    $this->actingAs($admin)
        ->post(route('admin.billing-rules.generate', $rule), $payload)
        ->assertRedirect();

    $payment = Payment::query()->sole();
    expect($payment->athlete_id)->toBe($athlete->athlete_id)
        ->and($payment->payment_type)->toBe('UNIFORM')
        ->and((float) $payment->total_amount)->toBe(350000.0)
        ->and($payment->billing_rule_id)->toBe($rule->id);
});

test('monthly rules with the same scope cannot overlap', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $branch = Branch::query()->create(['branch_name' => 'Overlap Branch', 'is_active' => true]);

    $base = [
        'name' => 'First branch rate',
        'charge_kind' => 'MONTHLY',
        'payment_type' => 'TUITION',
        'amount' => 150000,
        'branch_id' => $branch->branch_id,
        'group_id' => null,
        'due_days' => 14,
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-12-31',
        'is_active' => true,
        'notes' => null,
    ];

    $this->actingAs($admin)
        ->post(route('admin.billing-rules.store'), $base)
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.billing-rules.store'), [
            ...$base,
            'name' => 'Overlapping branch rate',
            'effective_from' => '2026-06-01',
            'effective_until' => '2027-01-31',
        ])
        ->assertSessionHasErrors('effective_from');

    expect(BillingRule::query()->count())->toBe(1);
});

test('scheduled tuition command waits until the configured local day and time', function () {
    $branch = Branch::query()->create(['branch_name' => 'Scheduled Branch', 'is_active' => true]);
    $group = Group::query()->create([
        'group_name' => 'Scheduled Group',
        'branch_id' => $branch->branch_id,
        'is_active' => true,
    ]);
    createBillingAthlete('Scheduled Athlete', $branch, $group);

    BillingSetting::query()->updateOrCreate(
        ['name' => 'monthly_tuition'],
        [
            'invoice_day' => 1,
            'invoice_time' => '01:10:00',
            'default_amount' => 150000,
            'is_active' => true,
        ],
    );

    $this->travelTo(Carbon::parse('2026-10-27 18:37:00', config('app.timezone')));
    $this->artisan('tuition:generate-monthly')
        ->expectsOutput('Monthly tuition generation is scheduled for day 1 at 01:10.')
        ->assertExitCode(0);

    expect(Payment::query()->count())->toBe(0);
});

test('scheduled tuition command creates monthly invoices once at the configured time', function () {
    $branch = Branch::query()->create(['branch_name' => 'Due Branch', 'is_active' => true]);
    $group = Group::query()->create([
        'group_name' => 'Due Group',
        'branch_id' => $branch->branch_id,
        'is_active' => true,
    ]);
    $athlete = createBillingAthlete('Due Athlete', $branch, $group);

    BillingSetting::query()->updateOrCreate(
        ['name' => 'monthly_tuition'],
        [
            'invoice_day' => 1,
            'invoice_time' => '01:10:00',
            'default_amount' => 165000,
            'is_active' => true,
        ],
    );

    $this->travelTo(Carbon::parse('2026-10-01 01:10:00', config('app.timezone')));
    $this->artisan('tuition:generate-monthly')
        ->expectsOutput('Generated 1 monthly tuition bills. Skipped 0 existing bills.')
        ->assertExitCode(0);
    $this->artisan('tuition:generate-monthly')
        ->expectsOutput('Generated 0 monthly tuition bills. Skipped 1 existing bills.')
        ->assertExitCode(0);

    $payment = Payment::query()->sole();
    expect($payment->athlete_id)->toBe($athlete->athlete_id)
        ->and((float) $payment->total_amount)->toBe(165000.0)
        ->and($payment->payment_date?->toDateString())->toBe('2026-10-01');
});

test('scheduled commands for session generation, activity log pruning, failure handling, and overlap protection work as configured', function () {
    $scheduleEvents = collect(\Illuminate\Support\Facades\Schedule::events());
    $tuitionEvent = $scheduleEvents->first(fn ($event) => str_contains((string) $event->command, 'tuition:generate-monthly'));
    $pruneEvent = $scheduleEvents->first(fn ($event) => str_contains((string) $event->command, 'activity-logs:prune --days=90'));
    $sessionEvent = $scheduleEvents->first(fn ($event) => str_contains((string) $event->command, 'sessions:generate-from-weekly --days=14'));

    expect($tuitionEvent)->not->toBeNull()
        ->and($tuitionEvent->withoutOverlapping)->toBeTrue()
        ->and($tuitionEvent->expression)->toBe('* * * * *')
        ->and($pruneEvent)->not->toBeNull()
        ->and($pruneEvent->expression)->toBe('0 2 * * *')
        ->and($sessionEvent)->not->toBeNull()
        ->and($sessionEvent->expression)->toBe('15 0 * * *');

    $this->travelTo(Carbon::parse('2026-10-15 02:00:00', config('app.timezone')));

    $oldLog = \App\Models\ActivityLog::query()->create([
        'action' => 'audit.old',
        'context' => 'test',
        'description' => 'Older than 90 days',
    ]);
    \Illuminate\Support\Facades\DB::table('activity_logs')
        ->where('id', $oldLog->id)
        ->update(['created_at' => now()->subDays(91), 'updated_at' => now()->subDays(91)]);

    $boundaryLog = \App\Models\ActivityLog::query()->create([
        'action' => 'audit.boundary',
        'context' => 'test',
        'description' => 'Within 90 days',
    ]);
    \Illuminate\Support\Facades\DB::table('activity_logs')
        ->where('id', $boundaryLog->id)
        ->update(['created_at' => now()->subDays(89), 'updated_at' => now()->subDays(89)]);

    $this->artisan('activity-logs:prune --days=90')
        ->expectsOutput('Pruned 1 activity log entries older than 90 days.')
        ->assertExitCode(0);

    $this->artisan('activity-logs:prune --days=90')
        ->expectsOutput('Pruned 0 activity log entries older than 90 days.')
        ->assertExitCode(0);

    expect(\App\Models\ActivityLog::query()->whereKey($oldLog->id)->exists())->toBeFalse()
        ->and(\App\Models\ActivityLog::query()->whereKey($boundaryLog->id)->exists())->toBeTrue();

    $coachUser = User::factory()->create(['role' => 'coach', 'account_status' => User::ACCOUNT_STATUS_ACTIVE]);
    $coach = \App\Models\Coach::query()->create(['id' => $coachUser->id, 'status' => 'active']);
    $branch = Branch::query()->create(['branch_name' => 'Scheduler Branch', 'is_active' => true]);
    $group = Group::query()->create([
        'group_name' => 'Scheduler Group',
        'branch_id' => $branch->branch_id,
        'coach_id' => $coach->coach_id,
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    \App\Models\WeeklyTrainingSchedule::query()->create([
        'title' => 'Active Weekly Slot',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'session_type' => 'reguler',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    \App\Models\WeeklyTrainingSchedule::query()->create([
        'title' => 'Inactive Weekly Slot',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'session_type' => 'reguler',
        'day_of_week' => 2,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => false,
    ]);

    $this->artisan('sessions:generate-from-weekly --from=2026-10-12 --days=14')
        ->expectsOutput('Generated 2 weekly training sessions from 2026-10-12 to 2026-10-25. Skipped 0 duplicates.')
        ->assertExitCode(0);

    $this->artisan('sessions:generate-from-weekly --from=2026-10-12 --days=14')
        ->expectsOutput('Generated 0 weekly training sessions from 2026-10-12 to 2026-10-25. Skipped 2 duplicates.')
        ->assertExitCode(0);

    expect(\App\Models\TrainingSession::query()->count())->toBe(2);

    $this->artisan('tuition:generate-monthly --month=2026-10 --amount=0 --force')
        ->assertExitCode(1);
});
