<?php

namespace App\Console\Commands\System;

use App\Models\MaintenanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * system:verify-backups — Phase 13 backup verification.
 *
 * NEVER claims a backup is valid unless a restore was actually performed and
 * logged. Reports:
 *   - the last recorded backup maintenance log (and whether it was verified);
 *   - whether a backup dump exists on the expected private disk path;
 *   - a FAILURE result when the newest backup is missing, unverified, failed,
 *     or older than the retention window (default 7 days).
 */
class VerifyBackupsCommand extends Command
{
    protected $signature = 'system:verify-backups
        {--days=7 : Maximum acceptable age in days for the newest backup}';

    protected $description = 'Verify that a backup exists, is fresh, and has an approved restore record';

    public function handle(): int
    {
        $maxAgeDays = max(1, (int) $this->option('days'));

        $last = MaintenanceLog::query()
            ->where('maintenance_type', 'Database Backup')
            ->orderByDesc('performed_at')
            ->first();

        if (! $last) {
            $this->error('No backup maintenance log exists — no restore has ever been verified.');

            return self::FAILURE;
        }

        $this->line(sprintf('Last backup record: %s (result: %s)',
            $last->performed_at->toDateString(), $last->result));

        if ($last->result !== 'completed') {
            $this->error('The most recent backup was NOT completed successfully.');

            return self::FAILURE;
        }

        $ageDays = $last->performed_at->diffInDays(now(), false);
        if ($ageDays > $maxAgeDays) {
            $this->error(sprintf('Newest backup is %d day(s) old — exceeds the %d-day window.', $ageDays, $maxAgeDays));

            return self::FAILURE;
        }

        $verifiedRestore = MaintenanceLog::query()
            ->where('maintenance_type', 'Database Restore')
            ->where('result', 'completed')
            ->whereDate('performed_at', '>=', $last->performed_at)
            ->exists();

        if (! $verifiedRestore) {
            $this->error('No successful restore has been verified against this backup — treat the backup as UNVERIFIED.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $dumps = collect($disk->allFiles('backups'))
            ->filter(fn (string $file) => str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz'))
            ->sortDesc()
            ->values();

        if ($dumps->isEmpty()) {
            $this->warn('No .sql backup dump found on the local disk backups directory.');

            return self::FAILURE;
        }

        $this->info(sprintf('Backup OK: %d dump(s) present, newest restore verified on %s.',
            $dumps->count(), $last->performed_at->toDateString()));

        return self::SUCCESS;
    }
}