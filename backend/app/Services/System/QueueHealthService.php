<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Queue health using the existing Laravel database queue
 * (jobs + failed_jobs tables — no independent queue mechanism).
 *
 * Reports pending/backlog size, failed jobs today/week, retry attempts and
 * a coarse breakdown of failed payloads by job type (OCR, AI, notification,
 * export). Read-only.
 */
class QueueHealthService
{
    public function check(): array
    {
        try {
            $pending = DB::table('jobs')->count();

            $failedToday = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->startOfDay())
                ->count();

            $failedWeek = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDays(7))
                ->count();

            $failedTotal = DB::table('failed_jobs')->count();

            $byType = $this->failedByType();

            $checks = [
                'connection' => 'ok',
                'failed_jobs' => $failedToday > 0 ? 'warning' : 'ok',
                'backlog' => $pending > 100 ? 'warning' : 'ok',
            ];

            $metrics = [
                'pending_jobs' => $pending,
                'failed_today' => $failedToday,
                'failed_week' => $failedWeek,
                'failed_total' => $failedTotal,
                'failed_by_type' => $byType,
            ];

            $status = $failedToday > 0 || $pending > 100 ? 'degraded' : 'ok';

            return [
                'status' => $status,
                'checks' => $checks,
                'metrics' => $metrics,
            ];
        } catch (Throwable) {
            return [
                'status' => 'error',
                'checks' => ['connection' => 'error'],
                'metrics' => ['pending_jobs' => 0, 'failed_today' => 0, 'failed_week' => 0, 'failed_total' => 0, 'failed_by_type' => []],
            ];
        }
    }

    private function failedByType(): array
    {
        $rows = DB::table('failed_jobs')->select('payload')->limit(200)->get();
        $counts = ['ocr' => 0, 'ai' => 0, 'notification' => 0, 'export' => 0, 'other' => 0];

        foreach ($rows as $row) {
            $payload = (string) $row->payload;
            $type = 'other';
            if (str_contains($payload, 'ProcessReport') || str_contains($payload, 'Ocr')) {
                $type = 'ocr';
            } elseif (str_contains($payload, 'Analyze') || str_contains($payload, 'Ai') || str_contains($payload, 'AI')) {
                $type = 'ai';
            } elseif (str_contains($payload, 'Notification')) {
                $type = 'notification';
            } elseif (str_contains($payload, 'Export')) {
                $type = 'export';
            }
            $counts[$type]++;
        }

        return $counts;
    }
}