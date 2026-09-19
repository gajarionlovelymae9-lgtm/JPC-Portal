<?php

namespace App\Models;

use Database\Factories\DepartmentTargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepartmentTarget extends Model
{
    /** @use HasFactory<DepartmentTargetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'department_plan_id',
        'department_id',
        'target_name',
        'target_description',
        'target_value',
        'unit',
        'start_date',
        'end_date',
        'status',
        'created_by',
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
     * The department plan this target belongs to.
     */
    public function departmentPlan(): BelongsTo
    {
        return $this->belongsTo(DepartmentPlan::class);
    }

    /**
     * The department this target is scoped to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The user who created the target.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Employee targets contributing to this department target.
     */
    public function employeeTargets(): HasMany
    {
        return $this->hasMany(EmployeeTarget::class);
    }

    /**
     * Tasks operationalizing this department target.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Targets in the draft stage.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
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
