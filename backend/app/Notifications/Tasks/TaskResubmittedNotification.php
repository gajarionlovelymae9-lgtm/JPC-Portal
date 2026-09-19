<?php

namespace App\Notifications\Tasks;

use App\Models\TaskAssignment;
use App\Models\User;

class TaskResubmittedNotification extends JpcNotification
{
    use BuildsTaskNotification;

    public function __construct(
        protected TaskAssignment $assignment,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $task = $this->assignment->task;

        return [
            'type' => 'task_resubmitted',
            'title' => 'Resubmission awaiting review',
            'message' => "{$this->employeeName($this->assignment)} resubmitted {$this->taskCode($task)} — {$task->title} for review.",
            'data' => [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'task_title' => $task->title,
                'submitted_by' => $this->employeeName($this->assignment),
            ],
            'priority' => 'normal',
            'task_id' => $task->id,
            'reminder_days' => null,
        ];
    }
}