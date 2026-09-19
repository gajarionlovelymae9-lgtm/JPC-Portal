<?php

namespace App\Models;

use Database\Factories\TaskReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReview extends Model
{
    /** @use HasFactory<TaskReviewFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'task_assignment_id',
        'reviewed_by',
        'decision',
        'remarks',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The task being reviewed.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The assignment being reviewed.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TaskAssignment::class, 'task_assignment_id');
    }

    /**
     * The user who performed the review.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Reviews with an approved decision.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('decision', 'approved');
    }

    /**
     * Reviews with a rejected decision.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('decision', 'rejected');
    }

    /**
     * Reviews requesting a revision.
     */
    public function scopeNeedsRevision(Builder $query): Builder
    {
        return $query->where('decision', 'needs_revision');
    }
}
