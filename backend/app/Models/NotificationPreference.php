<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-user, per-event notification preference.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationEvent $event
 * @property bool $in_app_enabled
 * @property bool $email_enabled
 * @property bool $critical_override_on
 */
class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'event',
        'in_app_enabled',
        'email_enabled',
        'critical_override_on',
    ];

    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'critical_override_on' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEvent(Builder $query, NotificationEvent|string $event): Builder
    {
        return $query->where('event', $event instanceof NotificationEvent ? $event->value : $event);
    }
}
