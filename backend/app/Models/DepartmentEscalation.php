<?php

namespace App\Models;

use App\Enums\EscalationPriority;
use App\Enums\EscalationStatus;
use App\Enums\EscalationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DepartmentEscalation extends Model
{
    use HasFactory;

    /**
     * Issue types mapped to the model class stored in `related_type`.
     *
     * @var array<string, class-string>
     */
    public const RELATED_TYPES = [
        'task' => Task::class,
        'report' => Report::class,
    ];

    protected $fillable = [
        'department_id',
        'employee_id',
        'created_by',
        'title',
        'type',
        'related_type',
        'related_id',
        'reason',
        'requested_action',
        'attachment_path',
        'attachment_name',
        'priority',
        'status',
        'assigned_to',
        'assigned_at',
        'reviewed_by',
        'reviewed_at',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EscalationType::class,
            'priority' => EscalationPriority::class,
            'status' => EscalationStatus::class,
            'assigned_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The employee the escalation is raised against.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The administrator currently responsible for the escalation.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * The administrator who last reviewed the escalation.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * The related task or report the escalation refers to, when applicable.
     */
    public function related(): MorphTo
    {
        return $this->morphTo(null, 'related_type', 'related_id');
    }

    /**
     * Append-only audit trail of every event on this escalation.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EscalationHistory::class, 'escalation_id')->orderBy('created_at', 'asc');
    }

    /**
     * Escalations that still require attention.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (EscalationStatus $status): string => $status->value,
            EscalationStatus::openStatuses(),
        ));
    }
}
