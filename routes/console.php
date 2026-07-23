<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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

Artisan::command('app:database-audit', function () {
    $migrator = app('migrator');
    $repository = $migrator->getRepository();
    $migrationFiles = $migrator->getMigrationFiles(database_path('migrations'));
    $ranMigrations = $repository->repositoryExists() ? $repository->getRan() : [];
    $pendingMigrations = array_values(array_diff(array_keys($migrationFiles), $ranMigrations));

    $requirements = [
        'users' => ['id', 'email', 'role', 'deleted_at'],
        'user_role_assignments' => ['user_id', 'role'],
        'athletes' => ['athlete_id', 'id', 'branch_id', 'training_group_id'],
        'coaches' => ['coach_id', 'id', 'status'],
        'branches' => ['branch_id', 'branch_name', 'is_active'],
        'training_groups' => ['id', 'name', 'is_active'],
        'class_groups' => ['group_id', 'branch_id', 'training_group_id', 'coach_id', 'class_type', 'schedule_mode', 'single_session_date', 'is_active'],
        'class_group_private_athletes' => ['group_id', 'athlete_id'],
        'class_group_coaches' => ['group_id', 'coach_id'],
        'weekly_training_schedules' => ['weekly_training_schedule_id', 'group_id', 'coach_id', 'session_type', 'is_active'],
        'training_sessions' => ['training_session_id', 'weekly_training_schedule_id', 'group_id', 'coach_id', 'session_type', 'metadata', 'deleted_at'],
        'training_session_coaches' => ['training_session_id', 'coach_id'],
        'athlete_attendance' => ['training_session_id', 'athlete_id', 'status'],
        'payments' => ['payment_id', 'payment_type', 'status', 'total_amount', 'paid_amount', 'remaining_amount'],
        'announcements' => ['id', 'title', 'target_role', 'is_active'],
        'events' => ['event_id', 'name', 'status'],
        'event_registrations' => ['evrid', 'event_id', 'athlete_id', 'status'],
    ];

    $missingTables = [];
    $missingColumns = [];

    foreach ($requirements as $table => $columns) {
        if (! Schema::hasTable($table)) {
            $missingTables[] = $table;
            continue;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
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
Schedule::command('tuition:generate-monthly')->dailyAt('01:10');
Schedule::command('sessions:generate-from-weekly --days=14')->dailyAt('00:15');
