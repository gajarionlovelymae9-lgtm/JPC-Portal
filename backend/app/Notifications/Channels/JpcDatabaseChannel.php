<?php

namespace App\Notifications\Channels;

use App\Models\Notification as NotificationModel;
use Illuminate\Notifications\Notification;

/**
 * Sends notifications into the JPC custom notifications inbox.
 *
 * Laravel's default database channel expects the morph/UUID schema, but the
 * JPC inbox is a bespoke table (user_id, type, title, message, data) extended
 * with routing metadata (priority, task_id, reminder_days). This channel is
 * registered as "jpc_database" via Notification::extend() and is returned by
 * every JpcNotification's via() method.
 *
 * The unique (user_id, type, task_id, reminder_days) index enforced by the
 * notifications table keeps reminder/overdue senders idempotent.
 */
class JpcDatabaseChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $payload = method_exists($notification, 'toJpcDatabase')
            ? $notification->toJpcDatabase($notifiable)
            : ['title' => class_basename($notification), 'data' => []];

        NotificationModel::query()->create([
            'user_id' => $notifiable->getKey(),
            'type' => $payload['type'] ?? 'system',
            'title' => $payload['title'],
            'message' => $payload['message'] ?? null,
            'data' => $payload['data'] ?? [],
            'priority' => $payload['priority'] ?? 'normal',
            'task_id' => $payload['task_id'] ?? null,
            'reminder_days' => $payload['reminder_days'] ?? null,
            'read_at' => null,
        ]);
    }
}