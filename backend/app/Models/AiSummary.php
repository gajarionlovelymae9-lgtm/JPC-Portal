<?php

namespace App\Models;

use Database\Factories\AiSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSummary extends Model
{
    /** @use HasFactory<AiSummaryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'ai_analysis_id',
    ];

    /**
     * Generated summary content written only by the AI summarization service.
     *
     * @var list<string>
     */
    protected $guarded = [
        'summary',
        'key_accomplishments',
        'key_activities',
        'key_findings',
        'results',
        'issues',
        'recommendations',
        'action_items',
        'generated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_accomplishments' => 'array',
            'key_activities' => 'array',
            'key_findings' => 'array',
            'results' => 'array',
            'issues' => 'array',
            'recommendations' => 'array',
            'action_items' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * The report being summarized.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * The AI analysis that generated this summary.
     */
    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(AiAnalysis::class);
    }
}
