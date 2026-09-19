<?php

namespace App\Console\Commands;

use App\Services\Notifications\DeadlineReminderService;
use Illuminate\Console\Command;

class CheckTargetRisks extends Command
{
    protected $signature = 'app:check-target-risks';
    protected $description = 'Detect targets falling behind progress thresholds and send target at risk alerts';

    public function handle(DeadlineReminderService $service): int
    {
        $this->info('Checking target progress risks...');
        $count = $service->checkTargetRisks();
        $this->info("Generated {$count} target at risk notifications.");

        return self::SUCCESS;
    }
}
