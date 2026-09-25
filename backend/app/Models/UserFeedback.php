<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User-submitted feedback / bug / feature request.
 *
 * Statuses follow the maintenance lifecycle: OPEN -> UNDER_REVIEW ->
 * IN_PROGRESS -> RESOLVED / REJECTED / CLOSED. Priority mirrors the bug
 * severity taxonomy (low | medium | high | critical).
 */
class UserFeedback extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CLOSED = 'CLOSED';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_REJECTED,
        self::STATUS_CLOSED,
    ];

    public const CATEGORIES = [
        'bug',
        'incorrect_information',
        'ui_problem',
        'ai_concern',
        'ocr_concern',
        'feature_request',
        'usability',
        'other',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to',
        'resolution',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}