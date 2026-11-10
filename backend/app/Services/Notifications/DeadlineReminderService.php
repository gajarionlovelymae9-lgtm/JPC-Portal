<?php

namespace App\Services\Notifications;

use App\Enums\NotificationEvent;
use App\Models\Notification;
use App\Models\TaskAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Automated deadline reminder, overdue detection, and target risk checking.
 *
 * Designed to be invoked by the Laravel Scheduler (daily). Every method is
 * idempotent — duplicate notifications are prevented via the unique index
 * on (user_id, type, task_id, reminder_days).
 */
class DeadlineReminderService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Send approaching-deadline reminders for all active assignments.
     * Intervals are configurable via config('task.notifications.reminder_days_before').
     */
    public function sendReminders(): int
    {
        $intervals = config('task.notifications.reminder_days_before', [7, 3, 1, 0]);
        $sent = 0;

        foreach ($intervals as $daysBefore) {
            $targetDate = now()->addDays($daysBefore)->startOfDay();

            $assignments = TaskAssignment::query()
                ->whereNotIn('status', ['completed', 'cancelled', 'submitted'])
                ->whereDate('due_date', $targetDate)
                ->whereHas('employee.user')
                ->with(['employee.user', 'task'])
                ->get();

            foreach ($assignments as $assignment) {
                try {
                    $this->notificationService->sendDeadlineReminder($assignment, $daysBefore);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('Deadline reminder failed', [
                        'assignment_id' => $assignment->id,
                        'days_before' => $daysBefore,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $sent;
    }

    /**
     * Detect overdue task assignments and notify employee + management.
     */
    public function checkOverdue(): int
    {
        $sent = 0;

        $assignments = TaskAssignment::query()
            ->whereNotIn('status', ['completed', 'cancelled', 'submitted'])
            ->whereDate('due_date', '<', now()->startOfDay())
            ->whereHas('employee.user')
            ->with(['employee.user', 'task'])
            ->get();

        foreach ($assignments as $assignment) {
            $daysOverdue = (int) now()->startOfDay()->diffInDays($assignment->due_date);

            try {
                $this->notificationService->sendOverdueNotification($assignment, $daysOverdue);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Overdue notification failed', [
                    'assignment_id' => $assignment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Check employee targets that are at risk (progress below expected threshold).
     */
    public function checkTargetRisks(): int
    {
        $sent = 0;

        $targets = DB::table('employee_targets')
            ->join('employees', 'employee_targets.employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->whereIn('employee_targets.status', ['active', 'approved', 'draft'])
            ->whereNotNull('employee_targets.end_date')
            ->whereDate('employee_targets.end_date', '<=', now()->addDays(14))
            ->select('employee_targets.*', 'users.id as user_id', 'employees.department_id')
            ->get();

        foreach ($targets as $target) {
            $exists = Notification::query()
                ->where('user_id', $target->user_id)
                ->where('type', NotificationEvent::TargetAtRisk->value)
                ->where('task_id', $target->id)
                ->where('reminder_days', -2)
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                Notification::create([
                    'user_id' => $target->user_id,
                    'type' => NotificationEvent::TargetAtRisk->value,
                    'title' => 'Target Milestone Approaching',
                    'message' => "Target \"{$target->title}\" is due on ".($target->end_date ?? 'approaching period').'.',
                    'priority' => 'high',
                    'task_id' => $target->id,
                    'reminder_days' => -2,
                    'data' => json_encode([
                        'module' => 'target',
                        'record_id' => $target->id,
                        'action' => 'view_targets',
                    ]),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Target risk notification failed', [
                    'target_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
