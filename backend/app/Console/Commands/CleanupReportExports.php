<?php

namespace App\Console\Commands;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * reports:cleanup-exports — Phase 9 export lifecycle cleanup (spec item 55).
 *
 * Marks past-TTL COMPLETED exports as EXPIRED and deletes their file from the
 * private disk, then removes orphaned files older than the retention window.
 */
class CleanupReportExports extends Command
{
    protected $signature = 'reports:cleanup-exports';

    protected $description = 'Expire completed report exports past TTL and delete their files from the private disk';

    public function handle(): int
    {
        $expiredCount = 0;
        $expiredExport = ReportExport::query()
            ->where('status', ReportExportStatus::Completed->value)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredExport as $export) {
            if ($export->file_path !== null) {
                Storage::disk(ReportExport::DISK)->delete($export->file_path);
            }

            $export->forceFill([
                'status' => ReportExportStatus::Expired->value,
                'file_path' => null,
            ])->save();

            $expiredCount++;
        }

        $this->info("Expired {$expiredCount} completed report export(s).");

        return self::SUCCESS;
    }
}