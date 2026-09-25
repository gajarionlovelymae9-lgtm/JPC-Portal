<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only snapshot of one system health check run.
 *
 * Written by SystemMonitoringService on manual, scheduled or API-triggered
 * checks. Contains the status plus the live per-component checks/metrics at
 * the moment the check executed.
 */
class SystemHealthCheck extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'source',
        'checks',
        'metrics',
        'checked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'metrics' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}