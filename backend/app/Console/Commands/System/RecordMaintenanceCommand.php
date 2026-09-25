<?php

namespace App\Console\Commands\System;

use App\Models\User;
use App\Services\System\MaintenanceLogService;
use Illuminate\Console\Command;

/**
 * system:record-maintenance — Phase 13 CLI maintenance logging.
 *
 * Lets an on-duty admin (or documented automated task) append an audited
 * maintenance record from the command line, e.g. backups, schema checks,
 * patching, performance work.
 */
class RecordMaintenanceCommand extends Command
{
    protected $signature = 'system:record-maintenance
        {type : Maintenance type, e.g. "Database Backup" or "Security Patch"}
        {description : What was performed}
        {--module= : Affected module}
        {--result=completed : completed|failed|pending|rolled_back}
        {--rollback-required : Mark that rollback is required}
        {--performed-by= : User ID or email of the performer}
        {--notes= : Free-form notes}';

    protected $description = 'Append an audited maintenance log record from the CLI';

    public function __construct(
        private readonly MaintenanceLogService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $performedBy = null;
        $performer = $this->option('performed-by');
        if (is_string($performer) && $performer !== '') {
            $user = User::query()
                ->where('id', $performer)
                ->orWhere('email', $performer)
                ->first();

            if (! $user) {
                $this->error("Performer not found: {$performer}");

                return self::FAILURE;
            }
            $performedBy = $user->id;
        }

        $this->service->record($performedBy, [
            'maintenance_type' => $this->argument('type'),
            'description' => $this->argument('description'),
            'affected_module' => $this->option('module'),
            'result' => $this->option('result'),
            'rollback_required' => $this->option('rollback-required'),
            'notes' => $this->option('notes'),
        ]);

        $this->info('Maintenance action recorded.');

        return self::SUCCESS;
    }
}