<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Application health: login/auth failures, recent exceptions from the daily
 * log, security-event volume and notification delivery health.
 *
 * Never exposes log contents, credentials or personal data — only counts.
 */
class ApplicationHealthService
{
    public function check(): array
    {
        $authFailures = $this->countFailedLoginsToday();
        $exceptions = $this->countExceptionsToday();
        $notificationsFailed = $this->countFailedNotificationsToday();

        $checks = [
            'application' => 'ok',
            'auth_failures' => $authFailures > 20 ? 'warning' : 'ok',
            'exceptions' => $exceptions > 50 ? 'warning' : 'ok',
            'notifications' => $notificationsFailed > 10 ? 'warning' : 'ok',
        ];

        $metrics = [
            'auth_failures_today' => $authFailures,
            'exceptions_today' => $exceptions,
            'notifications_failed_today' => $notificationsFailed,
        ];

        $status = in_array('warning', $checks, true) ? 'degraded' : 'ok';

        return ['status' => $status, 'checks' => $checks, 'metrics' => $metrics];
    }

    private function countFailedLoginsToday(): int
    {
        try {
            return DB::table('login_activities')
                ->where('event', 'failed')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countFailedNotificationsToday(): int
    {
        try {
            return DB::table('notification_logs')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countExceptionsToday(): int
    {
        try {
            $logFile = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
            if (! is_file($logFile)) {
                return 0;
            }
            $contents = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (! is_array($contents)) {
                return 0;
            }
            $count = 0;
            foreach ($contents as $line) {
                if (str_contains($line, '.ERROR') || str_contains($line, '.CRITICAL') || str_contains($line, '.EMERGENCY')) {
                    $count++;
                }
            }

            return $count;
        } catch (Throwable) {
            return 0;
        }
    }
}