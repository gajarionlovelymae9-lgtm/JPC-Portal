<?php

namespace App\Models;

use Database\Factories\TaskAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskAssignment extends Model
{
    /** @use HasFactory<TaskAssignmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'employee_id',
        'assigned_by',
        'assigned_at',
        'accepted_at',
        'status',
        'due_date',
        'completed_at',
        'progress_percentage',
        'remarks',
        'start_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'start_date' => 'date',
            'due_date' => 'date',
            'progress_percentage' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The task this assignment belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The employee the task was assigned to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The user who issued the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Progress history recorded against this assignment.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(TaskProgress::class);
    }

    /**
     * Comments attached to this assignment.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * File attachments for this assignment.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Review history for this assignment.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(TaskReview::class);
    }

    /**
     * Versioned submission history for this assignment.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }

    /**
     * Assignments still in assigned state.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Assignments accepted by the employee.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Assignments currently in progress.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Assignments submitted for review.
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Assignments that have been completed.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Assignments rejected during review.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Assignments cancelled.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Assignments still open (not yet completed/rejected/cancelled).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['assigned', 'accepted', 'in_progress', 'submitted']);
    }

    /**
     * Assignments past their due date that are not finished.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNull('completed_at')
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Restrict to assignments for the given employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Whether the assignment is past its due date and not finished.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->completed_at === null
            && ! in_array($this->status, ['completed', 'cancelled'], true);
    }
}
