<?php

namespace App\Notifications\Tasks;

use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Models\User;

class TaskApprovedNotification extends JpcNotification
{
    use BuildsTaskNotification;

    public function __construct(
        protected TaskAssignment $assignment,
        protected User $recipient,
        protected TaskReview $review,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $task = $this->assignment->task;

        return [
            'type' => 'task_approved',
            'title' => 'Task approved',
            'message' => "Your submission for {$this->taskCode($task)} — {$task->title} was approved. Well done!",
            'data' => [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'task_title' => $task->title,
                'decision' => $this->review->decision,
                'remarks' => $this->review->remarks,
                'reviewed_by' => $this->review->reviewed_by,
            ],
            'priority' => 'normal',
            'task_id' => $task->id,
            'reminder_days' => null,
        ];
    }
}