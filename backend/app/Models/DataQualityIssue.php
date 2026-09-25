<?php

namespace App\Models;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualityIssueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataQualityIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'issue_type',
        'severity',
        'status',
        'entity_type',
        'entity_id',
        'department_id',
        'employee_id',
        'title',
        'description',
        'detected_value',
        'expected_value',
        'detected_by',
        'reviewed_by',
        'resolved_by',
        'reviewed_at',
        'resolved_at',
        'resolution_notes',
        'metadata',
        'fingerprint',
    ];

    protected $casts = [
        'issue_type' => DataQualityIssueType::class,
        'severity' => DataQualityIssueSeverity::class,
        'status' => DataQualityIssueStatus::class,
        'detected_value' => 'array',
        'expected_value' => 'array',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DataQualityScan::class, 'scan_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DataQualityIssueHistory::class, 'data_quality_issue_id')->orderBy('created_at', 'desc');
    }

    public static function generateFingerprint(string $issueType, string $entityType, int|string $entityId, ?string $subKey = null): string
    {
        return hash('sha256', "{$issueType}:{$entityType}:{$entityId}" . ($subKey ? ":{$subKey}" : ''));
    }
}
