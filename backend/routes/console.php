<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deadline reminders before a task is due, then overdue alerts for whatever
// slipped past its deadline. Both senders are idempotent (see NotificationService).
Schedule::command('tasks:send-deadline-reminders')->dailyAt('07:00');
Schedule::command('tasks:send-overdue-notifications')->dailyAt('07:30');
