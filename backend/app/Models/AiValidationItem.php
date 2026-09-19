<?php

namespace App\Models;

use Database\Factories\AiValidationItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiValidationItem extends Model
{
    /** @use HasFactory<AiValidationItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ai_validation_result_id',
        'category',
    ];

    /**
     * AI finding content written only by the AI validation service.
     *
     * @var list<string>
     */
    protected $guarded = ['severity', 'finding', 'evidence', 'recommendation'];

    /**
     * The validation result this finding belongs to.
     */
    public function validationResult(): BelongsTo
    {
        return $this->belongsTo(AiValidationResult::class);
    }

    /**
     * Findings that require attention (severity critical).
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Findings flagged as warnings.
     */
    public function scopeWarning(Builder $query): Builder
    {
        return $query->where('severity', 'warning');
    }
}
