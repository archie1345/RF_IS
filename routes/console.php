<?php

use App\Models\ActivityLog;
use App\Models\UserFile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('activity-logs:prune {--days=90}', function () {
    $days = max((int) $this->option('days'), 1);
    $deleted = ActivityLog::query()
        ->where('created_at', '<', now()->subDays($days))
        ->delete();

    $this->info("Pruned {$deleted} activity log entries older than {$days} days.");
})->purpose('Delete old activity logs to reduce storage usage');

Artisan::command('user-files:privatize {--dry-run}', function () {
    if (! Schema::hasTable('user_files') || ! Schema::hasColumn('user_files', 'disk')) {
        $this->error('The user_files.disk column is missing. Run migrations first.');

        return 1;
    }

    $dryRun = (bool) $this->option('dry-run');
    $migrated = 0;
    $missing = 0;
    $failed = 0;

    UserFile::query()
        ->where(fn ($query) => $query->whereNull('disk')->orWhere('disk', UserFile::DISK_PUBLIC))
        ->orderBy('id')
        ->eachById(function (UserFile $file) use ($dryRun, &$migrated, &$missing, &$failed): void {
            $oldPath = (string) $file->file_path;
            if ($oldPath === '' || ! Storage::disk(UserFile::DISK_PUBLIC)->exists($oldPath)) {
                $missing++;
                $this->warn("Missing public file for user_files.id={$file->id}: {$oldPath}");

                return;
            }

            $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
            $newPath = 'user-files/'.$file->user_id.'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');

            if ($dryRun) {
                $migrated++;
                $this->line("Would move {$oldPath} to private:{$newPath}");

                return;
            }

            $stream = Storage::disk(UserFile::DISK_PUBLIC)->readStream($oldPath);
            if (! is_resource($stream)) {
                $failed++;
                $this->error("Unable to read public file for user_files.id={$file->id}");

                return;
            }

            try {
                $stored = Storage::disk(UserFile::DISK_PRIVATE)->put($newPath, $stream);
                if (! $stored) {
                    $failed++;
                    $this->error("Unable to write private file for user_files.id={$file->id}");

                    return;
                }

                DB::transaction(function () use ($file, $newPath): void {
                    UserFile::query()
                        ->whereKey($file->id)
                        ->lockForUpdate()
                        ->update([
                            'file_path' => $newPath,
                            'disk' => UserFile::DISK_PRIVATE,
                        ]);
                });

                Storage::disk(UserFile::DISK_PUBLIC)->delete($oldPath);
                $migrated++;
            } catch (Throwable $exception) {
                Storage::disk(UserFile::DISK_PRIVATE)->delete($newPath);
                $failed++;
                report($exception);
                $this->error("Failed to migrate user_files.id={$file->id}: {$exception->getMessage()}");
            } finally {
                fclose($stream);
            }
        });

    $this->newLine();
    $this->info(($dryRun ? 'Eligible' : 'Migrated').": {$migrated}");
    $this->line("Missing source files: {$missing}");
    $this->line("Failed: {$failed}");

    return $failed === 0 ? 0 : 1;
})->purpose('Move historical public certification and achievement files into private storage');

