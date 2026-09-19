<?php

namespace App\Listeners\Notifications;

use App\Events\TaskManagement\TaskReviewed;
use App\Services\Notifications\NotificationService;

class NotifyTaskReviewed
{
    public function __construct(protected NotificationService $notifications) {}

    public function handle(TaskReviewed $event): void
    {
        $this->notifications->sendReviewed($event->assignment, $event->review);
    }
}