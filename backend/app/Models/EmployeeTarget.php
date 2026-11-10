<?php

namespace App\Models;

use Database\Factories\EmployeeTargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTarget extends Model
{
    /** @use HasFactory<EmployeeTargetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'annual_plan_id',
        'department_target_id',
        'employee_id',
        'title',
        'description',
        'target_value',
        'target_unit',
        'start_date',
        'end_date',
        'status',
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
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The annual plan this target belongs to.
     */
    public function annualPlan(): BelongsTo
    {
        return $this->belongsTo(AnnualPlan::class);
    }

    /**
     * The department target this individual target contributes to, if any.
     */
    public function departmentTarget(): BelongsTo
    {
        return $this->belongsTo(DepartmentTarget::class);
    }

    /**
     * The employee the target is set for.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Tasks that operationalize this employee target.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Sub-targets this target is broken down into.
     */
    public function breakdowns(): HasMany
    {
        return $this->hasMany(EmployeeTargetBreakdown::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Milestone checkpoints on the way to achieving this target.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(EmployeeTargetMilestone::class)
            ->orderBy('threshold_percent')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Adjustment requests raised against this target.
     */
    public function adjustmentRequests(): HasMany
    {
        return $this->hasMany(EmployeeTargetAdjustmentRequest::class)->latest('id');
    }

    /**
     * Achievement notes recorded against this target.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(EmployeeTargetNote::class)->latest('id');
    }

    /**
     * Targets in the active lifecycle stage.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Targets that have been completed.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
