<?php

namespace App\Services\System;

use App\Models\MaintenanceLog;

/**
 * Aggregates the per-component health checks into one system-health payload.
 *
 * Components: application, database, queue, storage, AI provider, OCR
 * provider. Adds last-recorded backup metadata (from maintenance_logs) so
 * the dashboard can display it without claiming an unverified restore.
 */
class SystemHealthService
{
    public function __construct(
        private readonly ApplicationHealthService $application,
        private readonly DatabaseHealthService $database,
        private readonly QueueHealthService $queue,
        private readonly StorageHealthService $storage,
        private readonly AiHealthService $ai,
        private readonly OcrHealthService $ocr,
    ) {}

    public function check(): array
    {
        $application = $this->application->check();
        $database = $this->database->check();
        $queue = $this->queue->check();
        $storage = $this->storage->check();
        $ai = $this->ai->check();
        $ocr = $this->ocr->check();

        $statuses = [
            $application['status'],
            $database['status'],
            $queue['status'],
            $storage['status'],
            $ai['status'],
            $ocr['status'],
        ];

        $overall = 'ok';
        if (in_array('error', $statuses, true)) {
            $overall = 'degraded';
        } elseif (in_array('degraded', $statuses, true)) {
            $overall = 'degraded';
        }

        return [
            'status' => $overall,
            'checked_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'components' => [
                'application' => $application,
                'database' => $database,
                'queue' => $queue,
                'storage' => $storage,
                'ai' => $ai,
                'ocr' => $ocr,
            ],
            'last_backup' => $this->lastBackup(),
        ];
    }

    /**
     * Summary view used by the admin dashboard status strip.
     *
     * @return array<string, string>
     */
    public function statusStrip(): array
    {
        $check = $this->check();
        $components = $check['components'];
        $backup = $check['last_backup'];

        return [
            'application' => $components['application']['status'],
            'database' => $components['database']['status'],
            'queue' => $components['queue']['status'],
            'storage' => $components['storage']['status'],
            'ai_provider' => $components['ai']['status'],
            'ocr_provider' => $components['ocr']['status'],
            'notifications' => $components['application']['checks']['notifications'] ?? 'ok',
            'last_backup' => $backup['performed_at'] ?? 'never',
        ];
    }

    private function lastBackup(): ?array
    {
        $log = MaintenanceLog::query()
            ->where('maintenance_type', 'Database Backup')
            ->orderByDesc('performed_at')
            ->first();

        if (! $log) {
            return null;
        }

        return [
            'performed_at' => $log->performed_at->toDateString().' '.$log->performed_at->format('H:i'),
            'result' => $log->result,
            'verified' => $log->result === 'completed' && ! $log->rollback_required,
        ];
    }
}
