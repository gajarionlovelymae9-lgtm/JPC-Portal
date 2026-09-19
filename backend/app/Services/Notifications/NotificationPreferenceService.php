<?php

namespace App\Services\Notifications;

use App\Enums\NotificationEvent;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves per-user, per-event notification routing (§37–§41, §85, §89).
 *
 * Contract:
 * - Every event is on by default (both in-app and email).
 * - A user's notification_preferences rows can opt optional events out per
 *   channel. Critical events (NotificationEvent::isCritical()) can never be
 *   disabled — institutional policy wins over the user's toggle.
 * - Reads are cached per-user within a single request to avoid N+1 when a
 *   workflow emits several notifications at once.
 */
class NotificationPreferenceService
{
    /** @var array<int, array<string, array{in_app: bool, email: bool}>> */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * @return array{in_app: bool, email: bool}
     */
    public function channelsFor(User $user, NotificationEvent $event): array
    {
        $userId = (int) $user->getKey();

        if (! isset(self::$cache[$userId])) {
            self::$cache[$userId] = NotificationPreference::query()
                ->where('user_id', $userId)
                ->get()
                ->mapWithKeys(static fn (NotificationPreference $p) => [
                    $p->event->value => [
                        'in_app' => $p->in_app_enabled,
                        'email' => $p->email_enabled,
                    ],
                ])
                ->all();
        }

        $row = self::$cache[$userId][$event->value] ?? ['in_app' => true, 'email' => true];

        // Critical policy events are forcibly re-enabled on every channel.
        if ($event->isCritical()) {
            return ['in_app' => true, 'email' => true];
        }

        return $row;
    }

    public function isEnabled(User $user, NotificationEvent $event, string $channel): bool
    {
        $channels = $this->channelsFor($user, $event);

        return match ($channel) {
            'in_app', 'database', 'in-app' => $channels['in_app'],
            'email' => $channels['email'],
            default => false,
        };
    }

    /**
     * Persist channel toggles for optional events; critical events are ignored
     * (never written as disabled). Upserts per (user_id, event) — the unique
     * index on notification_preferences keeps this idempotent.
     *
     * @param  array<string, array{in_app: bool, email: bool}>  $payload  event => channels
     */
    public function updatePreferences(User $user, array $payload): int
    {
        $touched = 0;

        foreach ($payload as $eventName => $channels) {
            $event = NotificationEvent::tryFrom((string) $eventName);

            if ($event === null || $event->isCritical()) {
                continue; // never persisted for unknown/critical events
            }

            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => (int) $user->getKey(),
                    'event' => $event->value,
                ],
                [
                    'in_app_enabled' => $channels['in_app'] ?? true,
                    'email_enabled' => $channels['email'] ?? true,
                ],
            );

            $touched++;
        }

        $this->flush();

        return $touched;
    }

    public static function prefsForUser(User $user): Collection
    {
        return NotificationPreference::query()
            ->where('user_id', (int) $user->getKey())
            ->orderBy('event')
            ->get();
    }

    public function preferencesFor(User $user): Collection
    {
        return self::prefsForUser($user);
    }
}
