<?php

use App\Jobs\DataQuality\RunDataQualityScanJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deadline reminders before a task is due, then overdue alerts for whatever
// slipped past its deadline. Both senders are idempotent (see NotificationService).
Schedule::command('tasks:send-deadline-reminders')->dailyAt('07:00');
Schedule::command('tasks:send-overdue-notifications')->dailyAt('07:30');

// Phase 9 — Monthly management report on the first of each month, then expire
// stale export files (TTL enforced by the report_exports disk cleanup).
Schedule::command('reports:generate-monthly')->monthlyOn(1, '06:00');
Schedule::command('reports:cleanup-exports')->dailyAt('03:15');

// Phase 13 — System maintenance & monitoring.
// Full health scan daily (recorded in system_health_checks), backup freshness
// check daily, and a weekly automated data-quality scan (reuses the existing
// DataQualityScanner pipeline via the queued job).
Schedule::command('system:check-health')->dailyAt('05:00');
Schedule::command('system:verify-backups')->dailyAt('05:30');
Schedule::job(new RunDataQualityScanJob('full', null, null))->weeklyOn(1, '06:30');

// Announcements scheduled with a future publication time go live (and notify
// their audience) as soon as that time passes.
Schedule::command('announcements:publish-due')->everyMinute();
