<?php

namespace App\Models;

use Database\Factories\EmployeeTargetMilestoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target milestone — a checkpoint on the way to achieving a target.
 *
 * A milestone is automatically marked `achieved` once the parent target's
 * effective progress reaches `threshold_percent`, and the moment it did so is
 * recorded in `achieved_at` (see
 * App\Services\Employee\EmployeeTargetService::syncMilestones).
 */
class EmployeeTargetMilestone extends Model
{
    /** @use HasFactory<EmployeeTargetMilestoneFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACHIEVED = 'achieved';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_target_id',
        'title',
        'description',
        'threshold_percent',
        'status',
        'achieved_at',
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
            'threshold_percent' => 'integer',
            'sort_order' => 'integer',
            'achieved_at' => 'datetime',
        ];
    }

    /**
     * The parent target this milestone belongs to.
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(EmployeeTarget::class, 'employee_target_id');
    }

    /**
     * Deterministic order — earliest checkpoint first.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('threshold_percent')->orderBy('sort_order')->orderBy('id');
    }
}
