<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'annual_plan_id',
        'department_plan_id',
        'department_target_id',
        'employee_target_id',
        'parent_task_id',
        'task_code',
        'department_id',
        'title',
        'description',
        'priority',
        'status',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_hours',
        'target_value',
        'target_unit',
        'required_output',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'target_value' => 'decimal:2',
        ];
    }

    /**
     * The annual plan this task belongs to, if any.
     */
    public function annualPlan(): BelongsTo
    {
        return $this->belongsTo(AnnualPlan::class);
    }

    /**
     * The department plan this task belongs to, if any.
     */
    public function departmentPlan(): BelongsTo
    {
        return $this->belongsTo(DepartmentPlan::class);
    }

    /**
     * The department target this task operationalizes, if any.
     */
    public function departmentTarget(): BelongsTo
    {
        return $this->belongsTo(DepartmentTarget::class);
    }

    /**
     * The employee target this task operationalizes, if any.
     */
    public function employeeTarget(): BelongsTo
    {
        return $this->belongsTo(EmployeeTarget::class);
    }

    /**
     * The department responsible for the task, if any.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The user who created the task.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who last updated the task.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The parent task this task is a subtask of, if any.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Subtasks that break this task down further.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * Assignment records distributing this task to employees.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Append-only progress history for this task.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(TaskProgress::class);
    }

    /**
     * Comments attached to this task.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * File attachments for this task.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Review history for this task.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(TaskReview::class);
    }

    /**
     * Reference materials attached to this task.
     */
    public function references(): HasMany
    {
        return $this->hasMany(TaskReference::class);
    }

    /**
     * Reports submitted against this task.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Tasks in draft state.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /**
     * Tasks that have been assigned but not started.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Tasks currently in progress.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Tasks submitted for review.
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Tasks currently under review.
     */
    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', 'under_review');
    }

    /**
     * Tasks that have been completed.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Tasks that were rejected during review.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Tasks that were cancelled.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Tasks with high priority.
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', 'high');
    }

    /**
     * Tasks with urgent priority.
     */
    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', 'urgent');
    }

    /**
     * Tasks with an open (not yet terminal) lifecycle status.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'assigned', 'in_progress', 'submitted', 'under_review']);
    }

    /**
     * Tasks past their due date that have not been finished.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNull('completed_at')
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Restrict to tasks in the given department.
     */
    public function scopeByDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Whether the task is past its due date and not finished.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->completed_at === null
            && ! in_array($this->status, ['completed', 'cancelled'], true);
    }

    /**
     * Whether the task has reached its completed state.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
