<?php

namespace App\Notifications\Tasks;

use App\Models\TaskAssignment;
use App\Models\User;

class TaskOverdueNotification extends JpcNotification
{
    use BuildsTaskNotification;

    public function __construct(
        protected TaskAssignment $assignment,
        protected User $recipient,
        protected int $daysOverdue,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $task = $this->assignment->task;

        if ($this->selfServiced($this->recipient)) {
            $message = "{$this->taskCode($task)} is overdue by {$this->daysOverdue} {$this->daysWord($this->daysOverdue)}. Please submit it as soon as possible.";
        } else {
            $message = "{$this->employeeName($this->assignment)}'s {$this->taskCode($task)} is overdue by {$this->daysOverdue} {$this->daysWord($this->daysOverdue)}.";
        }

        return [
            'type' => 'task_overdue',
            'title' => 'Task overdue',
            'message' => $message,
            'data' => [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'task_title' => $task->title,
                'due_date' => $this->assignment->due_date?->toDateString(),
                'days_overdue' => $this->daysOverdue,
            ],
            'priority' => 'urgent',
            'task_id' => $task->id,
            'reminder_days' => \App\Models\Notification::REMINDER_DAYS_OVERDUE,
        ];
    }
}