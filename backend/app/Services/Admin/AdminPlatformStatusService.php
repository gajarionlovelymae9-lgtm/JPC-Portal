<?php

namespace App\Services\Admin;

use App\Services\System\SystemHealthService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Admin "System Status" + "Platform Architecture" payload.
 *
 * Reuses the existing SystemHealthService so the admin dashboard never
 * invents its own health probes, then maps the component statuses onto the
 * Connected / Degraded / Disconnected vocabulary the dashboard renders and
 * describes the running platform stack.
 */
class AdminPlatformStatusService
{
    /**
     * API version served under the /api/v1 prefix.
     */
    private const API_VERSION = 'v1';

    public function __construct(
        private readonly SystemHealthService $health,
    ) {}

    /**
     * Full platform payload for the admin dashboard system-status card.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $check = $this->health->check();
        $components = $check['components'];

        return [
            'checked_at' => $check['checked_at'],
            'overall' => $check['status'],
            'environment' => app()->environment(),
            'api_version' => self::API_VERSION,
            'services' => [
                [
                    'key' => 'api',
                    'label' => 'REST API',
                    'state' => $this->state($components['application']['status'] ?? 'ok'),
                    'detail' => 'Version '.self::API_VERSION,
                ],
                [
                    'key' => 'database',
                    'label' => 'Database',
                    'state' => $this->state($components['database']['status'] ?? 'ok'),
                    'detail' => $this->databaseDetail($components['database'] ?? []),
                ],
                [
                    'key' => 'backend',
                    'label' => 'Backend',
                    'state' => $this->state($components['application']['status'] ?? 'ok'),
                    'detail' => 'Laravel '.app()->version().' · '.app()->environment(),
                ],
                [
                    'key' => 'queue',
                    'label' => 'Queue Worker',
                    'state' => $this->state($components['queue']['status'] ?? 'ok'),
                    'detail' => 'Driver: '.config('queue.default'),
                ],
                [
                    'key' => 'storage',
                    'label' => 'File Storage',
                    'state' => $this->state($components['storage']['status'] ?? 'ok'),
                    'detail' => 'Disk: '.config('filesystems.default'),
                ],
                [
                    'key' => 'ai',
                    'label' => 'AI Provider',
                    'state' => $this->state($components['ai']['status'] ?? 'ok'),
                    'detail' => 'Provider: '.($components['ai']['metrics']['provider'] ?? config('ai_reports.ai.provider', 'fake')),
                ],
                [
                    'key' => 'ocr',
                    'label' => 'OCR Provider',
                    'state' => $this->state($components['ocr']['status'] ?? 'ok'),
                    'detail' => 'Provider: '.($components['ocr']['metrics']['provider'] ?? config('ai_reports.ocr.provider', 'fake')),
                ],
            ],
            'architecture' => $this->architecture(),
            'last_backup' => $check['last_backup'],
        ];
    }

    /**
     * Static description of the deployed stack, read from live config so the
     * card can never drift from the running application.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public function architecture(): array
    {
        return [
            ['key' => 'frontend', 'label' => 'Frontend', 'value' => 'React + Vite SPA (TypeScript)'],
            ['key' => 'transport', 'label' => 'Transport', 'value' => 'REST API over JSON · v'.self::API_VERSION],
            ['key' => 'backend', 'label' => 'Backend', 'value' => 'Laravel '.app()->version().' (PHP '.PHP_VERSION.')'],
            ['key' => 'auth', 'label' => 'Authentication', 'value' => 'Laravel Sanctum (session cookie + CSRF)'],
            ['key' => 'database', 'label' => 'Database', 'value' => $this->databaseName()],
            ['key' => 'environment', 'label' => 'Environment', 'value' => app()->environment()],
        ];
    }

    /**
     * Map a raw health status onto the dashboard vocabulary.
     */
    private function state(?string $status): string
    {
        return match ($status) {
            'ok' => 'connected',
            'degraded' => 'warning',
            'error' => 'disconnected',
            default => 'warning',
        };
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function databaseDetail(array $component): string
    {
        $name = $this->databaseName();
        $tables = $component['metrics']['tables_count'] ?? null;
        $size = $component['metrics']['database_size_mb'] ?? null;

        $detail = $name;
        if (is_numeric($tables)) {
            $detail .= ' · '.$tables.' tables';
        }
        if (is_numeric($size)) {
            $detail .= ' · '.$size.' MB';
        }

        return $detail;
    }

    private function databaseName(): string
    {
        try {
            DB::connection()->getPdo();

            return (string) config('database.connections.mysql.database', 'acadtrack_db');
        } catch (Throwable) {
            return (string) config('database.connections.mysql.database', 'acadtrack_db');
        }
    }
}
