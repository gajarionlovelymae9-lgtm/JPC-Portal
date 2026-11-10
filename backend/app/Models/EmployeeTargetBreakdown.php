<?php

namespace App\Models;

use Database\Factories\EmployeeTargetBreakdownFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target breakdown — one sub-target that splits a parent target into smaller,
 * individually tracked pieces of work.
 *
 * `progress` is stored per sub-target (0–100). The parent target's effective
 * progress is the weighted average of its breakdowns, which is how sub-target
 * progress is connected back to the main target (see
 * App\Services\Employee\EmployeeTargetService).
 */
class EmployeeTargetBreakdown extends Model
{
    /** @use HasFactory<EmployeeTargetBreakdownFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_target_id',
        'title',
        'description',
        'target_value',
        'target_unit',
        'progress',
        'status',
        'completed_at',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'progress' => 'integer',
            'sort_order' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The parent target this sub-target belongs to.
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(EmployeeTarget::class, 'employee_target_id');
    }

    /**
     * Deterministic display order (explicit sort_order, then insertion order).
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
