<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal AI quality feedback loop entry.
 *
 * Records how an authorized user rated an AI-produced summary/validation/
 * similarity/finding/insight without mutating the original, historical AI
 * result. Feedback never changes official performance scores.
 */
class AiFeedback extends Model
{
    use HasFactory;

    public const VERDICTS = ['Correct', 'Partially Correct', 'Incorrect', 'Needs Human Review'];

    public const AI_TYPES = ['summary', 'validation', 'similarity', 'findings', 'insight', 'chat'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ai_type',
        'ai_record_id',
        'category',
        'verdict',
        'comment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}