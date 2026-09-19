<?php

namespace App\Notifications\Tasks;

use App\Models\TaskAssignment;
use App\Models\User;

class TaskDeadlineReminderNotification extends JpcNotification
{
    use BuildsTaskNotification;

    public function __construct(
        protected TaskAssignment $assignment,
        protected User $recipient,
        protected int $daysBefore,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $task = $this->assignment->task;

        $message = $this->daysBefore === 0
            ? "{$this->taskCode($task)} — {$task->title} is due today."
            : "{$this->taskCode($task)} — {$task->title} is due in {$this->daysBefore} {$this->daysWord($this->daysBefore)}.";

        return [
            'type' => 'deadline_reminder',
            'title' => $this->daysBefore === 0 ? 'Due today' : 'Deadline reminder',
            'message' => $message,
            'data' => [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'task_title' => $task->title,
                'due_date' => $this->assignment->due_date?->toDateString(),
                'days_before' => $this->daysBefore,
            ],
            'priority' => $this->daysBefore <= 1 ? 'important' : 'normal',
            'task_id' => $task->id,
            'reminder_days' => $this->daysBefore,
        ];
    }
}