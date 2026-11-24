<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Password change request — an employee asks to change their login password and
 * an administrator approves or rejects it.
 *
 * The proposed password is stored already hashed and is never applied at
 * request time; approving copies the hash onto the user record. The full
 * request/decision history is appended to the append-only `activity_logs`
 * trail by PasswordChangeRequestService.
 */
class PasswordChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'requested_by',
        'proposed_password',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
        'applied_at',
    ];

    /**
     * The hash is an implementation detail and must never leave the server.
     *
     * @var list<string>
     */
    protected $hidden = ['proposed_password'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * The account whose password is being changed.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who submitted the request.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The administrator who reviewed the request.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Only requests still awaiting a decision.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