Artisan::command('app:database-audit', function () {
    $migrator = app('migrator');
    $repository = $migrator->getRepository();
    $migrationFiles = $migrator->getMigrationFiles(database_path('migrations'));
    $ranMigrations = $repository->repositoryExists() ? $repository->getRan() : [];
    $pendingMigrations = array_values(array_diff(array_keys($migrationFiles), $ranMigrations));

    $requirements = [
        'users' => ['id', 'email', 'role', 'deleted_at'],
        'user_role_assignments' => ['user_id', 'role'],
        'user_files' => ['id', 'user_id', 'file_path', 'disk', 'mime_type', 'size_bytes'],
        'athletes' => ['athlete_id', 'member_number', 'joined_at', 'id', 'branch_id', 'group_id', 'training_group_id'],
        'member_number_sequences' => ['joined_on', 'last_sequence'],
        'coaches' => ['coach_id', 'id', 'status'],
        'branches' => ['branch_id', 'branch_name', 'is_active'],
        'training_groups' => ['id', 'name', 'is_active'],
        'class_groups' => ['group_id', 'branch_id', 'training_group_id', 'coach_id', 'class_type', 'schedule_mode', 'single_session_date', 'is_active'],
        'class_group_private_athletes' => ['group_id', 'athlete_id'],
        'class_group_coaches' => ['group_id', 'coach_id'],
        'weekly_training_schedules' => ['weekly_training_schedule_id', 'group_id', 'coach_id', 'session_type', 'is_active'],
        'training_sessions' => [
            'training_session_id', 'weekly_training_schedule_id', 'group_id', 'coach_id', 'session_type', 'metadata',
            'attendance_token_hash', 'attendance_qr_token', 'attendance_opens_at', 'attendance_closes_at',
            'attendance_qr_generated_at', 'attendance_qr_revoked_at', 'deleted_at',
        ],
        'training_session_coaches' => ['training_session_id', 'coach_id'],
        'athlete_attendance' => ['training_session_id', 'athlete_id', 'status'],
        'billing_settings' => ['name', 'invoice_day', 'invoice_time', 'default_amount', 'is_active'],
        'billing_rules' => [
            'id', 'name', 'charge_kind', 'payment_type', 'amount', 'branch_id', 'group_id',
            'due_days', 'effective_from', 'effective_until', 'is_active', 'deleted_at',
        ],
        'message_templates' => ['id', 'key', 'body'],
        'payments' => [
            'payment_id', 'invoice_number', 'bill_kind', 'payment_type', 'status', 'proof_status',
            'billing_rule_id', 'billing_run_key', 'total_amount', 'paid_amount', 'remaining_amount',
            'payment_date', 'due_date', 'collection_method', 'proof_path', 'proof_disk',
        ],
        'payment_transactions' => [
            'ptid', 'payment_id', 'verified_by', 'amount', 'transaction_date', 'payment_method',
            'transaction_type', 'proof_path', 'proof_disk', 'proof_notes',
        ],
        'invoice_templates' => [
            'name', 'company_name', 'payment_notes', 'qris_enabled', 'qris_label',
            'qris_instructions', 'qris_image_path',
        ],
        'announcements' => ['id', 'title', 'target_role', 'is_active'],
        'events' => ['event_id', 'e_name', 'status'],
        'event_registrations' => [
            'evrid', 'event_id', 'athlete_id', 'status', 'result_medal',
            'result_class_name', 'result_division', 'result_category',
        ],
    ];

    $missingTables = [];
    $missingColumns = [];

    foreach ($requirements as $table => $columns) {
        if (Schema::hasTable($table) === false) {
            $missingTables[] = $table;

            continue;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column) === false) {
                $missingColumns[] = "{$table}.{$column}";
            }
        }
    }

    $this->newLine();
    $this->info('Database schema audit');
    $this->line('Connection: '.config('database.default'));
    $this->line('Database: '.(config('database.connections.'.config('database.default').'.database') ?? '-'));
    $this->line('Migration files: '.count($migrationFiles));
    $this->line('Applied migrations: '.count($ranMigrations));

    if ($pendingMigrations !== []) {
        $this->warn('Pending migrations:');
        foreach ($pendingMigrations as $migration) {
            $this->line("  - {$migration}");
        }
    } else {
        $this->info('No pending migrations.');
    }

    if ($missingTables !== []) {
        $this->error('Missing tables:');
        foreach ($missingTables as $table) {
            $this->line("  - {$table}");
        }
    }

    if ($missingColumns !== []) {
        $this->error('Missing columns:');
        foreach ($missingColumns as $column) {
            $this->line("  - {$column}");
        }
    }

    if ($pendingMigrations === [] && $missingTables === [] && $missingColumns === []) {
        $this->newLine();
        $this->info('Database schema is aligned with the current application requirements.');

        return 0;
    }

    $this->newLine();
    $this->warn('Run php artisan migrate, then execute this audit again. Do not rebuild the database unless migrations cannot repair it.');

    return 1;
})->purpose('Report pending migrations and missing critical application tables or columns');

Schedule::command('activity-logs:prune --days=90')->dailyAt('02:00');
Schedule::command('tuition:generate-monthly')->everyMinute()->withoutOverlapping();
Schedule::command('sessions:generate-from-weekly --days=14')->dailyAt('00:15');
