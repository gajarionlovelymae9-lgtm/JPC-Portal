<?php

namespace App\Models;

use App\Enums\DataQualityScanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataQualityScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_type',
        'scope',
        'status',
        'started_at',
        'completed_at',
        'records_checked',
        'issues_found',
        'critical_count',
        'high_count',
        'medium_count',
        'low_count',
        'initiated_by',
        'error_message',
    ];

    protected $casts = [
        'scope' => 'array',
        'status' => DataQualityScanStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'records_checked' => 'integer',
        'issues_found' => 'integer',
        'critical_count' => 'integer',
        'high_count' => 'integer',
        'medium_count' => 'integer',
        'low_count' => 'integer',
    ];

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class, 'scan_id');
    }
}
