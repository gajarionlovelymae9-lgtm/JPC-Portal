<?php

namespace App\Models;

use Database\Factories\OcrProcessingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrProcessing extends Model
{
    /** @use HasFactory<OcrProcessingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_file_id',
        'provider',
        'language',
    ];

    /**
     * OCR execution and result fields written only by the OCR service.
     *
     * @var list<string>
     */
    protected $guarded = ['status', 'raw_text', 'confidence_score', 'started_at', 'completed_at', 'error_message'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The report file being processed.
     */
    public function reportFile(): BelongsTo
    {
        return $this->belongsTo(ReportFile::class);
    }

    /**
     * OCR runs that finished successfully.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * OCR runs that failed.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * OCR runs still being processed.
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }
}
