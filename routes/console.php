<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Schedule::command('activity-logs:prune --days=90')->dailyAt('02:00');
Schedule::command('tuition:generate-monthly')->dailyAt('01:10');
Schedule::command('sessions:generate-from-weekly --days=14')->dailyAt('00:15');
