<?php

namespace App\Notifications\Tasks;

use Illuminate\Notifications\Notification;

/**
 * Base class for every JPC workflow notification.
 *
 * Sends through the custom "jpc_database" channel into the JPC notifications
 * inbox. Concrete subclasses build the payload (type, title, message, data,
 * priority, task_id, reminder_days) for one recipient at a time.
 */
abstract class JpcNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['jpc_database'];
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     message: string|null,
     *     data: array<string, mixed>,
     *     priority: string,
     *     task_id: int|null,
     *     reminder_days: int|null
     * }
     */
    abstract public function toJpcDatabase(object $notifiable): array;
}