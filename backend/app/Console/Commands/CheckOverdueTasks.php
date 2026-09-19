<?php

namespace App\Console\Commands;

use App\Services\Notifications\DeadlineReminderService;
use Illuminate\Console\Command;

class CheckOverdueTasks extends Command
{
    protected $signature = 'app:check-overdue-tasks';
    protected $description = 'Detect overdue tasks and send notifications to employees, department heads, and administration';

    public function handle(DeadlineReminderService $service): int
    {
        $this->info('Checking overdue tasks...');
        $count = $service->checkOverdue();
        $this->info("Generated {$count} overdue task notifications.");

        return self::SUCCESS;
    }
}
