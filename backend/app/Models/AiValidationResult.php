<?php

namespace App\Models;

use Database\Factories\AiValidationResultFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiValidationResult extends Model
{
    /** @use HasFactory<AiValidationResultFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'ai_analysis_id',
        'requirement_match',
        'reference_anchor_score',
        'confidence_score',
    ];

    /**
     * AI result fields written only by the AI validation service.
     *
     * @var list<string>
     */
    protected $guarded = [
        'task_alignment_score',
        'reference_alignment_score',
        'completeness_score',
        'relevance_score',
        'evidence_score',
        'clarity_score',
        'overall_score',
        'result',
        'explanation',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_alignment_score' => 'decimal:4',
            'reference_alignment_score' => 'decimal:4',
            'completeness_score' => 'decimal:4',
            'relevance_score' => 'decimal:4',
            'evidence_score' => 'decimal:4',
            'clarity_score' => 'decimal:4',
            'overall_score' => 'decimal:4',
            'reference_anchor_score' => 'decimal:4',
            'confidence_score' => 'decimal:4',
        ];
    }

    /**
     * The report being validated.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * The AI analysis that produced this result.
     */
    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(AiAnalysis::class);
    }

    /**
     * Granular findings attached to this result.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AiValidationItem::class);
    }

    /**
     * Results that passed validation (advisory only).
     */
    public function scopePassed(Builder $query): Builder
    {
        return $query->where('result', 'pass');
    }

    /**
     * Results flagged with a warning (advisory only).
     */
    public function scopeWarning(Builder $query): Builder
    {
        return $query->where('result', 'warning');
    }

    /**
     * Results that failed validation (advisory only).
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('result', 'fail');
    }
}
