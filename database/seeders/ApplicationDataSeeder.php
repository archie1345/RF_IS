<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApplicationDataSeeder extends Seeder
{
    /**
     * Seed the application's database with sample data for all migrated tables.
     */
    public function run(): void
    {
        $now = now();
        
        $parentIdToken = (string) Str::lower(Str::ulid());
        $athleteIdToken = (string) Str::lower(Str::ulid());
        $coachIdToken = (string) Str::lower(Str::ulid());

        $adminUserId = DB::table('users')->insertGetId([
            'name' => 'Admin RFIS',
            'email' => 'admin@rfis.test',
            'email_verified_at' => $now,
            'password' => Hash::make('12345678'),
            'gender' => 'MALE',
            'role' => 'admin',
            'bday' => '2005-06-13',
            'phone' => '08813323088',
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $coachUserId = DB::table('users')->insertGetId([
            'name' => 'Budi Santoso',
            'email' => 'coach@rfis.test',
            'email_verified_at' => $now,
            'password' => Hash::make('12345678'),
            'gender' => 'MALE',
            'role' => 'coach',
            'bday' => '1988-07-21',
            'phone' => '081200000002',
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $parentUserId = DB::table('users')->insertGetId([
            'name' => 'Rina Putri',
            'email' => 'parent@rfis.test',
            'email_verified_at' => $now,
            'password' => Hash::make('12345678'),
            'gender' => 'FEMALE',
            'role' => 'parent',
            'bday' => '1985-03-15',
            'phone' => '081200000003',
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $athleteUserId = DB::table('users')->insertGetId([
            'name' => 'Adit Pratama',
            'email' => 'athlete@rfis.test',
            'email_verified_at' => $now,
            'password' => Hash::make('12345678'),
            'gender' => 'MALE',
            'role' => 'athlete',
            'bday' => '2012-05-14',
            'phone' => '081200000004',
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

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

        $branchId = DB::table('branches')->insertGetId([
            'branch_name' => 'Central Dojang',
            'location' => 'Jakarta Selatan',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], 'branch_id');

        $groupId = DB::table('class_groups')->insertGetId([
            'group_name' => 'Junior Sparring',
            'description' => 'Fundamental technique and sparring preparation.',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], 'group_id');

        DB::table('parents')->insert([
            'parent_id' => $parentIdToken,
            'id' => $parentUserId,
            'relation' => 'mother',
            'occupation' => 'Accountant',
            'notes' => 'Primary emergency contact.',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('coaches')->insert([
            'coach_id' => $coachIdToken,
            'id' => $coachUserId,
            'specialization' => 'Kyorugi and youth development',
            'bio' => 'National-level coach assigned to junior athletes.',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('coach_sessions')->insert([
            'coach_id' => $coachIdToken,
            'branch_id' => $branchId,
            'location' => 'Hall A',
            'session_date' => $now->copy()->addDays(1)->toDateString(),
            'start_time' => '16:00:00',
            'end_time' => '18:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('athletes')->insert([
            'athlete_id' => $athleteIdToken,
            'id' => $athleteUserId,
            'group_id' => $groupId,
            'parent_id' => $parentIdToken,
            'branch_id' => $branchId,
            'height_cm' => 150.50,
            'weight_kg' => 42.30,
            'nik_hash' => hash('sha256', '3174001205120001'),
            'nik_ciphertext' => Crypt::encryptString('3174001205120001'),
            'bpjs_hash' => hash('sha256', 'BPJS-00001'),
            'bpjs_ciphertext' => Crypt::encryptString('BPJS-00001'),
            'alamat' => 'Jl. Merdeka No. 10, Jakarta',
            'geup' => 'GEUP_8',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('athlete_attendance')->insert([
            'athlete_id' => $athleteIdToken, 
            'date' => $now->toDateString(),
            'status' => 'PRESENT',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $eventId = DB::table('events')->insertGetId([
            'e_name' => 'Jakarta Open Championship',
            'e_date' => $now->copy()->addMonth()->toDateString(),
            'location' => 'GOR Soemantri',
            'level' => 'REGIONAL',
            'entry_fee' => 250000,
            'description' => 'Regional tournament for junior and senior athletes.',
            'organizer' => 'Pengprov Taekwondo DKI',
            'contact_info' => 'event@rfis.test',
            'sponsors' => 'RFIS Sports',
            'status' => 'SCHEDULED',
            'poster_url' => 'https://example.test/posters/jakarta-open.jpg',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], 'event_id');

        DB::table('event_registrations')->insert([
            'athlete_id' => $athleteIdToken, 
            'event_id' => $eventId,
            'category' => 'KYORUGI',
            'registered_at' => $now,
            'status' => 'CONFIRMED',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('event_results')->insert([
            'athlete_id' => $athleteIdToken, 
            'event_id' => $eventId,
            'result' => 'PARTICIPATED',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('licenses')->insert([
            'id' => $athleteUserId,
            'license_number' => 'LIC-RFIS-0001',
            'license_type' => 'BELT',
            'level' => 'GEUP_8',
            'issued_date' => $now->copy()->subMonths(2)->toDateString(),
            'expiry_date' => $now->copy()->addYear()->toDateString(),
            'issued_by' => 'RFIS Administration',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $paymentId = DB::table('payments')->insertGetId([
            'athlete_id' => $athleteIdToken, 
            'payment_type' => 'TUITION',
            'amount' => 300000,
            'reference_id' => 100001,
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'remaining_amount' => 0,
            'payment_date' => $now->toDateString(),
            'status' => 'COMPLETED',
            'notes' => 'Monthly tuition payment.',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], 'payment_id');

        DB::table('payment_transactions')->insert([
            'payment_id' => $paymentId,
            'verified_by' => $adminUserId,
            'amount' => 300000,
            'transaction_date' => $now->toDateString(),
            'payment_method' => 'TRANSFER',
            'transaction_type' => 'PAYMENT',
            'notes' => 'Verified by finance admin.',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => 'SeededJob',
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
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
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                'data' => ['commandName' => 'FailedSeededJob'],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'Seeded example failure for local testing.',
            'failed_at' => $now,
        ]);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\Models\User',
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
            'payload' => base64_encode(json_encode(['login_web_' . sha1('web') => $adminUserId], JSON_THROW_ON_ERROR)),
            'last_activity' => $now->timestamp,
        ]);

        DB::table('user_role_assignments')->insert([
            [
                'user_id' => $adminUserId,
                'role' => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $coachUserId,
                'role' => 'coach',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $parentUserId,
                'role' => 'parent',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $athleteUserId,
                'role' => 'athlete',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}