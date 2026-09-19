<?php

namespace App\Listeners\Notifications;

use App\Events\TaskManagement\TaskAssigned;
use App\Services\Notifications\NotificationService;

class NotifyTaskAssigned
{
    public function __construct(protected NotificationService $notifications) {}

    public function handle(TaskAssigned $event): void
    {
        $this->notifications->sendTaskAssigned($event->assignment);
    }
}