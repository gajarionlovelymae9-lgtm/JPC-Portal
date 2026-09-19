<?php

namespace App\Models;

use Database\Factories\AiAnalysisFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAnalysis extends Model
{
    /** @use HasFactory<AiAnalysisFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'report_version_id',
        'analysis_type',
        'provider',
        'model',
        'analysis_version',
    ];

    /**
     * Execution lifecycle fields written only by the AI service.
     *
     * @var list<string>
     */
    protected $guarded = ['status', 'input_hash', 'started_at', 'completed_at', 'error_message'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The report being analyzed.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Summaries produced by this analysis.
     */
    public function summaries(): HasMany
    {
        return $this->hasMany(AiSummary::class);
    }

    /**
     * Validation results produced by this analysis.
     */
    public function validationResults(): HasMany
    {
        return $this->hasMany(AiValidationResult::class);
    }

    /**
     * Similarity comparisons produced by this analysis.
     */
    public function similarityResults(): HasMany
    {
        return $this->hasMany(AiSimilarityResult::class);
    }

    /**
     * Analyses that finished successfully.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Analyses that failed.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Analyses still being processed.
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }
}
