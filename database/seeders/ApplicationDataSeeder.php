<?php

namespace Database\Seeders;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserRoleAssignment;
use App\Models\WeeklyTrainingSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplicationDataSeeder extends Seeder
{
    private const PASSWORD = '12345678';

    public function run(): void
    {
        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $profiles = $this->seedRoleProfiles($users);
            $branches = $this->seedBranches();
            $trainingGroups = $this->seedTrainingGroups();
            $classes = $this->seedClasses($branches, $trainingGroups, $profiles['coaches']);
            $athletes = $this->seedAthletes($users, $profiles['parents'], $branches, $trainingGroups, $classes);

            $classes['private']->forceFill([
                'dedicated_athlete_id' => $athletes['putri']->athlete_id,
            ])->save();
            $classes['private']->privateAthletes()->sync([
                $athletes['putri']->athlete_id,
                $athletes['multi']->athlete_id,
            ]);

            $this->seedSchedulesAndSessions($branches, $classes, $profiles['coaches']);
            $this->seedAttendanceHistory();
            $this->seedPayments($users, $athletes);
            $this->seedChampionships($users, $athletes);
            $this->seedAnnouncements($users['admin']);
            $this->seedProfileRecords($users);
        });

        $this->command?->newLine();
        $this->command?->info('RFIS demo data seeded. All demo accounts use password: '.self::PASSWORD);
        $this->command?->table(
            ['Account', 'Roles'],
            [
                ['admin@rfis.test', 'Admin'],
                ['coach@rfis.test', 'Coach'],
                ['coach2@rfis.test', 'Coach'],
                ['parent@rfis.test', 'Parent'],
                ['athlete@rfis.test', 'Athlete'],
                ['multirole@rfis.test', 'Coach + Parent + Athlete'],
            ],
        );
    }

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        $definitions = [
            'admin' => ['Admin RFIS', 'admin@rfis.test', 'admin', 'MALE', '1990-01-10', '080000000001'],
            'coach1' => ['Budi Santoso', 'coach@rfis.test', 'coach', 'MALE', '1988-07-21', '080000000002'],
            'coach2' => ['Siti Rahma', 'coach2@rfis.test', 'coach', 'FEMALE', '1990-11-02', '080000000003'],
            'parent' => ['Rina Putri', 'parent@rfis.test', 'parent', 'FEMALE', '1985-03-15', '080000000004'],
            'athlete' => ['Adit Pratama', 'athlete@rfis.test', 'athlete', 'MALE', '2012-05-14', '080000000005'],
            'nadia' => ['Nadia Kirana', 'nadia@rfis.test', 'athlete', 'FEMALE', '2013-09-18', '080000000006'],
            'rafi' => ['Rafi Mahendra', 'rafi@rfis.test', 'athlete', 'MALE', '2011-01-08', '080000000007'],
            'putri' => ['Putri Anindya', 'putri@rfis.test', 'athlete', 'FEMALE', '2011-03-27', '080000000008'],
            'multi' => ['Dewi Multi Peran', 'multirole@rfis.test', 'coach', 'FEMALE', '1998-08-08', '080000000009'],
            'multiChild' => ['Raka Multi', 'child@rfis.test', 'athlete', 'MALE', '2014-04-12', '080000000010'],
        ];

        $users = [];

        foreach ($definitions as $key => [$name, $email, $role, $gender, $birthday, $phone]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => self::PASSWORD,
                    'gender' => $gender,
                    'role' => $role,
                    'bday' => $birthday,
                    'phone' => $phone,
                    'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                ],
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $users[$key] = $user;
        }

        $roleMap = [
            'admin' => ['admin'],
            'coach1' => ['coach'],
            'coach2' => ['coach'],
            'parent' => ['parent'],
            'athlete' => ['athlete'],
            'nadia' => ['athlete'],
            'rafi' => ['athlete'],
            'putri' => ['athlete'],
            'multi' => ['coach', 'parent', 'athlete'],
            'multiChild' => ['athlete'],
        ];

        foreach ($roleMap as $userKey => $roles) {
            foreach ($roles as $role) {
                UserRoleAssignment::query()->updateOrCreate([
                    'user_id' => $users[$userKey]->id,
                    'role' => $role,
                ]);
            }
        }

        return $users;
    }

    /** @return array{parents:array<string, ParentProfile>,coaches:array<string, Coach>} */
    private function seedRoleProfiles(array $users): array
    {
        $parents = [
            'parent' => ParentProfile::query()->updateOrCreate(
                ['id' => $users['parent']->id],
                ['relation' => 'mother', 'occupation' => 'Accountant', 'notes' => 'Primary demo parent.'],
            ),
            'multi' => ParentProfile::query()->updateOrCreate(
                ['id' => $users['multi']->id],
                ['relation' => 'guardian', 'occupation' => 'Coach', 'notes' => 'Multi-role parent profile.'],
            ),
        ];

        $coaches = [
            'budi' => Coach::query()->updateOrCreate(
                ['id' => $users['coach1']->id],
                ['specialization' => 'Kyorugi and youth development', 'bio' => 'Junior sparring coach.', 'status' => 'active'],
            ),
            'siti' => Coach::query()->updateOrCreate(
                ['id' => $users['coach2']->id],
                ['specialization' => 'Poomsae and conditioning', 'bio' => 'Competition preparation coach.', 'status' => 'active'],
            ),
            'multi' => Coach::query()->updateOrCreate(
                ['id' => $users['multi']->id],
                ['specialization' => 'Private training', 'bio' => 'Multi-role demo coach.', 'status' => 'active'],
            ),
        ];

        return compact('parents', 'coaches');
    }

    /** @return array<string, Branch> */
    private function seedBranches(): array
    {
        return [
            'central' => Branch::query()->updateOrCreate(
                ['branch_name' => 'Central Dojang'],
                [
                    'location' => 'Hall A',
                    'address' => 'Jl. Merdeka No. 10',
                    'city' => 'Malang',
                    'province' => 'Jawa Timur',
                    'latitude' => -7.9666204,
                    'longitude' => 112.6326321,
                    'attendance_radius_meters' => 100,
                    'timezone' => 'Asia/Jakarta',
                    'is_active' => true,
                ],
            ),
            'east' => Branch::query()->updateOrCreate(
                ['branch_name' => 'East Dojang'],
                [
                    'location' => 'Studio B',
                    'address' => 'Jl. Danau Toba No. 21',
                    'city' => 'Malang',
                    'province' => 'Jawa Timur',
                    'latitude' => -7.9419325,
                    'longitude' => 112.6384232,
                    'attendance_radius_meters' => 120,
                    'timezone' => 'Asia/Jakarta',
                    'is_active' => true,
                ],
            ),
        ];
    }

    /** @return array<string, TrainingGroup> */
    private function seedTrainingGroups(): array
    {
        return [
            'junior' => TrainingGroup::query()->updateOrCreate(
                ['name' => 'Junior'],
                ['description' => 'Junior athlete category.', 'is_active' => true],
            ),
            'competition' => TrainingGroup::query()->updateOrCreate(
                ['name' => 'Prestasi'],
                ['description' => 'Competition athlete category.', 'is_active' => true],
            ),
            'beginner' => TrainingGroup::query()->updateOrCreate(
                ['name' => 'Pemula'],
                ['description' => 'Beginner athlete category.', 'is_active' => true],
            ),
        ];
    }

    /** @return array<string, Group> */
    private function seedClasses(array $branches, array $trainingGroups, array $coaches): array
    {
        $today = now()->isoWeekday();
        $classes = [
            'junior' => Group::query()->updateOrCreate(
                ['group_name' => 'Junior Sparring'],
                [
                    'branch_id' => $branches['central']->branch_id,
                    'training_group_id' => $trainingGroups['junior']->id,
                    'coach_id' => $coaches['budi']->coach_id,
                    'class_type' => 'reguler',
                    'schedule_mode' => 'weekly',
                    'single_session_date' => null,
                    'day_of_week' => $today,
                    'start_time' => '16:00',
                    'end_time' => '18:00',
                    'min_belt' => null,
                    'description' => 'Fundamental technique and sparring preparation.',
                    'is_active' => true,
                ],
            ),
            'competition' => Group::query()->updateOrCreate(
                ['group_name' => 'Competition Team'],
                [
                    'branch_id' => $branches['east']->branch_id,
                    'training_group_id' => $trainingGroups['competition']->id,
                    'coach_id' => $coaches['siti']->coach_id,
                    'class_type' => 'prestasi',
                    'schedule_mode' => 'weekly',
                    'single_session_date' => null,
                    'day_of_week' => (($today + 2 - 1) % 7) + 1,
                    'start_time' => '19:00',
                    'end_time' => '20:30',
                    'min_belt' => 'GEUP_6',
                    'description' => 'Tournament preparation and conditioning.',
                    'is_active' => true,
                ],
            ),
            'private' => Group::query()->updateOrCreate(
                ['group_name' => 'Private Performance'],
                [
                    'branch_id' => $branches['east']->branch_id,
                    'training_group_id' => null,
                    'coach_id' => $coaches['siti']->coach_id,
                    'class_type' => 'private',
                    'schedule_mode' => 'one_day',
                    'single_session_date' => now()->addDays(3)->toDateString(),
                    'day_of_week' => now()->addDays(3)->isoWeekday(),
                    'start_time' => '18:00',
                    'end_time' => '19:00',
                    'min_belt' => null,
                    'description' => 'Private session demonstrating multiple athletes and coaches.',
                    'is_active' => true,
                ],
            ),
        ];

        $classes['junior']->coaches()->sync([$coaches['budi']->coach_id, $coaches['siti']->coach_id]);
        $classes['competition']->coaches()->sync([$coaches['siti']->coach_id, $coaches['multi']->coach_id]);
        $classes['private']->coaches()->sync([$coaches['siti']->coach_id, $coaches['multi']->coach_id]);

        return $classes;
    }

    /** @return array<string, Athlete> */
    private function seedAthletes(array $users, array $parents, array $branches, array $trainingGroups, array $classes): array
    {
        $definitions = [
            'adit' => [$users['athlete'], $classes['junior'], $trainingGroups['junior'], $parents['parent'], $branches['central'], 150.5, 42.3, 'GEUP_8'],
            'nadia' => [$users['nadia'], $classes['junior'], $trainingGroups['junior'], $parents['parent'], $branches['central'], 147.0, 39.8, 'GEUP_9'],
            'rafi' => [$users['rafi'], $classes['competition'], $trainingGroups['competition'], null, $branches['east'], 154.2, 45.1, 'GEUP_5'],
            'putri' => [$users['putri'], null, $trainingGroups['competition'], null, $branches['east'], 156.3, 47.4, 'GEUP_6'],
            'multi' => [$users['multi'], $classes['competition'], $trainingGroups['competition'], null, $branches['east'], 165.0, 55.0, 'DAN_1'],
            'multiChild' => [$users['multiChild'], $classes['junior'], $trainingGroups['junior'], $parents['multi'], $branches['central'], 140.0, 35.0, 'GEUP_10'],
        ];

        $athletes = [];
        foreach ($definitions as $key => [$user, $class, $trainingGroup, $parent, $branch, $height, $weight, $belt]) {
            $identifier = 'RFIS-DEMO-'.strtoupper($key);
            $athletes[$key] = Athlete::query()->updateOrCreate(
                ['id' => $user->id],
                [
                    'group_id' => $class?->group_id,
                    'training_group_id' => $trainingGroup->id,
                    'parent_id' => $parent?->parent_id,
                    'branch_id' => $branch->branch_id,
                    'height_cm' => $height,
                    'weight_kg' => $weight,
                    'nik_hash' => hash('sha256', $identifier.'-NIK'),
                    'nik_ciphertext' => $identifier.'-NIK',
                    'bpjs_hash' => hash('sha256', $identifier.'-BPJS'),
                    'bpjs_ciphertext' => $identifier.'-BPJS',
                    'alamat' => $branch->address,
                    'school_origin' => 'Sekolah Demo RFIS',
                    'geup' => $belt,
                ],
            );
        }

        return $athletes;
    }

    private function seedSchedulesAndSessions(array $branches, array $classes, array $coaches): void
    {
        foreach (['junior', 'competition'] as $key) {
            $class = $classes[$key];
            WeeklyTrainingSchedule::query()->updateOrCreate(
                ['group_id' => $class->group_id],
                [
                    'title' => $class->group_name,
                    'branch_id' => $class->branch_id,
                    'dedicated_athlete_id' => null,
                    'coach_id' => $class->coach_id,
                    'session_type' => $class->class_type,
                    'day_of_week' => $class->day_of_week,
                    'start_time' => $class->start_time,
                    'end_time' => $class->end_time,
                    'location' => $class->branch_id === $branches['central']->branch_id ? 'Hall A' : 'Studio B',
                    'is_active' => true,
                ],
            );
        }

        app(GenerateWeeklyTrainingSessions::class)->handle(
            now()->subWeeks(4)->startOfWeek(),
            now()->addWeeks(2)->endOfWeek(),
        );

        $privateClass = $classes['private']->fresh(['privateAthletes', 'coaches', 'branch']);
        $privateDate = Carbon::parse((string) $privateClass->single_session_date)->toDateString();
        $coachIds = $privateClass->coaches->pluck('coach_id')->map(fn ($id) => (string) $id)->values()->all();
        $privateSession = TrainingSession::query()->updateOrCreate(
            ['group_id' => $privateClass->group_id, 'session_date' => $privateDate],
            [
                'weekly_training_schedule_id' => null,
                'coach_id' => $coachIds[0] ?? $coaches['siti']->coach_id,
                'branch_id' => $privateClass->branch_id,
                'session_type' => 'private',
                'dedicated_athlete_id' => $privateClass->dedicated_athlete_id,
                'title' => $privateClass->group_name,
                'location' => $privateClass->branch?->location,
                'start_time' => $privateClass->start_time,
                'end_time' => $privateClass->end_time,
                'status' => 'CONFIRMED',
                'metadata' => ['class_schedule_mode' => 'one_day'],
            ],
        );
        $privateSession->assignedCoaches()->sync($coachIds);
        app(InitializeSessionAttendance::class)->handle($privateSession);
    }

    private function seedAttendanceHistory(): void
    {
        $sessions = TrainingSession::query()
            ->with('attendances')
            ->whereDate('session_date', '<', today())
            ->orderBy('session_date')
            ->get();

        foreach ($sessions as $sessionIndex => $session) {
            foreach ($session->attendances as $attendanceIndex => $attendance) {
                $present = (($sessionIndex + $attendanceIndex) % 5) !== 0;
                $attendance->update([
                    'status' => $present ? 'PRESENT' : 'ABSENT',
                    'checked_in_at' => $present
                        ? Carbon::parse((string) $session->session_date)->setTimeFromTimeString(substr((string) $session->start_time, 0, 8))->addMinutes(4)
                        : null,
                    'notes' => $present ? 'Seeded attendance history.' : 'Seeded absence requiring follow-up.',
                    'follow_up_owner' => $present ? null : 'coach',
                ]);
            }
        }
    }

    private function seedPayments(array $users, array $athletes): void
    {
        $admin = $users['admin'];
        $parentByAthlete = [
            'adit' => $users['parent']->id,
            'nadia' => $users['parent']->id,
            'multiChild' => $users['multi']->id,
        ];

        foreach ($athletes as $key => $athlete) {
            $completed = Payment::query()->updateOrCreate(
                ['payment_type' => 'TUITION', 'reference_id' => 'SEED-TUITION-'.$key.'-'.now()->subMonth()->format('Ym')],
                [
                    'athlete_id' => $athlete->athlete_id,
                    'billable_user_id' => $athlete->id,
                    'payer_user_id' => $parentByAthlete[$key] ?? $athlete->id,
                    'bill_kind' => 'INVOICE',
                    'amount' => 300000,
                    'total_amount' => 300000,
                    'paid_amount' => 300000,
                    'remaining_amount' => 0,
                    'payment_date' => now()->subMonth()->startOfMonth()->addDays(5),
                    'status' => 'COMPLETED',
                    'proof_status' => 'APPROVED',
                    'notes' => 'Seeded completed monthly tuition.',
                ],
            );

            PaymentTransaction::query()->updateOrCreate(
                ['payment_id' => $completed->payment_id, 'transaction_type' => PaymentTransaction::TYPE_PAYMENT],
                [
                    'verified_by' => $admin->id,
                    'amount' => 300000,
                    'transaction_date' => now()->subMonth()->startOfMonth()->addDays(6),
                    'payment_method' => 'TRANSFER',
                    'notes' => 'Seeded verified tuition transaction.',
                ],
            );

            Payment::query()->updateOrCreate(
                ['payment_type' => 'TUITION', 'reference_id' => 'SEED-TUITION-'.$key.'-'.now()->format('Ym')],
                [
                    'athlete_id' => $athlete->athlete_id,
                    'billable_user_id' => $athlete->id,
                    'payer_user_id' => $parentByAthlete[$key] ?? $athlete->id,
                    'bill_kind' => 'INVOICE',
                    'amount' => 300000,
                    'total_amount' => 300000,
                    'paid_amount' => $key === 'adit' ? 150000 : 0,
                    'remaining_amount' => $key === 'adit' ? 150000 : 300000,
                    'payment_date' => now()->startOfMonth()->addDays(5),
                    'status' => 'PENDING',
                    'proof_status' => 'NONE',
                    'notes' => 'Seeded current tuition requiring attention.',
                ],
            );
        }

        foreach (['coach1', 'coach2', 'multi'] as $index => $coachKey) {
            Payment::query()->updateOrCreate(
                ['bill_kind' => 'PAYROLL', 'reference_id' => 'SEED-PAYROLL-'.$coachKey.'-'.now()->format('Ym')],
                [
                    'payee_user_id' => $users[$coachKey]->id,
                    'payment_type' => 'OTHER',
                    'amount' => 750000,
                    'total_amount' => 750000,
                    'paid_amount' => $index === 0 ? 750000 : 0,
                    'remaining_amount' => $index === 0 ? 0 : 750000,
                    'payment_date' => now()->endOfMonth(),
                    'status' => $index === 0 ? 'COMPLETED' : 'PENDING',
                    'proof_status' => 'NONE',
                    'notes' => 'Seeded coach payroll record.',
                ],
            );
        }
    }

    private function seedChampionships(array $users, array $athletes): void
    {
        $completed = Event::query()->updateOrCreate(
            ['e_name' => 'Malang Poomsae Festival'],
            [
                'e_date' => now()->subMonth()->toDateString(),
                'location' => 'GOR Ken Arok',
                'level' => 'REGIONAL',
                'entry_fee' => 150000,
                'max_slots' => 80,
                'description' => 'Completed seeded event.',
                'organizer' => 'Rhino Fighter',
                'contact_info' => 'event@rfis.test',
                'status' => 'COMPLETED',
            ],
        );
        $upcoming = Event::query()->updateOrCreate(
            ['e_name' => 'Jakarta Open Championship'],
            [
                'e_date' => now()->addMonth()->toDateString(),
                'location' => 'GOR Soemantri',
                'level' => 'NATIONAL',
                'entry_fee' => 250000,
                'max_slots' => 120,
                'description' => 'Upcoming seeded championship.',
                'organizer' => 'Pengprov Taekwondo DKI',
                'contact_info' => 'jakarta-open@rfis.test',
                'status' => 'SCHEDULED',
            ],
        );

        $resultRegistration = EventRegistration::query()->updateOrCreate(
            ['athlete_id' => $athletes['adit']->athlete_id, 'event_id' => $completed->event_id],
            [
                'category' => 'POOMSAE',
                'classification' => 'Prestasi',
                'class_name' => 'Cadet Putra',
                'division' => 'Individual',
                'team_contingent' => 'Rhino Fighter',
                'status' => 'CONFIRMED',
                'result_medal' => 'SILVER',
                'result_class_name' => 'Cadet Putra',
                'result_division' => 'Individual',
                'result_category' => 'POOMSAE',
            ],
        );

        UserAchievement::query()->updateOrCreate(
            ['user_id' => $users['athlete']->id, 'event_registration_id' => $resultRegistration->evrid],
            [
                'event_id' => $completed->event_id,
                'championship_name' => $completed->e_name,
                'medal' => 'SILVER',
                'location' => $completed->location,
                'event_date' => $completed->e_date,
                'class_name' => 'Cadet Putra',
                'division' => 'Individual',
                'category' => 'POOMSAE',
                'is_auto_recorded' => true,
                'notes' => 'Seeded championship result.',
            ],
        );

        $pendingRegistration = EventRegistration::query()->updateOrCreate(
            ['athlete_id' => $athletes['putri']->athlete_id, 'event_id' => $upcoming->event_id],
            [
                'category' => 'KYORUGI',
                'classification' => 'Prestasi',
                'class_name' => 'Junior Putri',
                'division' => 'Under 49 kg',
                'team_contingent' => 'Rhino Fighter',
                'status' => 'PENDING',
            ],
        );

        Payment::query()->updateOrCreate(
            ['payment_type' => 'CHAMPIONSHIP', 'reference_id' => $pendingRegistration->evrid],
            [
                'athlete_id' => $athletes['putri']->athlete_id,
                'billable_user_id' => $athletes['putri']->id,
                'payer_user_id' => $athletes['putri']->id,
                'bill_kind' => 'INVOICE',
                'amount' => 250000,
                'total_amount' => 250000,
                'paid_amount' => 0,
                'remaining_amount' => 250000,
                'payment_date' => now(),
                'status' => 'PENDING',
                'proof_status' => 'NONE',
                'notes' => 'Seeded championship registration invoice.',
            ],
        );

        if (Schema::hasTable('event_results')) {
            DB::table('event_results')->updateOrInsert(
                ['athlete_id' => $athletes['adit']->athlete_id, 'event_id' => $completed->event_id],
                ['result' => 'SILVER', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
            );
        }
    }

    private function seedAnnouncements(User $admin): void
    {
        $rows = [
            ['Selamat datang di RFIS', 'Gunakan menu sesuai tugas Anda. Data demo tersedia untuk setiap peran.', 'ALL'],
            ['Briefing pelatih', 'Pelatih diminta memeriksa sesi dan kehadiran yang ditugaskan.', 'COACH'],
            ['Pengingat pembayaran', 'Periksa tagihan anak dan unggah bukti pembayaran melalui menu Keuangan.', 'PARENT'],
            ['Persiapan kejuaraan', 'Pastikan profil, berat badan, dan dokumen kompetisi sudah diperbarui.', 'ATHLETE'],
            ['Pemeriksaan operasional', 'Tinjau pembayaran tertunda, absensi, dan agenda mendatang.', 'ADMIN'],
        ];

        foreach ($rows as [$title, $message, $target]) {
            Announcement::query()->updateOrCreate(
                ['title' => $title],
                [
                    'created_by' => $admin->id,
                    'message' => $message,
                    'target_role' => $target,
                    'is_active' => true,
                    'publish_at' => now()->subHour(),
                    'expire_at' => now()->addMonths(2),
                ],
            );
        }
    }

    private function seedProfileRecords(array $users): void
    {
        UserCertification::query()->updateOrCreate(
            ['user_id' => $users['athlete']->id, 'title' => 'Sertifikat Geup 8'],
            [
                'cert_type' => 'BELT',
                'issuer' => 'Rhino Fighter',
                'certified_at' => now()->subMonths(6),
                'expires_at' => null,
                'notes' => 'Seeded athlete certification.',
            ],
        );

        UserCertification::query()->updateOrCreate(
            ['user_id' => $users['coach1']->id, 'title' => 'Lisensi Pelatih Daerah'],
            [
                'cert_type' => 'COACH_LICENSE',
                'issuer' => 'Pengprov Taekwondo Jawa Timur',
                'certified_at' => now()->subYear(),
                'expires_at' => now()->addYear(),
                'notes' => 'Seeded coach certification.',
            ],
        );

        UserAchievement::query()->updateOrCreate(
            ['user_id' => $users['multi']->id, 'championship_name' => 'Kejuaraan Internal RFIS'],
            [
                'medal' => 'GOLD',
                'location' => 'Central Dojang',
                'event_date' => now()->subMonths(3),
                'class_name' => 'Senior',
                'division' => 'Individual',
                'category' => 'POOMSAE',
                'is_auto_recorded' => false,
                'notes' => 'Manual achievement for the multi-role athlete demo.',
            ],
        );
    }
}
