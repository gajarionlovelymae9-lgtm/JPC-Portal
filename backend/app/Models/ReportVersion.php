<?php

namespace App\Models;

use Database\Factories\ReportVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportVersion extends Model
{
    /** @use HasFactory<ReportVersionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'version_number',
        'title',
        'content',
        'references',
        'status',
        'submitted_by',
        'file_path',
        'notes',
        'change_summary',
        'metadata',
        'submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'metadata' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReportFile::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReportReviewComment::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AiFinding::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ReportReview::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(ReportTimeline::class);
    }
}
