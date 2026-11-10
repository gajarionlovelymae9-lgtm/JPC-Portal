<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single entry in an escalation's audit trail.
 *
 * Rows are append-only: they record who created, reviewed, assigned, updated,
 * resolved or rejected an escalation, together with the status transition and
 * any note. Internal notes (`is_internal`) are never shown to the originating
 * Department Head.
 */
class EscalationHistory extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_REVIEWED = 'reviewed';

    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_STATUS_CHANGED = 'status_changed';

    public const ACTION_NOTE = 'note';

    public const ACTION_RESOLVED = 'resolved';

    public const ACTION_REJECTED = 'rejected';

    protected $fillable = [
        'escalation_id',
        'user_id',
        'action',
        'note',
        'is_internal',
        'old_status',
        'new_status',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function escalation(): BelongsTo
    {
        return $this->belongsTo(DepartmentEscalation::class, 'escalation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
