<?php

namespace App\Listeners\Notifications;

use App\Events\TaskManagement\TaskSubmitted;
use App\Services\Notifications\NotificationService;

class NotifyTaskSubmitted
{
    public function __construct(protected NotificationService $notifications) {}

    public function handle(TaskSubmitted $event): void
    {
        $this->notifications->sendTaskSubmitted($event->assignment);
    }
}