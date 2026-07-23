<?php

namespace Database\Seeders;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\Athlete;
use App\Models\Coach;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AllRoleDemoSeeder extends Seeder
{
    private const PASSWORD = '12345678';

    public function run(): void
    {
        DB::transaction(function (): void {
            $allRoleUser = $this->seedUser(
                'Pengguna Semua Peran',
                'allroles@rfis.test',
                ['admin', 'coach', 'parent', 'athlete'],
                'FEMALE',
                '1995-05-20',
                '080000000099',
            );
            $childUser = $this->seedUser(
                'Anak Semua Peran',
                'allroles.child@rfis.test',
                ['athlete'],
                'MALE',
                '2014-09-12',
                '080000000098',
            );

            $parent = ParentProfile::query()->updateOrCreate(
                ['id' => $allRoleUser->id],
                [
                    'relation' => 'guardian',
                    'occupation' => 'Administrator and coach',
                    'notes' => 'Demo parent profile for the account with every application role.',
                ],
            );
            $coach = Coach::query()->updateOrCreate(
                ['id' => $allRoleUser->id],
                [
                    'specialization' => 'Operations, kyorugi, and athlete development',
                    'bio' => 'Demo coach profile for testing the complete multi-role workflow.',
                    'status' => 'active',
                ],
            );

            $competitionClass = Group::query()->where('group_name', 'Competition Team')->firstOrFail();
            $juniorClass = Group::query()->where('group_name', 'Junior Sparring')->firstOrFail();
            $privateClass = Group::query()->where('group_name', 'Private Performance')->firstOrFail();

            $allRoleAthlete = Athlete::query()->updateOrCreate(
                ['id' => $allRoleUser->id],
                [
                    'joined_at' => '2023-03-01',
                    'group_id' => $competitionClass->group_id,
                    'training_group_id' => $competitionClass->training_group_id,
                    'parent_id' => null,
                    'branch_id' => $competitionClass->branch_id,
                    'height_cm' => 165.00,
                    'weight_kg' => 55.00,
                    'nik_hash' => hash('sha256', 'RFIS-ALL-ROLE-NIK'),
                    'nik_ciphertext' => 'RFIS-ALL-ROLE-NIK',
                    'bpjs_hash' => hash('sha256', 'RFIS-ALL-ROLE-BPJS'),
                    'bpjs_ciphertext' => 'RFIS-ALL-ROLE-BPJS',
                    'alamat' => 'Jl. Demo Semua Peran, Malang',
                    'school_origin' => 'RFIS Senior Academy',
                    'geup' => 'DAN',
                ],
            );
            $childAthlete = Athlete::query()->updateOrCreate(
                ['id' => $childUser->id],
                [
                    'joined_at' => '2026-07-24',
                    'group_id' => $juniorClass->group_id,
                    'training_group_id' => $juniorClass->training_group_id,
                    'parent_id' => $parent->parent_id,
                    'branch_id' => $juniorClass->branch_id,
                    'height_cm' => 142.00,
                    'weight_kg' => 36.00,
                    'nik_hash' => hash('sha256', 'RFIS-ALL-ROLE-CHILD-NIK'),
                    'nik_ciphertext' => 'RFIS-ALL-ROLE-CHILD-NIK',
                    'bpjs_hash' => hash('sha256', 'RFIS-ALL-ROLE-CHILD-BPJS'),
                    'bpjs_ciphertext' => 'RFIS-ALL-ROLE-CHILD-BPJS',
                    'alamat' => 'Jl. Demo Semua Peran, Malang',
                    'school_origin' => 'RFIS Junior Academy',
                    'geup' => 'GEUP_9',
                ],
            );

            foreach ([$competitionClass, $privateClass] as $class) {
                $class->coaches()->syncWithoutDetaching([$coach->coach_id]);
                TrainingSession::query()
                    ->where('group_id', $class->group_id)
                    ->get()
                    ->each(fn (TrainingSession $session) => $session->assignedCoaches()->syncWithoutDetaching([$coach->coach_id]));
            }

            TrainingSession::query()
                ->whereIn('group_id', [$competitionClass->group_id, $juniorClass->group_id])
                ->get()
                ->each(fn (TrainingSession $session) => app(InitializeSessionAttendance::class)->handle($session));

            $this->seedInvoices($allRoleUser, $allRoleAthlete, $childAthlete);
            $this->seedPayroll($allRoleUser);
            $this->seedCompetitionRecord($allRoleUser, $allRoleAthlete);
            $this->seedProfileRecords($allRoleUser);
        });

        $this->command?->info('All-role demo account: allroles@rfis.test / '.self::PASSWORD);
    }

    /** @param array<int, string> $roles */
    private function seedUser(
        string $name,
        string $email,
        array $roles,
        string $gender,
        string $birthday,
        string $phone,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'gender' => $gender,
                'role' => $roles[0],
                'bday' => $birthday,
                'phone' => $phone,
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            ],
        );
        $user->forceFill(['email_verified_at' => now()])->save();

        foreach ($roles as $role) {
            UserRoleAssignment::query()->updateOrCreate([
                'user_id' => $user->id,
                'role' => $role,
            ]);
        }

        return $user;
    }

    private function seedInvoices(User $payer, Athlete $member, Athlete $child): void
    {
        foreach ([
            [$member, $payer->id, 990000000000001, 300000, 300000, 'COMPLETED'],
            [$child, $payer->id, 990000000000002, 300000, 0, 'PENDING'],
        ] as [$athlete, $payerUserId, $reference, $total, $paid, $status]) {
            Payment::query()->updateOrCreate(
                ['bill_kind' => 'INVOICE', 'reference_id' => $reference],
                [
                    'athlete_id' => $athlete->athlete_id,
                    'billable_user_id' => $athlete->id,
                    'payer_user_id' => $payerUserId,
                    'payment_type' => 'TUITION',
                    'amount' => $total,
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'remaining_amount' => $total - $paid,
                    'payment_date' => now()->startOfMonth()->addDays(5),
                    'status' => $status,
                    'proof_status' => $status === 'COMPLETED' ? 'APPROVED' : 'NONE',
                    'notes' => 'Seeded invoice for complete all-role workflow testing.',
                ],
            );
        }
    }

    private function seedPayroll(User $allRoleUser): void
    {
        Payment::query()->updateOrCreate(
            ['bill_kind' => 'PAYROLL', 'reference_id' => 990000000000003],
            [
                'athlete_id' => null,
                'billable_user_id' => null,
                'payee_user_id' => $allRoleUser->id,
                'payer_user_id' => null,
                'payment_type' => 'OTHER',
                'amount' => 750000,
                'total_amount' => 750000,
                'paid_amount' => 0,
                'remaining_amount' => 750000,
                'payment_date' => now()->endOfMonth(),
                'status' => 'PENDING',
                'proof_status' => 'NONE',
                'notes' => 'Seeded payroll for the all-role coach context.',
            ],
        );
    }

    private function seedCompetitionRecord(User $user, Athlete $athlete): void
    {
        $event = Event::query()->where('status', 'SCHEDULED')->orderBy('e_date')->first();
        if (! $event) {
            return;
        }

        EventRegistration::query()->updateOrCreate(
            ['athlete_id' => $athlete->athlete_id, 'event_id' => $event->event_id],
            [
                'category' => 'POOMSAE',
                'classification' => 'Prestasi',
                'class_name' => 'Senior',
                'division' => 'Individual',
                'team_contingent' => 'Rhino Fighter',
                'status' => 'CONFIRMED',
            ],
        );

        UserAchievement::query()->updateOrCreate(
            ['user_id' => $user->id, 'championship_name' => 'All-Role Internal Championship'],
            [
                'medal' => 'GOLD',
                'location' => 'Central Dojang',
                'event_date' => now()->subMonths(2),
                'class_name' => 'Senior',
                'division' => 'Individual',
                'category' => 'POOMSAE',
                'is_auto_recorded' => false,
                'notes' => 'Seeded achievement for the all-role athlete context.',
            ],
        );
    }

    private function seedProfileRecords(User $user): void
    {
        UserCertification::query()->updateOrCreate(
            ['user_id' => $user->id, 'cert_type' => 'TRAINER', 'title' => 'Lisensi Pelatih Semua Peran'],
            [
                'issuer' => 'Pengprov Taekwondo Jawa Timur',
                'certified_at' => now()->subYear(),
                'expires_at' => now()->addYear(),
                'notes' => 'Seeded trainer certification for all-role testing.',
            ],
        );
    }
}
