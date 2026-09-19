<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;

class TaskCancelledNotification extends JpcNotification
{
    use BuildsTaskNotification;

    public function __construct(
        protected Task $task,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_cancelled',
            'title' => 'Task cancelled',
            'message' => "{$this->taskCode($this->task)} — {$this->task->title} was cancelled. No further action is needed.",
            'data' => [
                'task_id' => $this->task->id,
                'task_code' => $this->task->task_code,
                'task_title' => $this->task->title,
            ],
            'priority' => 'normal',
            'task_id' => $this->task->id,
            'reminder_days' => null,
        ];
    }
}