<?php

namespace App\Services\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OCR provider health computed from the existing `ocr_processings` table
 * (Phase 4 OCR architecture). Reports queued/processing/completed/failed
 * counts, failure rate, average duration and low-confidence results.
 *
 * A failing OCR provider never blocks report acceptance — the workflow
 * continues and only the OCR indicator is degraded.
 */
class OcrHealthService
{
    public function check(): array
    {
        try {
            $provider = (string) config('ai_reports.ocr.provider', 'fake');
            $minConfidence = (float) config('ai_reports.ocr.min_confidence', 0.55);

            $total = DB::table('ocr_processings')->count();
            $failed = DB::table('ocr_processings')->where('status', 'failed')->count();
            $completed = DB::table('ocr_processings')->where('status', 'completed')->count();
            $queued = DB::table('ocr_processings')->where('status', 'pending')->count();
            $processing = DB::table('ocr_processings')->where('status', 'processing')->count();

            $lowConfidence = DB::table('ocr_processings')
                ->where('status', 'completed')
                ->whereNotNull('confidence_score')
                ->where('confidence_score', '<', $minConfidence)
                ->count();

            $avgSeconds = $this->averageDurationSeconds();

            $failureRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;

            $checks = [
                'provider_configured' => $provider !== '' ? 'ok' : 'error',
                'failure_rate' => $failureRate > 20 ? 'warning' : 'ok',
                'low_confidence' => $lowConfidence > 0 ? 'warning' : 'ok',
            ];

            $metrics = [
                'provider' => $provider,
                'total' => $total,
                'queued' => $queued,
                'processing' => $processing,
                'completed' => $completed,
                'failed' => $failed,
                'failure_rate_percent' => $failureRate,
                'low_confidence_count' => $lowConfidence,
                'average_duration_seconds' => $avgSeconds,
            ];

            $status = $checks['provider_configured'] === 'ok' ? ($failureRate > 20 ? 'degraded' : 'ok') : 'error';

            return ['status' => $status, 'checks' => $checks, 'metrics' => $metrics];
        } catch (Throwable) {
            return [
                'status' => 'error',
                'checks' => ['provider_configured' => 'error'],
                'metrics' => [
                    'provider' => (string) config('ai_reports.ocr.provider', 'fake'),
                    'total' => 0,
                    'failed' => 0,
                    'failure_rate_percent' => 0,
                ],
            ];
        }
    }

    private function averageDurationSeconds(): float
    {
        $rows = DB::table('ocr_processings')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->select('started_at', 'completed_at')
            ->limit(200)
            ->get();

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($rows as $row) {
            $start = Carbon::parse($row->started_at);
            $end = Carbon::parse($row->completed_at);
            $total += max(0, $end->diffInSeconds($start));
        }

        return round($total / $rows->count(), 2);
    }
}
