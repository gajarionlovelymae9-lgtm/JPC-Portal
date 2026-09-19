<?php

namespace App\Notifications\Tasks;

use App\Models\TaskAssignment;
use App\Models\User;

class TaskAssignedNotification extends JpcNotification
{
    use BuildsTaskNotification;

    public function __construct(
        protected TaskAssignment $assignment,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $task = $this->assignment->task;

        if ($this->selfServiced($this->recipient)) {
            $message = "You have been assigned {$this->taskCode($task)} — {$task->title}.";
        } else {
            $message = "{$this->employeeName($this->assignment)} was assigned {$this->taskCode($task)} — {$task->title}.";
        }

        return [
            'type' => 'task_assigned',
            'title' => 'New task assigned',
            'message' => $message,
            'data' => [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'task_title' => $task->title,
                'due_date' => $this->assignment->due_date?->toDateString(),
                'priority' => $task->priority,
            ],
            'priority' => $task->priority === 'urgent' ? 'urgent' : 'normal',
            'task_id' => $task->id,
            'reminder_days' => null,
        ];
    }
}