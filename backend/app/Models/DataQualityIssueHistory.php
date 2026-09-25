<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityIssueHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'data_quality_issue_id',
        'previous_status',
        'new_status',
        'action',
        'performed_by',
        'notes',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(DataQualityIssue::class, 'data_quality_issue_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
