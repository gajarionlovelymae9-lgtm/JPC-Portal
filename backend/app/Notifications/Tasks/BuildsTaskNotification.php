<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;

/**
 * Shared message helpers for task workflow notifications. Keeps the payloads
 * of the nine notification classes readable and consistent.
 */
trait BuildsTaskNotification
{
    private function taskCode(Task $task): string
    {
        // Legacy/manually-created tasks can have no code; fall back to the same
        // display identifier the read models use instead of crashing the
        // notification (which would roll back the whole assignment).
        return $task->task_code ?? "TSK-{$task->id}";
    }

    private function employeeName(TaskAssignment $assignment): string
    {
        return $assignment->employee?->full_name ?? "Employee #{$assignment->employee_id}";
    }

    private function daysWord(int $days): string
    {
        return $days === 1 ? 'day' : 'days';
    }

    /**
     * Whether the recipient is a plain employee (own-task views) rather than a
     * reviewer/manager (management task views).
     */
    private function selfServiced(User $recipient): bool
    {
        return $recipient->roleName() === 'employee';
    }
}
