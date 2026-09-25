<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Storage health for the private report-files disk and the report-exports
 * disk (config/ai_reports.php + config/filesystems.php).
 *
 * Reports disk reachability, export backlog and low-free-space warnings.
 * Never exposes absolute paths or credentials.
 */
class StorageHealthService
{
    private const FREE_SPACE_WARN_BYTES = 500 * 1024 * 1024; // 500 MB

    public function check(): array
    {
        $diskOk = true;
        $exportsWritable = true;

        try {
            Storage::disk('local')->files('');
        } catch (Throwable) {
            $diskOk = false;
        }

        try {
            Storage::disk('report_exports')->put('.healthcheck', 'ok');
            Storage::disk('report_exports')->delete('.healthcheck');
        } catch (Throwable) {
            $exportsWritable = false;
        }

        $free = $this->freeSpaceBytes();

        $checks = [
            'private_disk' => $diskOk ? 'ok' : 'error',
            'exports_disk' => $exportsWritable ? 'ok' : 'error',
            'free_space' => ($free !== null && $free < self::FREE_SPACE_WARN_BYTES) ? 'warning' : 'ok',
        ];

        $metrics = [
            'exports_disk_bytes' => $this->exportsSizeBytes(),
            'free_space_bytes' => $free,
        ];

        $status = ($diskOk && $exportsWritable) ? 'ok' : 'error';
        if ($status === 'ok' && $checks['free_space'] === 'warning') {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'checks' => $checks,
            'metrics' => $metrics,
        ];
    }

    private function exportsSizeBytes(): int
    {
        try {
            $total = 0;
            foreach (Storage::disk('report_exports')->allFiles('') as $file) {
                $total += (int) Storage::disk('report_exports')->size($file);
            }

            return $total;
        } catch (Throwable) {
            return 0;
        }
    }

    private function freeSpaceBytes(): ?int
    {
        try {
            $root = storage_path('app/private');
            $free = @disk_free_space($root);

            return $free === false ? null : (int) $free;
        } catch (Throwable) {
            return null;
        }
    }
}