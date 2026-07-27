<?php

namespace Database\Seeders;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\CoachAttendance;
use App\Models\Event;
use App\Models\EventCoachRegistration;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Support\Domain\PaymentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FullSystemSimulationSeeder extends Seeder
{
    private const PASSWORD = '12345678';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('FullSystemSimulationSeeder is intended for local, testing, and staging environments only.');
        }

        $this->call(ApplicationDataSeeder::class);

        DB::transaction(function (): void {
            $this->seedPayrollSourceSessions();
            $this->seedAttendanceStatusMatrix();
            $this->seedCoachAttendanceStatusMatrix();
            $this->seedEventStatusMatrix();
            $this->seedFinanceStatusMatrix();
            $this->seedAnnouncementStatusMatrix();
            $this->seedProfileStatusMatrix();
        });

        $this->command?->info('Full RFIS simulation data is ready.');
        $this->command?->table(['Account', 'Password', 'Primary simulation use'], [
            ['admin@rfis.test', self::PASSWORD, 'Administration, finance, payroll, reports, settings'],
            ['coach@rfis.test', self::PASSWORD, 'Sessions, coach attendance, assigned athletes'],
            ['parent@rfis.test', self::PASSWORD, 'All linked children, schedules, attendance, invoices'],
            ['athlete@rfis.test', self::PASSWORD, 'Attendance, championships, profile, payments'],
            ['multirole@rfis.test', self::PASSWORD, 'Combined coach, parent, and athlete dashboard'],
            ['allroles@rfis.test', self::PASSWORD, 'Admin, coach, parent, and athlete access in one account'],
        ]);
    }

    private function seedPayrollSourceSessions(): void
    {
        $coachUser = User::query()->where('email', 'coach@rfis.test')->firstOrFail();
        $coach = Coach::query()->where('id', $coachUser->id)->firstOrFail();
        $group = Group::query()->where('group_name', 'Competition Team')->firstOrFail();
        $branch = Branch::query()->findOrFail($group->branch_id);
        $today = now(config('app.timezone', 'Asia/Jakarta'))->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $dates = array_map(
            fn ($date) => $date->isAfter($today) ? $today->copy() : $date,
            [$monthStart->copy()->addDay(), $monthStart->copy()->addDays(4)],
        );

        foreach ([
            ['Simulation Payroll Session A', '18:00:00', '19:30:00'],
            ['Simulation Payroll Session B', '19:00:00', '20:00:00'],
        ] as $index => [$title, $startTime, $endTime]) {
            $sessionDate = $dates[$index];
            $session = TrainingSession::query()->updateOrCreate(
                [
                    'title' => $title,
                    'session_date' => $sessionDate->toDateString(),
                    'coach_id' => $coach->coach_id,
                ],
                [
                    'weekly_training_schedule_id' => null,
                    'branch_id' => $branch->branch_id,
                    'group_id' => $group->group_id,
                    'session_type' => 'prestasi',
                    'dedicated_athlete_id' => null,
                    'location' => $branch->location,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => 'CONFIRMED',
                    'metadata' => ['simulation' => true, 'payroll_source' => true],
                ],
            );

            $session->assignedCoaches()->syncWithoutDetaching([$coach->coach_id]);
            app(InitializeSessionAttendance::class)->handle($session);

            CoachAttendance::query()->updateOrCreate(
                [
                    'training_session_id' => $session->training_session_id,
                    'coach_id' => $coach->coach_id,
                ],
                [
                    'status' => 'TEACH',
                    'checked_at' => Carbon::parse($sessionDate->toDateString().' '.$startTime)->addMinutes(2),
                ],
            );
        }
    }

    private function seedAttendanceStatusMatrix(): void
    {
        $statuses = ['PRESENT', 'LATE', 'EXCUSED', 'ABSENT'];
        $records = Attendance::query()
            ->with('trainingSession')
            ->whereHas('trainingSession', fn ($query) => $query->whereDate('session_date', '<=', today()))
            ->orderByDesc('date')
            ->orderBy('athlete_attendance_id')
            ->limit(count($statuses))
            ->get();

        foreach ($records as $index => $attendance) {
            $status = $statuses[$index];
            $session = $attendance->trainingSession;
            $checkedInAt = null;

            if ($session && in_array($status, ['PRESENT', 'LATE'], true)) {
                $checkedInAt = Carbon::parse(
                    Carbon::parse($session->session_date)->toDateString().' '.substr((string) $session->start_time, 0, 8),
                )->addMinutes($status === 'LATE' ? 20 : 3);
            }

            $attendance->update([
                'date' => $session?->session_date ?? today(),
                'status' => $status,
                'checked_in_at' => $checkedInAt,
                'notes' => 'Full-system simulation status: '.$status.'.',
                'follow_up_owner' => in_array($status, ['ABSENT', 'EXCUSED'], true) ? 'coach' : null,
            ]);
        }
    }

    private function seedCoachAttendanceStatusMatrix(): void
    {
        $coach = Coach::query()->whereHas('user', fn ($query) => $query->where('email', 'coach2@rfis.test'))->firstOrFail();
        $occupiedSessionIds = CoachAttendance::query()
            ->where('coach_id', $coach->coach_id)
            ->pluck('training_session_id');
        $session = TrainingSession::query()
            ->whereDate('session_date', '<=', today())
            ->where('status', '!=', 'CANCELED')
            ->when($occupiedSessionIds->isNotEmpty(), fn ($query) => $query->whereNotIn('training_session_id', $occupiedSessionIds))
            ->orderByDesc('session_date')
            ->first();

        if (! $session) {
            return;
        }

        $session->assignedCoaches()->syncWithoutDetaching([$coach->coach_id]);
        CoachAttendance::query()->updateOrCreate(
            [
                'training_session_id' => $session->training_session_id,
                'coach_id' => $coach->coach_id,
            ],
            [
                'status' => 'NOT_TEACH',
                'checked_at' => null,
            ],
        );
    }

    private function seedEventStatusMatrix(): void
    {
        $coach = Coach::query()->whereHas('user', fn ($query) => $query->where('email', 'coach@rfis.test'))->firstOrFail();
        $nadia = Athlete::query()->whereHas('user', fn ($query) => $query->where('email', 'nadia@rfis.test'))->firstOrFail();
        $rafi = Athlete::query()->whereHas('user', fn ($query) => $query->where('email', 'rafi@rfis.test'))->firstOrFail();

        $ongoing = Event::query()->updateOrCreate(
            ['e_name' => 'RFIS Simulation Ongoing Cup'],
            [
                'e_date' => today(),
                'registration_deadline' => now()->subDay(),
                'location' => 'Central Dojang',
                'level' => 'LOCAL',
                'entry_fee' => 175000,
                'max_slots' => 32,
                'description' => 'Ongoing event used to simulate roster and result management.',
                'organizer' => 'Rhino Fighter',
                'contact_info' => 'admin@rfis.test',
                'status' => 'ONGOING',
            ],
        );
        $canceled = Event::query()->updateOrCreate(
            ['e_name' => 'RFIS Simulation Canceled Cup'],
            [
                'e_date' => now()->addWeeks(2),
                'registration_deadline' => now()->addWeek(),
                'location' => 'East Dojang',
                'level' => 'REGIONAL',
                'entry_fee' => 200000,
                'max_slots' => 48,
                'description' => 'Canceled event used to verify archived and read-only states.',
                'organizer' => 'Rhino Fighter',
                'contact_info' => 'admin@rfis.test',
                'status' => 'CANCELED',
            ],
        );

        EventCoachRegistration::query()->updateOrCreate(
            ['event_id' => $ongoing->event_id, 'coach_id' => $coach->coach_id],
            ['role' => 'Head Coach'],
        );

        EventRegistration::query()->updateOrCreate(
            ['event_id' => $ongoing->event_id, 'athlete_id' => $nadia->athlete_id],
            [
                'category' => 'POOMSAE',
                'classification' => 'Prestasi',
                'class_name' => 'Cadet Putri',
                'division' => 'Individual',
                'team_contingent' => 'Rhino Fighter',
                'status' => 'CONFIRMED',
            ],
        );
        EventRegistration::query()->updateOrCreate(
            ['event_id' => $canceled->event_id, 'athlete_id' => $rafi->athlete_id],
            [
                'category' => 'KYORUGI',
                'classification' => 'Prestasi',
                'class_name' => 'Junior Putra',
                'division' => 'Under 55 kg',
                'team_contingent' => 'Rhino Fighter',
                'status' => 'PENDING',
            ],
        );

        UserAchievement::query()->updateOrCreate(
            [
                'user_id' => $nadia->id,
                'championship_name' => 'RFIS Simulation Historical Championship',
            ],
            [
                'medal' => 'BRONZE',
                'location' => 'Central Dojang',
                'event_date' => now()->subMonths(2),
                'class_name' => 'Cadet Putri',
                'division' => 'Individual',
                'category' => 'POOMSAE',
                'is_auto_recorded' => false,
                'notes' => 'Simulation achievement for profile and championship history views.',
            ],
        );
    }

    private function seedFinanceStatusMatrix(): void
    {
        $admin = User::query()->where('email', 'admin@rfis.test')->firstOrFail();
        $parent = User::query()->where('email', 'parent@rfis.test')->firstOrFail();
        $adit = Athlete::query()->whereHas('user', fn ($query) => $query->where('email', 'athlete@rfis.test'))->firstOrFail();
        $nadia = Athlete::query()->whereHas('user', fn ($query) => $query->where('email', 'nadia@rfis.test'))->firstOrFail();
        $coachUser = User::query()->where('email', 'coach2@rfis.test')->firstOrFail();

        Payment::query()->updateOrCreate(
            ['bill_kind' => 'INVOICE', 'reference_id' => 997000000000001],
            [
                'athlete_id' => $nadia->athlete_id,
                'billable_user_id' => $nadia->id,
                'payer_user_id' => $parent->id,
                'payment_type' => 'UNIFORM',
                'amount' => 450000,
                'total_amount' => 450000,
                'paid_amount' => 0,
                'remaining_amount' => 450000,
                'payment_date' => now()->subMonth()->startOfMonth(),
                'due_date' => now()->subWeek(),
                'collection_method' => 'TRANSFER',
                'status' => PaymentStatus::PENDING,
                'proof_status' => PaymentStatus::PROOF_NONE,
                'notes' => 'Simulation overdue invoice for reminders and finance filters.',
            ],
        );

        $partial = Payment::query()->updateOrCreate(
            ['bill_kind' => 'INVOICE', 'reference_id' => 997000000000002],
            [
                'athlete_id' => $adit->athlete_id,
                'billable_user_id' => $adit->id,
                'payer_user_id' => $parent->id,
                'payment_type' => 'LICENSE',
                'amount' => 600000,
                'total_amount' => 600000,
                'paid_amount' => 200000,
                'remaining_amount' => 400000,
                'payment_date' => now()->startOfMonth()->addDays(2),
                'due_date' => now()->endOfMonth(),
                'collection_method' => 'TRANSFER',
                'status' => PaymentStatus::PENDING,
                'proof_status' => PaymentStatus::PROOF_NONE,
                'notes' => 'Simulation partial UKT payment.',
            ],
        );
        PaymentTransaction::query()->firstOrCreate(
            [
                'payment_id' => $partial->payment_id,
                'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
                'notes' => 'Simulation first installment.',
            ],
            [
                'verified_by' => $admin->id,
                'amount' => 200000,
                'transaction_date' => now()->startOfMonth()->addDays(3),
                'payment_method' => 'TRANSFER',
            ],
        );

        $payroll = Payment::query()->updateOrCreate(
            ['bill_kind' => 'PAYROLL', 'reference_id' => 997000000000003],
            [
                'athlete_id' => null,
                'billable_user_id' => null,
                'payee_user_id' => $coachUser->id,
                'payer_user_id' => null,
                'payment_type' => 'OTHER',
                'payroll_period' => now()->startOfMonth()->toDateString(),
                'payroll_basis_type' => 'SESSION',
                'payroll_units' => 2,
                'payroll_rate' => 150000,
                'payroll_base_amount' => 300000,
                'payroll_bonus_amount' => 100000,
                'amount' => 400000,
                'total_amount' => 400000,
                'paid_amount' => 400000,
                'remaining_amount' => 0,
                'payment_date' => today(),
                'due_date' => today(),
                'collection_method' => 'TRANSFER',
                'status' => PaymentStatus::COMPLETED,
                'proof_status' => PaymentStatus::PROOF_APPROVED,
                'notes' => 'Simulation paid payroll with bonus and downloadable receipt.',
            ],
        );
        PaymentTransaction::query()->firstOrCreate(
            [
                'payment_id' => $payroll->payment_id,
                'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
                'notes' => 'Simulation payroll payment.',
            ],
            [
                'verified_by' => $admin->id,
                'amount' => 400000,
                'transaction_date' => now(),
                'payment_method' => 'TRANSFER',
            ],
        );
    }

    private function seedAnnouncementStatusMatrix(): void
    {
        $admin = User::query()->where('email', 'admin@rfis.test')->firstOrFail();

        foreach ([
            [
                'RFIS Simulation Scheduled Announcement',
                'This announcement becomes visible tomorrow.',
                'COACH',
                true,
                now()->addDay(),
                now()->addMonth(),
            ],
            [
                'RFIS Simulation Expired Announcement',
                'This announcement is retained for history but no longer shown on dashboards.',
                'PARENT',
                true,
                now()->subMonth(),
                now()->subDay(),
            ],
            [
                'RFIS Simulation Draft Announcement',
                'This inactive announcement demonstrates the draft state.',
                'ATHLETE',
                false,
                now(),
                now()->addMonth(),
            ],
        ] as [$title, $message, $targetRole, $active, $publishAt, $expireAt]) {
            Announcement::query()->updateOrCreate(
                ['title' => $title],
                [
                    'created_by' => $admin->id,
                    'message' => $message,
                    'target_role' => $targetRole,
                    'is_active' => $active,
                    'publish_at' => $publishAt,
                    'expire_at' => $expireAt,
                ],
            );
        }
    }

    private function seedProfileStatusMatrix(): void
    {
        $coach = User::query()->where('email', 'coach2@rfis.test')->firstOrFail();
        $athlete = User::query()->where('email', 'nadia@rfis.test')->firstOrFail();

        UserCertification::query()->updateOrCreate(
            [
                'user_id' => $coach->id,
                'cert_type' => 'TRAINER',
                'title' => 'Lisensi Pelatih Kedaluwarsa - Simulasi',
            ],
            [
                'issuer' => 'Pengprov Taekwondo Jawa Timur',
                'certified_at' => now()->subYears(2),
                'expires_at' => now()->subMonth(),
                'notes' => 'Expired certificate used to verify warning and renewal states.',
            ],
        );

        UserAchievement::query()->updateOrCreate(
            [
                'user_id' => $athlete->id,
                'championship_name' => 'RFIS Simulation Friendly Match',
            ],
            [
                'medal' => 'NONE',
                'location' => 'East Dojang',
                'event_date' => now()->subWeeks(3),
                'class_name' => 'Cadet Putri',
                'division' => 'Individual',
                'category' => 'POOMSAE',
                'is_auto_recorded' => false,
                'notes' => 'Participation record without a medal.',
            ],
        );
    }
}
