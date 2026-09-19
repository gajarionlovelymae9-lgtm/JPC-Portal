<?php

namespace App\Console\Commands;

use App\Models\TaskAssignment;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

class SendOverdueNotifications extends Command
{
    protected $signature = 'tasks:send-overdue-notifications';

    protected $description = 'Notify assigned employees and management when a task assignment becomes overdue.';

    public function handle(NotificationService $notifications): int
    {
        $assignments = TaskAssignment::query()
            ->overdue()
            ->with(['task', 'employee.user'])
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            $daysOverdue = $this->wholeDaysOverdue($assignment->due_date);

            $notifications->sendOverdueNotification($assignment, max(1, $daysOverdue));
            $sent++;
        }

        $this->info("Overdue notifications sent for {$sent} overdue assignment(s).");

        return self::SUCCESS;
    }

    /**
     * Whole calendar days the assignment is past its due date.
     */
    private function wholeDaysOverdue(\Carbon\CarbonInterface $due): int
    {
        $today = now()->copy()->startOfDay();

        return (int) round(($today->getTimestamp() - $due->copy()->startOfDay()->getTimestamp()) / 86_400);
    }
}