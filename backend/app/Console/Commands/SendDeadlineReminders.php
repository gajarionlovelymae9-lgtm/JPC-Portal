<?php

namespace App\Console\Commands;

use App\Services\Notifications\DeadlineReminderService;
use Illuminate\Console\Command;

class SendDeadlineReminders extends Command
{
    protected $signature = 'tasks:send-deadline-reminders';
    protected $description = 'Send deadline reminders (7d, 3d, 1d, due today) for active task assignments';

    public function handle(DeadlineReminderService $service): int
    {
        $this->info('Checking approaching deadlines...');
        $count = $service->sendReminders();
        $this->info("Generated {$count} deadline reminder notifications.");

        return self::SUCCESS;
    }
}