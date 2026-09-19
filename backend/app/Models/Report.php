<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'employee_id',
        'title',
        'description',
        'remarks',
        'submission_date',
        'deadline',
        'version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'deadline' => 'date',
            'is_late' => 'boolean',
            'version' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * The task this report addresses.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The employee who submitted the report.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Versioned snapshots of the report.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class);
    }

    /**
     * Files attached across all versions of the report.
     */
    public function files(): HasMany
    {
        return $this->hasMany(ReportFile::class);
    }

    /**
     * Source materials referenced by the report.
     */
    public function references(): HasMany
    {
        return $this->hasMany(ReportReference::class);
    }

    /**
     * AI analysis executions for this report.
     */
    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    /**
     * AI summaries generated from this report.
     */
    public function aiSummaries(): HasMany
    {
        return $this->hasMany(AiSummary::class);
    }

    /**
     * AI validation results produced for this report.
     */
    public function aiValidationResults(): HasMany
    {
        return $this->hasMany(AiValidationResult::class);
    }

    /**
     * AI similarity comparisons where this report is the subject.
     */
    public function aiSimilarityResults(): HasMany
    {
        return $this->hasMany(AiSimilarityResult::class, 'report_id');
    }

    /**
     * Manual review decisions recorded against this report.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ReportReview::class);
    }

    /**
     * Manual approval decisions recorded against this report.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ReportApproval::class);
    }

    /**
     * Reports still in draft state.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /**
     * Reports submitted by the employee but not yet reviewed.
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Reports waiting for review.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereIn('status', ['submitted', 'processing', 'ai_validated', 'under_manual_review']);
    }

    /**
     * Reports still in an active workflow (not draft, not finalized).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['submitted', 'processing', 'ai_validated', 'under_manual_review', 'revision_required']);
    }

    /**
     * Reports that have been approved.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Reports that have been rejected.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Reports flagged as submitted late.
     */
    public function scopeLate(Builder $query): Builder
    {
        return $query->where('is_late', true);
    }
}
