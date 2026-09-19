<?php

namespace App\Listeners\Notifications;

use App\Events\TaskManagement\TaskCancelled;
use App\Services\Notifications\NotificationService;

class NotifyTaskCancelled
{
    public function __construct(protected NotificationService $notifications) {}

    public function handle(TaskCancelled $event): void
    {
        $this->notifications->sendTaskCancelled($event->task);
    }
}