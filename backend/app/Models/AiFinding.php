<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'report_version_id',
        'ai_analysis_id',
        'finding_type',
        'severity',
        'title',
        'description',
        'evidence',
        'confidence_score',
        'status',
        'reviewed_by',
        'reviewer_action',
        'reviewer_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class);
    }

    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(AiAnalysis::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
