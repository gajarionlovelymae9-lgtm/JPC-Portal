<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only log of maintenance actions (backups, patches, prompt updates,
 * data cleanups, incident responses, ...). Written by MaintenanceLogService
 * (API) or the `system:record-maintenance` command.
 */
class MaintenanceLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'performed_at',
        'version',
        'performed_by',
        'maintenance_type',
        'affected_module',
        'description',
        'result',
        'rollback_required',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'rollback_required' => 'boolean',
        ];
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}