<?php

namespace App\Models;

use Database\Factories\ReportFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportFile extends Model
{
    /** @use HasFactory<ReportFileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'report_version_id',
        'original_filename',
        'stored_filename',
        'storage_disk',
        'file_path',
        'mime_type',
        'file_size',
        'checksum',
        'uploaded_by',
    ];

    /**
     * Internal storage details are not part of the public API surface.
     *
     * @var list<string>
     */
    protected $hidden = [
        'stored_filename',
        'storage_disk',
        'file_path',
        'checksum',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /**
     * The report this file belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * The report version this file was uploaded under.
     */
    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class);
    }

    /**
     * The user who uploaded the file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * OCR processing executions for this file.
     */
    public function ocrProcessings(): HasMany
    {
        return $this->hasMany(OcrProcessing::class);
    }
}
