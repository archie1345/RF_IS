<?php

namespace App\Console\Commands;

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateWeeklyTrainingSessionsCommand extends Command
{
    protected $signature = 'sessions:generate-from-weekly {--from=} {--days=7}';

    protected $description = 'Generate dated training sessions from active weekly training schedules.';

    public function handle(GenerateWeeklyTrainingSessions $generator): int
    {
        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : now()->startOfWeek();
        $days = max((int) $this->option('days'), 1);
        $to = $from->copy()->addDays($days - 1)->endOfDay();

        $result = $generator->handle($from, $to);

        $this->info("Generated {$result['created']} weekly training sessions from {$result['from']} to {$result['to']}. Skipped {$result['skipped']} duplicates.");

        return self::SUCCESS;
    }
}
