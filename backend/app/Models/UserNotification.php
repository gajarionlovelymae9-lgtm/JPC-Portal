<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * UserNotification — per-user read-state pivot between a User and a
 * Notification.
 *
 * A Notification row is broadcast to many users; this pivot records whether
 * THAT user has read it, and when. "DB row exists = notification; DB row
 * absent = no notification" is enforced at the service layer by creating the
 * pivot only from real Notification records — never stand-alone.
 */
class UserNotification extends Pivot
{
    public $incrementing = true;

    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'notification_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
