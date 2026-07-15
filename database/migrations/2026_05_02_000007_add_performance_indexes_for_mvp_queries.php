<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->index(['session_date', 'start_time'], 'training_sessions_schedule_idx');
            $table->index(['branch_id', 'group_id', 'session_date'], 'training_sessions_branch_group_date_idx');
            $table->index(['coach_id', 'session_date'], 'training_sessions_coach_date_idx');
            $table->index(['status', 'session_date'], 'training_sessions_status_date_idx');
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->index(['athlete_id', 'date'], 'athlete_attendance_athlete_date_idx');
            $table->index(['training_session_id', 'date', 'status'], 'athlete_attendance_session_date_status_idx');
            $table->index(['date', 'status'], 'athlete_attendance_date_status_idx');
            $table->index(['checked_in_at'], 'athlete_attendance_checked_in_at_idx');
        });

        Schema::table('coach_attendance', function (Blueprint $table) {
            $table->index(['training_session_id', 'status'], 'coach_attendance_session_status_idx');
            $table->index(['coach_id', 'status'], 'coach_attendance_coach_status_idx');
            $table->index(['checked_at'], 'coach_attendance_checked_at_idx');
        });

        Schema::table('training_session_coaches', function (Blueprint $table) {
            $table->index(['coach_id', 'training_session_id'], 'training_session_coaches_training_session_idx');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['actor_user_id', 'created_at'], 'activity_logs_actor_created_idx');
            $table->index(['context', 'created_at'], 'activity_logs_context_created_idx');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'activity_logs_subject_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['athlete_id', 'payment_date'], 'payments_athlete_payment_date_idx');
            $table->index(['status', 'payment_date'], 'payments_status_payment_date_idx');
            $table->index(['payment_type', 'payment_date'], 'payments_type_payment_date_idx');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->index(['payment_id', 'transaction_date'], 'payment_transactions_payment_date_idx');
            $table->index(['verified_by', 'transaction_date'], 'payment_transactions_verified_date_idx');
            $table->index(['transaction_type', 'transaction_date'], 'payment_transactions_type_date_idx');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->index(['event_id', 'status'], 'event_registrations_event_status_idx');
            $table->index(['athlete_id', 'status'], 'event_registrations_athlete_status_idx');
        });

        Schema::table('event_results', function (Blueprint $table) {
            $table->index(['event_id', 'result'], 'event_results_event_result_idx');
            $table->index(['athlete_id', 'event_id'], 'event_results_athlete_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_results', function (Blueprint $table) {
            $table->dropIndex('event_results_event_result_idx');
            $table->dropIndex('event_results_athlete_event_idx');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropIndex('event_registrations_event_status_idx');
            $table->dropIndex('event_registrations_athlete_status_idx');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('payment_transactions_payment_date_idx');
            $table->dropIndex('payment_transactions_verified_date_idx');
            $table->dropIndex('payment_transactions_type_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_athlete_payment_date_idx');
            $table->dropIndex('payments_status_payment_date_idx');
            $table->dropIndex('payments_type_payment_date_idx');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_actor_created_idx');
            $table->dropIndex('activity_logs_context_created_idx');
            $table->dropIndex('activity_logs_subject_created_idx');
        });

        Schema::table('training_session_coaches', function (Blueprint $table) {
            $table->dropIndex('training_session_coaches_training_session_idx');
        });

        Schema::table('coach_attendance', function (Blueprint $table) {
            $table->dropIndex('coach_attendance_session_status_idx');
            $table->dropIndex('coach_attendance_coach_status_idx');
            $table->dropIndex('coach_attendance_checked_at_idx');
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->dropIndex('athlete_attendance_athlete_date_idx');
            $table->dropIndex('athlete_attendance_session_date_status_idx');
            $table->dropIndex('athlete_attendance_date_status_idx');
            $table->dropIndex('athlete_attendance_checked_in_at_idx');
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropIndex('training_sessions_schedule_idx');
            $table->dropIndex('training_sessions_branch_group_date_idx');
            $table->dropIndex('training_sessions_coach_date_idx');
            $table->dropIndex('training_sessions_status_date_idx');
        });
    }
};
