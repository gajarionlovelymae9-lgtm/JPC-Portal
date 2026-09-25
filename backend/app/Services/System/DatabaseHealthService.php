<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Database health: connectivity, size, pending migrations and orphan records.
 *
 * Read-only checks. Migration health compares migration files present under
 * database/migrations against the applied set recorded in the `migrations`
 * table — a file that is absent from the table is reported as pending.
 */
class DatabaseHealthService
{
    public function check(): array
    {
        $connected = $this->connectionOk();

        $checks = ['connection' => $connected ? 'ok' : 'error'];

        $metrics = [
            'database_size_mb' => $this->databaseSizeMb(),
            'tables_count' => $this->tableCount(),
        ];

        $orphans = $this->orphanRecords();
        $checks['orphans'] = $orphans['total'] === 0 ? 'ok' : 'warning';
        $metrics['orphan_count'] = $orphans['total'];

        $pending = $this->pendingMigrations();
        $checks['migrations'] = $pending === [] ? 'ok' : 'warning';
        $metrics['pending_migrations'] = $pending;

        $status = $connected ? ($checks['orphans'] === 'ok' ? 'ok' : 'degraded') : 'error';

        return [
            'status' => $status,
            'checks' => $checks,
            'metrics' => $metrics,
        ];
    }

    private function connectionOk(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function databaseSizeMb(): float
    {
        try {
            $driver = config('database.default');
            if ($driver !== 'mysql') {
                return 0.0;
            }
            $database = config('database.connections.mysql.database');
            $row = DB::selectOne(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.TABLES
                 WHERE table_schema = ?',
                [$database],
            );

            return (float) ($row->size_mb ?? 0);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function tableCount(): int
    {
        try {
            $database = config('database.connections.mysql.database');
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE table_schema = ?',
                [$database],
            );

            return (int) ($row->c ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function pendingMigrations(): array
    {
        try {
            $migrationDir = database_path('migrations');
            $files = collect(glob($migrationDir.'/*.php'))
                ->map(fn (string $file): string => basename($file, '.php'))
                ->values();

            $applied = DB::table('migrations')->pluck('migration');

            return $files->diff($applied)->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function orphanRecords(): array
    {
        $candidates = [
            ['reports', 'employee_id', 'employees', 'id'],
            ['report_versions', 'report_id', 'reports', 'id'],
            ['report_files', 'report_id', 'reports', 'id'],
            ['ocr_processings', 'report_file_id', 'report_files', 'id'],
            ['ai_analyses', 'report_id', 'reports', 'id'],
            ['task_assignments', 'task_id', 'tasks', 'id'],
            ['task_assignments', 'employee_id', 'employees', 'id'],
            ['user_feedback', 'user_id', 'users', 'id'],
            ['maintenance_logs', 'performed_by', 'users', 'id'],
        ];

        $findings = [];
        $total = 0;

        foreach ($candidates as [$table, $column, $refTable, $refColumn]) {
            try {
                $count = (int) DB::table($table)
                    ->leftJoin($refTable, "{$table}.{$column}", '=', "{$refTable}.{$refColumn}")
                    ->whereNotNull("{$table}.{$column}")
                    ->whereNull("{$refTable}.{$refColumn}")
                    ->count();
                if ($count > 0) {
                    $findings[] = "{$table}.{$column} -> {$refTable}: {$count}";
                    $total += $count;
                }
            } catch (Throwable) {
                // Table may not exist on a fresh checkout; ignore for health.
            }
        }

        return ['total' => $total, 'details' => $findings];
    }
}