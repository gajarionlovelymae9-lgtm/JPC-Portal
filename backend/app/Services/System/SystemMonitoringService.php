<?php

namespace App\Services\System;

use App\Models\SystemHealthCheck;
use Illuminate\Support\Facades\DB;

/**
 * Persists health-check runs and aggregates the admin System Health
 * Dashboard payload (component status strip, failed jobs, API/AI/OCR
 * failure counts, warnings, security events and recent critical errors).
 *
 * Dashboard numbers are computed live from the existing tables; the persisted
 * SystemHealthCheck rows provide run history for trend reporting.
 */
class SystemMonitoringService
{
    public function __construct(
        private readonly SystemHealthService $health,
    ) {}

    /**
     * Run a full check, persist it and return the payload.
     */
    public function runAndRecord(string $source = 'manual'): array
    {
        $check = $this->health->check();

        SystemHealthCheck::create([
            'status' => $check['status'],
            'source' => $source,
            'checks' => $this->flattenChecks($check['components']),
            'metrics' => $this->flattenMetrics($check['components']),
            'checked_at' => now(),
        ]);

        return $check;
    }

    /**
     * Dashboard payload: status strip + counters + recent critical errors.
     */
    public function dashboard(): array
    {
        $check = $this->health->check();

        return [
            'system_status' => [
                'application' => $this->label($check['components']['application']['status']),
                'database' => $this->label($check['components']['database']['status']),
                'queue' => $this->label($check['components']['queue']['status']),
                'storage' => $this->label($check['components']['storage']['status']),
                'ai_provider' => $this->label($check['components']['ai']['status']),
                'ocr_provider' => $this->label($check['components']['ocr']['status']),
                'notifications' => $this->label($check['components']['application']['checks']['notifications'] ?? 'ok'),
                'last_backup' => ($check['last_backup']['performed_at'] ?? 'never'),
            ],
            'counters' => [
                'failed_jobs_today' => $check['components']['queue']['metrics']['failed_today'] ?? 0,
                'failed_jobs_week' => $check['components']['queue']['metrics']['failed_week'] ?? 0,
                'api_errors_today' => $check['components']['application']['metrics']['exceptions_today'] ?? 0,
                'ai_failures' => $check['components']['ai']['metrics']['failed'] ?? 0,
                'ocr_failures' => $check['components']['ocr']['metrics']['failed'] ?? 0,
                'storage_warnings' => $check['components']['storage']['checks']['free_space'] === 'warning' ? 1 : 0,
                'database_warnings' => ($check['components']['database']['checks']['orphans'] ?? 'ok') === 'warning' ? 1 : 0,
                'security_events' => $this->securityEventsToday(),
                'auth_failures' => $check['components']['application']['metrics']['auth_failures_today'] ?? 0,
            ],
            'recent_critical_errors' => $this->recentCriticalErrors(),
            'checked_at' => $check['checked_at'],
            'status' => $check['status'],
        ];
    }

    /**
     * Recent persisted health-check runs (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 20): array
    {
        return SystemHealthCheck::query()
            ->orderByDesc('checked_at')
            ->limit($limit)
            ->get()
            ->map(fn (SystemHealthCheck $row) => [
                'id' => $row->id,
                'status' => $row->status,
                'source' => $row->source,
                'checks' => $row->checks,
                'checked_at' => $row->checked_at?->toIso8601String(),
            ])
            ->all();
    }

    private function securityEventsToday(): int
    {
        try {
            return DB::table('login_activities')
                ->where('event', 'failed')
                ->where('created_at', '>=', now()->startOfDay())
                ->count() + DB::table('audit_logs')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function recentCriticalErrors(): array
    {
        try {
            $logFile = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
            if (! is_file($logFile)) {
                return [];
            }
            $contents = @file($logFile, FILE_IGNORE_NEW_LINES);
            if (! is_array($contents)) {
                return [];
            }

            $errors = [];
            foreach (array_reverse($contents) as $line) {
                if (str_contains($line, '.CRITICAL') || str_contains($line, '.EMERGENCY')) {
                    // Redact anything that looks like a token/key/secret value.
                    $line = (string) preg_replace('/(token|key|password|secret)=[^\s&,}]+/i', '$1=[redacted]', $line);
                    $errors[] = mb_substr($line, 0, 400);
                    if (count($errors) >= 5) {
                        break;
                    }
                }
            }

            return $errors;
        } catch (\Throwable) {
            return [];
        }
    }

    private function label(string $status): string
    {
        return match ($status) {
            'ok' => 'Healthy',
            'degraded' => 'Degraded',
            'error' => 'Error',
            default => ucfirst($status),
        };
    }

    private function flattenChecks(array $components): array
    {
        $flat = [];
        foreach ($components as $name => $component) {
            foreach ($component['checks'] ?? [] as $key => $value) {
                $flat[$name.'.'.$key] = $value;
            }
        }

        return $flat;
    }

    private function flattenMetrics(array $components): array
    {
        $flat = [];
        foreach ($components as $name => $component) {
            foreach ($component['metrics'] ?? [] as $key => $value) {
                $flat[$name.'.'.$key] = is_scalar($value) ? $value : json_encode($value);
            }
        }

        return $flat;
    }
}
