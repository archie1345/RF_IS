<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'class_groups' => 'group_id',
            'training_groups' => 'id',
            'events' => 'event_id',
            'athlete_attendance' => 'athlete_attendance_id',
            'payments' => 'payment_id',
            'payment_transactions' => 'ptid',
            'billing_rules' => 'id',
            'branches' => 'branch_id',
            'announcements' => 'id',
            'user_certifications' => 'id',
            'user_achievements' => 'id',
            'user_files' => 'id',
            'training_sessions' => 'training_session_id',
            'weekly_training_schedules' => 'weekly_training_schedule_id',
            'coach_attendance' => 'coach_attendance_id',
            'event_registrations' => 'evrid',
        ];

        foreach ($tables as $table => $pk) {
            if (!Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $table) use ($pk) {
                    $table->uuid('uuid')->nullable()->after($pk);
                });

                $records = DB::table($table)->get();
                foreach ($records as $record) {
                    DB::table($table)->where($pk, $record->$pk)->update(['uuid' => (string) Str::uuid()]);
                }

                Schema::table($table, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable(false)->unique()->change();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'class_groups',
            'training_groups',
            'events',
            'athlete_attendance',
            'payments',
            'payment_transactions',
            'billing_rules',
            'branches',
            'announcements',
            'user_certifications',
            'user_achievements',
            'user_files',
            'training_sessions',
            'weekly_training_schedules',
            'coach_attendance',
            'event_registrations',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropUnique(['uuid']);
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
