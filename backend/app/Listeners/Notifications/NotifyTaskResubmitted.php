<?php

namespace App\Listeners\Notifications;

use App\Events\TaskManagement\TaskResubmitted;
use App\Services\Notifications\NotificationService;

class NotifyTaskResubmitted
{
    public function __construct(protected NotificationService $notifications) {}

    public function handle(TaskResubmitted $event): void
    {
        $this->notifications->sendTaskResubmitted($event->assignment);
    }
}