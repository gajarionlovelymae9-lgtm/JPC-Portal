<?php

namespace App\Models;

use Database\Factories\AiSimilarityResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSimilarityResult extends Model
{
    /** @use HasFactory<AiSimilarityResultFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'compared_report_id',
    ];

    /**
     * Similarity result fields written only by the AI service.
     *
     * @var list<string>
     */
    protected $guarded = ['similarity_score', 'status', 'explanation'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'similarity_score' => 'decimal:4',
        ];
    }

    /**
     * The report serving as the subject of the comparison.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    /**
     * The report this subject was compared against.
     */
    public function comparedReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'compared_report_id');
    }
}
