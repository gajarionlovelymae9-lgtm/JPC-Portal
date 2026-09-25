<?php

namespace App\Console\Commands\System;

use App\Services\System\SystemMonitoringService;
use Illuminate\Console\Command;

/**
 * system:check-health — Phase 13 scheduled health check.
 *
 * Runs the full system health scan and persists a SystemHealthCheck record
 * (source: scheduled). Exit code 1 when the system is degraded so monitoring
 * layers can react.
 */
class SystemHealthCheckCommand extends Command
{
    protected $signature = 'system:check-health
        {--source=scheduled : Origin of the run (scheduled|manual)}';

    protected $description = 'Run the full system health check and record the result';

    public function __construct(
        private readonly SystemMonitoringService $monitoring,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = in_array($this->option('source'), ['manual', 'api', 'scheduled'], true)
            ? $this->option('source')
            : 'scheduled';

        $check = $this->monitoring->runAndRecord($source);

        $this->info(sprintf('System health: %s', strtoupper($check['status'])));

        foreach ($check['components'] as $name => $component) {
            $this->line(sprintf('  - %-12s %s', $name, $component['status']));
        }

        $warnings = [];
        foreach ($check['components'] as $name => $component) {
            foreach ($component['checks'] ?? [] as $key => $value) {
                if ($value === 'warning') {
                    $warnings[] = "{$name}.{$key}";
                }
            }
        }

        if ($warnings !== []) {
            $this->warn('Warnings: '.implode(', ', $warnings));
        }

        return $check['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}