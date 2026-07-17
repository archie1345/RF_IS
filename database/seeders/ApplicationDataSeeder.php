<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ApplicationDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $password = Hash::make('12345678');

        $userIds = [];
        $parentIds = [];
        $coachIds = [];
        $athleteIds = [];

        $makeUser = function (string $name, string $email, string $role, string $gender, string $birthday, string $phone) use ($now, $password, &$userIds): int {
            $id = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => $now,
                'password' => $password,
                'gender' => $gender,
                'role' => $role,
                'bday' => $birthday,
                'phone' => $phone,
                'remember_token' => Str::random(10),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            $userIds[$email] = $id;

            return $id;
        };

        $adminUserId = $makeUser('Admin RFIS', 'admin@rfis.test', 'admin', 'MALE', '2005-06-13', '080000000001');

        $coachUserId1 = $makeUser('Budi Santoso', 'coach@rfis.test', 'coach', 'MALE', '1988-07-21', '080000000002');
        $coachUserId2 = $makeUser('Siti Rahma', 'coach2@rfis.test', 'coach', 'FEMALE', '1990-11-02', '080000000005');

        $parentUserId1 = $makeUser('Rina Putri', 'parent@rfis.test', 'parent', 'FEMALE', '1985-03-15', '080000000003');
        $parentUserId2 = $makeUser('Agus Pranoto', 'parent2@rfis.test', 'parent', 'MALE', '1982-10-19', '080000000006');
        $parentUserId3 = $makeUser('Maya Lestari', 'parent3@rfis.test', 'parent', 'FEMALE', '1987-04-28', '080000000007');

        $athleteUsers = [
            ['Adit Pratama', 'athlete@rfis.test', 'MALE', '2012-05-14', '080000000004'],
            ['Nadia Kirana', 'nadia@rfis.test', 'FEMALE', '2013-09-18', '080000000008'],
            ['Rafi Mahendra', 'rafi@rfis.test', 'MALE', '2011-01-08', '080000000009'],
            ['Citra Dewi', 'citra@rfis.test', 'FEMALE', '2014-02-22', '080000000010'],
            ['Bagas Saputra', 'bagas@rfis.test', 'MALE', '2010-12-11', '080000000011'],
            ['Keisha Amanda', 'keisha@rfis.test', 'FEMALE', '2012-08-03', '080000000012'],
            ['Dimas Arya', 'dimas@rfis.test', 'MALE', '2009-06-30', '080000000013'],
            ['Putri Anindya', 'putri@rfis.test', 'FEMALE', '2011-03-27', '080000000014'],
        ];

        $athleteUserIds = [];

        foreach ($athleteUsers as $athleteUser) {
            [$name, $email, $gender, $birthday, $phone] = $athleteUser;
            $athleteUserIds[$email] = $makeUser($name, $email, 'athlete', $gender, $birthday, $phone);
        }

        DB::table('password_reset_tokens')->insert([
            'email' => 'parent@rfis.test',
            'token' => hash('sha256', 'parent-reset-token'),
            'created_at' => $now,
        ]);

        DB::table('cache')->insert([
            'key' => 'rfis:settings:default_branch',
            'value' => serialize(['value' => 'Central Dojang']),
            'expiration' => $now->copy()->addHour()->timestamp,
        ]);

        DB::table('cache_locks')->insert([
            'key' => 'rfis:seed-lock',
            'owner' => 'database-seeder',
            'expiration' => $now->copy()->addMinutes(10)->timestamp,
        ]);

        $centralBranchId = DB::table('branches')->insertGetId([
            'branch_name' => 'Central Dojang',
            'location' => 'Hall A',
            'address' => 'Jl. Merdeka No. 10',
            'city' => 'Malang',
            'province' => 'Jawa Timur',
            'latitude' => -7.9666204,
            'longitude' => 112.6326321,
            'attendance_radius_meters' => 100,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], 'branch_id');

        $eastBranchId = DB::table('branches')->insertGetId([
            'branch_name' => 'East Dojang',
            'location' => 'Studio B',
            'address' => 'Jl. Danau Toba No. 21',
            'city' => 'Malang',
            'province' => 'Jawa Timur',
            'latitude' => -7.9419325,
            'longitude' => 112.6384232,
            'attendance_radius_meters' => 120,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], 'branch_id');

        $inactiveBranchId = DB::table('branches')->insertGetId([
            'branch_name' => 'Inactive Demo Dojang',
            'location' => 'Old Hall',
            'address' => 'Jl. Lama No. 1',
            'city' => 'Malang',
            'province' => 'Jawa Timur',
            'latitude' => -7.9500000,
            'longitude' => 112.6200000,
            'attendance_radius_meters' => 80,
            'timezone' => 'Asia/Jakarta',
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], 'branch_id');

        $parentIds['rina'] = (string) Str::lower(Str::ulid());
        $parentIds['agus'] = (string) Str::lower(Str::ulid());
        $parentIds['maya'] = (string) Str::lower(Str::ulid());

        DB::table('parents')->insert([
            [
                'parent_id' => $parentIds['rina'],
                'id' => $parentUserId1,
                'relation' => 'mother',
                'occupation' => 'Accountant',
                'notes' => 'Primary emergency contact.',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'parent_id' => $parentIds['agus'],
                'id' => $parentUserId2,
                'relation' => 'father',
                'occupation' => 'Engineer',
                'notes' => 'Available after office hours.',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'parent_id' => $parentIds['maya'],
                'id' => $parentUserId3,
                'relation' => 'mother',
                'occupation' => 'Teacher',
                'notes' => 'Prefers WhatsApp communication.',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        $coachIds['budi'] = (string) Str::lower(Str::ulid());
        $coachIds['siti'] = (string) Str::lower(Str::ulid());

        DB::table('coaches')->insert([
            [
                'coach_id' => $coachIds['budi'],
                'id' => $coachUserId1,
                'specialization' => 'Kyorugi and youth development',
                'bio' => 'National-level coach assigned to junior athletes.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'coach_id' => $coachIds['siti'],
                'id' => $coachUserId2,
                'specialization' => 'Poomsae and conditioning',
                'bio' => 'Focuses on technical form, flexibility, and competition readiness.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        $todayIso = $now->copy()->startOfDay()->isoWeekday();

        $groups = [
            'junior' => [
                'branch_id' => $centralBranchId,
                'coach_id' => null,
                'group_name' => 'Junior Sparring',
                'class_type' => 'reguler',
                'day_of_week' => $todayIso,
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
                'min_belt' => null,
                'description' => 'Fundamental technique and sparring preparation.',
                'is_active' => true,
            ],
            'cadet' => [
                'branch_id' => $centralBranchId,
                'coach_id' => null,
                'group_name' => 'Cadet Fundamentals',
                'class_type' => 'pemula',
                'day_of_week' => (($todayIso + 1 - 1) % 7) + 1,
                'start_time' => '15:00:00',
                'end_time' => '16:30:00',
                'min_belt' => 'GEUP_10',
                'description' => 'Beginner-focused footwork, stance, and discipline class.',
                'is_active' => true,
            ],
            'competition' => [
                'branch_id' => $eastBranchId,
                'coach_id' => null,
                'group_name' => 'Competition Team',
                'class_type' => 'prestasi',
                'day_of_week' => (($todayIso + 2 - 1) % 7) + 1,
                'start_time' => '19:00:00',
                'end_time' => '20:30:00',
                'min_belt' => 'GEUP_6',
                'description' => 'Athlete performance class for tournament preparation.',
                'is_active' => true,
            ],
            'private' => [
                'branch_id' => $eastBranchId,
                'coach_id' => $coachIds['siti'],
                'group_name' => 'Private Performance',
                'class_type' => 'private',
                'day_of_week' => (($todayIso + 3 - 1) % 7) + 1,
                'start_time' => '18:00:00',
                'end_time' => '19:00:00',
                'min_belt' => 'GEUP_8',
                'description' => 'One-on-one performance refinement.',
                'is_active' => true,
            ],
            'inactive' => [
                'branch_id' => $inactiveBranchId,
                'coach_id' => null,
                'group_name' => 'Inactive Demo Class',
                'class_type' => 'reguler',
                'day_of_week' => (($todayIso + 4 - 1) % 7) + 1,
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'min_belt' => null,
                'description' => 'Inactive class to test filters.',
                'is_active' => false,
            ],
        ];

        $groupIds = [];
        $scheduleIds = [];

        foreach ($groups as $key => $group) {
            $groupIds[$key] = DB::table('class_groups')->insertGetId([
                ...$group,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ], 'group_id');

            if ($group['is_active']) {
                $scheduleIds[$key] = DB::table('weekly_training_schedules')->insertGetId([
                    'title' => $group['group_name'],
                    'branch_id' => $group['branch_id'],
                    'group_id' => $groupIds[$key],
                    'dedicated_athlete_id' => null,
                    'coach_id' => $group['class_type'] === 'private' ? $group['coach_id'] : null,
                    'session_type' => $group['class_type'],
                    'day_of_week' => $group['day_of_week'],
                    'start_time' => $group['start_time'],
                    'end_time' => $group['end_time'],
                    'location' => $group['branch_id'] === $centralBranchId ? 'Hall A' : 'Studio B',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ], 'weekly_training_schedule_id');
            }
        }

        $athleteSeedRows = [
            ['Adit Pratama', 'athlete@rfis.test', 'junior', $parentIds['rina'], $centralBranchId, 150.50, 42.30, 'GEUP_8'],
            ['Nadia Kirana', 'nadia@rfis.test', 'junior', $parentIds['rina'], $centralBranchId, 147.00, 39.80, 'GEUP_9'],
            ['Rafi Mahendra', 'rafi@rfis.test', 'junior', $parentIds['agus'], $centralBranchId, 154.20, 45.10, 'GEUP_7'],
            ['Citra Dewi', 'citra@rfis.test', 'cadet', $parentIds['maya'], $centralBranchId, 139.40, 34.20, 'GEUP_10'],
            ['Bagas Saputra', 'bagas@rfis.test', 'cadet', $parentIds['agus'], $centralBranchId, 145.60, 38.90, 'GEUP_10'],
            ['Keisha Amanda', 'keisha@rfis.test', 'competition', $parentIds['maya'], $eastBranchId, 158.20, 48.60, 'GEUP_5'],
            ['Dimas Arya', 'dimas@rfis.test', 'competition', $parentIds['agus'], $eastBranchId, 164.00, 53.10, 'GEUP_4'],
            ['Putri Anindya', 'putri@rfis.test', 'private', $parentIds['rina'], $eastBranchId, 156.30, 47.40, 'GEUP_6'],
        ];

        foreach ($athleteSeedRows as $index => $row) {
            [$name, $email, $groupKey, $parentId, $branchId, $height, $weight, $geup] = $row;

            $athleteId = (string) Str::lower(Str::ulid());
            $athleteIds[$email] = $athleteId;

            DB::table('athletes')->insert([
                'athlete_id' => $athleteId,
                'id' => $athleteUserIds[$email],
                'group_id' => $groupIds[$groupKey],
                'parent_id' => $parentId,
                'branch_id' => $branchId,
                'height_cm' => $height,
                'weight_kg' => $weight,
                'nik_hash' => hash('sha256', 'DEMO-NIK-ATHLETE-'.($index + 1)),
                'nik_ciphertext' => Crypt::encryptString('DEMO-NIK-ATHLETE-'.($index + 1)),
                'bpjs_hash' => hash('sha256', 'DEMO-BPJS-ATHLETE-'.($index + 1)),
                'bpjs_ciphertext' => Crypt::encryptString('DEMO-BPJS-ATHLETE-'.($index + 1)),
                'alamat' => $branchId === $centralBranchId ? 'Jl. Merdeka No. 10, Malang' : 'Jl. Danau Toba No. 21, Malang',
                'geup' => $geup,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }

        $attendanceStatusPattern = ['PRESENT', 'PRESENT', 'PRESENT', 'PRESENT', 'ABSENT', 'PRESENT', 'PRESENT', 'ABSENT'];

        foreach ($scheduleIds as $groupKey => $scheduleId) {
            $group = $groups[$groupKey];

            for ($weekOffset = -7; $weekOffset <= 2; $weekOffset++) {
                $sessionDate = $now->copy()
                    ->startOfWeek()
                    ->addWeeks($weekOffset)
                    ->addDays(((int) $group['day_of_week']) - 1)
                    ->startOfDay();

                $isFuture = $sessionDate->gt($now->copy()->startOfDay());

                $trainingSessionId = DB::table('training_sessions')->insertGetId([
                    'weekly_training_schedule_id' => $scheduleId,
                    'coach_id' => $group['class_type'] === 'private' ? $group['coach_id'] : null,
                    'branch_id' => $group['branch_id'],
                    'group_id' => $groupIds[$groupKey],
                    'session_type' => $group['class_type'],
                    'dedicated_athlete_id' => null,
                    'title' => $group['group_name'],
                    'location' => $group['branch_id'] === $centralBranchId ? 'Hall A' : 'Studio B',
                    'session_date' => $sessionDate->toDateString(),
                    'start_time' => $group['start_time'],
                    'end_time' => $group['end_time'],
                    'status' => 'CONFIRMED',
                    'attendance_token_hash' => null,
                    'attendance_opens_at' => null,
                    'attendance_closes_at' => null,
                    'attendance_qr_generated_at' => null,
                    'attendance_qr_revoked_at' => null,
                    'created_at' => $sessionDate->copy()->subDays(2),
                    'updated_at' => $sessionDate->copy()->subDays(2),
                ], 'training_session_id');

                if (Schema::hasTable('training_session_coaches') && $group['class_type'] === 'private' && $group['coach_id']) {
                    DB::table('training_session_coaches')->insert([
                        'training_session_id' => $trainingSessionId,
                        'coach_id' => $group['coach_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $groupAthletes = collect($athleteSeedRows)
                    ->filter(fn ($row) => $row[2] === $groupKey)
                    ->values();

                foreach ($groupAthletes as $athleteIndex => $athleteRow) {
                    $email = $athleteRow[1];
                    $status = $isFuture
                        ? 'ABSENT'
                        : $attendanceStatusPattern[($athleteIndex + abs($weekOffset)) % count($attendanceStatusPattern)];

                    DB::table('athlete_attendance')->insert([
                        'athlete_id' => $athleteIds[$email],
                        'training_session_id' => $trainingSessionId,
                        'date' => $sessionDate->toDateString(),
                        'status' => $status,
                        'checked_in_at' => $status === 'PRESENT'
                            ? $sessionDate->copy()->setTimeFromTimeString($group['start_time'])->addMinutes(3)
                            : null,
                        'notes' => $isFuture ? 'Seeded future attendance placeholder.' : 'Seeded historical attendance for dashboard trends.',
                        'follow_up_owner' => $status === 'ABSENT' ? 'coach' : null,
                        'created_at' => $sessionDate,
                        'updated_at' => $sessionDate,
                        'deleted_at' => null,
                    ]);
                }
            }
        }

        $events = [
            [
                'e_name' => 'Surabaya Friendly Match',
                'e_date' => $now->copy()->subMonths(2)->toDateString(),
                'location' => 'GOR Surabaya',
                'level' => 'REGIONAL',
                'entry_fee' => 150000,
                'description' => 'Friendly sparring event for junior athletes.',
                'organizer' => 'Pengprov Taekwondo Jatim',
                'contact_info' => 'surabaya-open@rfis.test',
                'sponsors' => 'RFIS Sports',
                'status' => 'COMPLETED',
                'poster_url' => 'https://example.test/posters/surabaya-friendly.jpg',
            ],
            [
                'e_name' => 'Malang Poomsae Festival',
                'e_date' => $now->copy()->subWeeks(3)->toDateString(),
                'location' => 'GOR Ken Arok',
                'level' => 'REGIONAL',
                'entry_fee' => 100000,
                'description' => 'Local poomsae evaluation and ranking event.',
                'organizer' => 'Rhino Fighter',
                'contact_info' => 'event@rfis.test',
                'sponsors' => 'RFIS Foundation',
                'status' => 'COMPLETED',
                'poster_url' => 'https://example.test/posters/malang-poomsae.jpg',
            ],
            [
                'e_name' => 'Jakarta Open Championship',
                'e_date' => $now->copy()->addMonth()->toDateString(),
                'location' => 'GOR Soemantri',
                'level' => 'NATIONAL',
                'entry_fee' => 250000,
                'description' => 'National tournament for junior and senior athletes.',
                'organizer' => 'Pengprov Taekwondo DKI',
                'contact_info' => 'jakarta-open@rfis.test',
                'sponsors' => 'RFIS Sports',
                'status' => 'SCHEDULED',
                'poster_url' => 'https://example.test/posters/jakarta-open.jpg',
            ],
        ];

        $eventIds = [];

        foreach ($events as $event) {
            $eventIds[] = DB::table('events')->insertGetId([
                ...$event,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ], 'event_id');
        }

        $registrationRows = [
            ['athlete@rfis.test', 0, 'KYORUGI', 'CONFIRMED', 'BRONZE'],
            ['nadia@rfis.test', 0, 'KYORUGI', 'CONFIRMED', 'PARTICIPATED'],
            ['rafi@rfis.test', 0, 'KYORUGI', 'CONFIRMED', 'SILVER'],
            ['keisha@rfis.test', 1, 'POOMSAE', 'CONFIRMED', 'GOLD'],
            ['dimas@rfis.test', 1, 'POOMSAE', 'CONFIRMED', 'SILVER'],
            ['putri@rfis.test', 2, 'KYORUGI', 'PENDING', null],
            ['bagas@rfis.test', 2, 'KYORUGI', 'PENDING', null],
        ];

        foreach ($registrationRows as $row) {
            [$email, $eventIndex, $category, $status, $result] = $row;

            $registrationId = DB::table('event_registrations')->insertGetId([
                'athlete_id' => $athleteIds[$email],
                'event_id' => $eventIds[$eventIndex],
                'category' => $category,
                'registered_at' => $now->copy()->subDays(10),
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ], 'evrid');

            if ($result) {
                DB::table('event_results')->insert([
                    'athlete_id' => $athleteIds[$email],
                    'event_id' => $eventIds[$eventIndex],
                    'result' => $result,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            }

            if ($eventIndex === 2) {
                DB::table('payments')->insert([
                    'athlete_id' => $athleteIds[$email],
                    'billable_user_id' => $athleteUserIds[$email],
                    'payer_user_id' => null,
                    'bill_kind' => 'INVOICE',
                    'payment_type' => 'CHAMPIONSHIP',
                    'amount' => 250000,
                    'reference_id' => $registrationId,
                    'total_amount' => 250000,
                    'paid_amount' => 0,
                    'remaining_amount' => 250000,
                    'payment_date' => $now->toDateString(),
                    'status' => 'PENDING',
                    'proof_status' => 'NONE',
                    'notes' => 'Demo championship registration bill.',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            }
        }

        foreach ($athleteSeedRows as $index => $athleteRow) {
            [$name, $email] = $athleteRow;
            $athleteId = $athleteIds[$email];
            $billableUserId = $athleteUserIds[$email];

            DB::table('licenses')->insert([
                'id' => $billableUserId,
                'license_number' => 'LIC-RFIS-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'license_type' => 'BELT',
                'level' => $athleteRow[7],
                'issued_date' => $now->copy()->subMonths(3 + $index)->toDateString(),
                'expiry_date' => $now->copy()->addYear()->toDateString(),
                'issued_by' => 'RFIS Administration',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
                $paymentMonth = $now->copy()->subMonths($monthOffset)->startOfMonth();
                $baseAmount = 300000;
                $isCompleted = (($index + $monthOffset) % 5) !== 0;
                $isPartial = ! $isCompleted && (($index + $monthOffset) % 2 === 0);
                $paidAmount = $isCompleted ? $baseAmount : ($isPartial ? 150000 : 0);

                $paymentId = DB::table('payments')->insertGetId([
                    'athlete_id' => $athleteId,
                    'billable_user_id' => $billableUserId,
                    'payer_user_id' => $index % 3 === 0 ? $parentUserId1 : ($index % 3 === 1 ? $parentUserId2 : $parentUserId3),
                    'bill_kind' => 'INVOICE',
                    'payment_type' => 'TUITION',
                    'amount' => $baseAmount,
                    'reference_id' => (int) ($paymentMonth->format('Ym').str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)),
                    'total_amount' => $baseAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $baseAmount - $paidAmount,
                    'payment_date' => $paymentMonth->copy()->addDays(4)->toDateString(),
                    'status' => $paidAmount >= $baseAmount ? 'COMPLETED' : 'PENDING',
                    'proof_status' => 'NONE',
                    'notes' => $paidAmount >= $baseAmount
                        ? 'Seeded completed monthly tuition payment.'
                        : 'Seeded unpaid or partial monthly tuition bill.',
                    'created_at' => $paymentMonth,
                    'updated_at' => $paymentMonth,
                    'deleted_at' => null,
                ], 'payment_id');

                if ($paidAmount > 0) {
                    DB::table('payment_transactions')->insert([
                        'payment_id' => $paymentId,
                        'verified_by' => $adminUserId,
                        'amount' => $paidAmount,
                        'transaction_date' => $paymentMonth->copy()->addDays(5)->toDateString(),
                        'payment_method' => 'TRANSFER',
                        'transaction_type' => 'PAYMENT',
                        'notes' => 'Seeded payment transaction for finance trends.',
                        'proof_notes' => 'Synthetic demo proof note; no real document.',
                        'created_at' => $paymentMonth,
                        'updated_at' => $paymentMonth,
                        'deleted_at' => null,
                    ]);
                }
            }
        }

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => 'SeededJob',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => 'SeededJob'],
            ], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now->timestamp,
            'created_at' => $now->timestamp,
        ]);

        DB::table('job_batches')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'initial-seed-batch',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => '{}',
            'cancelled_at' => null,
            'created_at' => $now->timestamp,
            'finished_at' => $now->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => 'FailedSeededJob',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => 'FailedSeededJob'],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Seeded example failure for local testing.',
            'failed_at' => $now,
        ]);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $adminUserId,
            'name' => 'seeded-admin-token',
            'token' => hash('sha256', 'seeded-admin-token'),
            'abilities' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'expires_at' => $now->copy()->addMonth(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $adminUserId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'RFIS Seeder',
            'payload' => base64_encode(json_encode(['login_web_'.sha1('web') => $adminUserId], JSON_THROW_ON_ERROR)),
            'last_activity' => $now->timestamp,
        ]);

        $roleRows = [
            [$adminUserId, 'admin'],
            [$coachUserId1, 'coach'],
            [$coachUserId2, 'coach'],
            [$parentUserId1, 'parent'],
            [$parentUserId2, 'parent'],
            [$parentUserId3, 'parent'],
        ];

        foreach ($athleteUserIds as $athleteUserId) {
            $roleRows[] = [$athleteUserId, 'athlete'];
        }

        DB::table('user_role_assignments')->insert(
            collect($roleRows)->map(fn (array $row) => [
                'user_id' => $row[0],
                'role' => $row[1],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }
}