<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * AI provider health computed from the existing `ai_analyses` table
 * (Phase 4 AI architecture) — never from new, duplicated records.
 *
 * Tracks request/success/failure counts, timeouts, provider/model identifiers
 * and average processing time. Never logs API keys, prompts or report contents.
 */
class AiHealthService
{
    public function check(): array
    {
        try {
            $provider = (string) config('ai_reports.ai.provider', 'fake');

            $total = DB::table('ai_analyses')->count();
            $failed = DB::table('ai_analyses')->where('status', 'failed')->count();
            $success = DB::table('ai_analyses')->where('status', 'completed')->count();
            $pending = DB::table('ai_analyses')->whereIn('status', ['pending', 'processing'])->count();

            $timeouts = DB::table('ai_analyses')
                ->where('status', 'failed')
                ->where(function ($query) {
                    $query->where('error_message', 'like', '%timeout%')
                        ->orWhere('error_message', 'like', '%timed out%');
                })
                ->count();

            $avgSeconds = $this->averageDurationSeconds();

            $providers = DB::table('ai_analyses')->distinct()->pluck('provider')->all();
            $models = DB::table('ai_analyses')->whereNotNull('model')->distinct()->pluck('model')->all();

            $failureRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;

            $checks = [
                'provider_configured' => $provider !== '' ? 'ok' : 'error',
                'failure_rate' => $failureRate > 20 ? 'warning' : 'ok',
            ];

            $metrics = [
                'provider' => $provider,
                'providers_seen' => $providers,
                'models_seen' => $models,
                'total_requests' => $total,
                'successful' => $success,
                'failed' => $failed,
                'pending' => $pending,
                'timeout_count' => $timeouts,
                'failure_rate_percent' => $failureRate,
                'average_processing_seconds' => $avgSeconds,
            ];

            $status = $checks['provider_configured'] === 'ok' ? ($failureRate > 20 ? 'degraded' : 'ok') : 'error';

            return ['status' => $status, 'checks' => $checks, 'metrics' => $metrics];
        } catch (Throwable) {
            return [
                'status' => 'error',
                'checks' => ['provider_configured' => 'error'],
                'metrics' => [
                    'provider' => (string) config('ai_reports.ai.provider', 'fake'),
                    'total_requests' => 0,
                    'successful' => 0,
                    'failed' => 0,
                    'timeout_count' => 0,
                    'failure_rate_percent' => 0,
                    'average_processing_seconds' => 0,
                ],
            ];
        }
    }

    private function averageDurationSeconds(): float
    {
        $rows = DB::table('ai_analyses')
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
            $start = \Carbon\Carbon::parse($row->started_at);
            $end = \Carbon\Carbon::parse($row->completed_at);
            $total += max(0, $end->diffInSeconds($start));
        }

        return round($total / $rows->count(), 2);
    }
}