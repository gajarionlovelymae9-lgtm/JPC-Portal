<?php

namespace App\Services\System;

use App\Models\MaintenanceLog;
use App\Services\Audit\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Maintenance log write/read with audit trail (every maintenance action is
 * auditable). Used by MaintenanceController and system commands.
 */
class MaintenanceLogService
{
    public function __construct(
        private readonly ActivityLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(?int $performedBy, array $data): MaintenanceLog
    {
        $log = MaintenanceLog::create([
            'performed_at' => $data['performed_at'] ?? now()->toDateString(),
            'version' => $data['version'] ?? $this->currentVersion(),
            'performed_by' => $performedBy,
            'maintenance_type' => $data['maintenance_type'],
            'affected_module' => $data['affected_module'] ?? null,
            'description' => $data['description'],
            'result' => $data['result'] ?? 'completed',
            'rollback_required' => (bool) ($data['rollback_required'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->log(
            'maintenance.record',
            "Maintenance recorded: {$log->maintenance_type} ({$log->result})",
            $performedBy ? \App\Models\User::find($performedBy) : null,
            $log,
            ['type' => $log->maintenance_type, 'module' => $log->affected_module],
        );

        return $log;
    }

    public function list(Request $request): LengthAwarePaginator
    {
        $query = MaintenanceLog::query()->with('performer:id,name,email');

        if ($request->filled('type')) {
            $query->where('maintenance_type', $request->string('type'));
        }
        if ($request->filled('result')) {
            $query->where('result', $request->string('result'));
        }

        return $query->orderByDesc('performed_at')->orderByDesc('id')->paginate(
            min((int) $request->input('per_page', 15), 50),
        );
    }

    /**
     * Read the JPC Portal VERSION file from the project root (the backend
     * base_path sits one level below it). Falls back to config('app.version').
     */
    private function currentVersion(): ?string
    {
        $versionFile = dirname(base_path()).'/VERSION';
        if (is_file($versionFile)) {
            $raw = @file_get_contents($versionFile);
            if (is_string($raw)) {
                // File format: "JPC Portal v1.0.0\n<release date...>"
                if (preg_match('/v\d+\.\d+\.\d+/', $raw, $matches)) {
                    return $matches[0];
                }
            }
        }

        return (string) config('app.version', 'v1.0.0');
    }
}