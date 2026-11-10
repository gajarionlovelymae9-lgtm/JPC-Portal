<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationEvent;
use App\Models\Notification as NotificationModel;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationPreferenceService;
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
 *
 * This channel is the single point every workflow notification flows through,
 * so it also owns:
 *   - user preference routing — non-critical events are skipped when the
 *     recipient disabled in-app alerts (critical events always deliver);
 *   - honest delivery telemetry — every row written here gets an in-app
 *     delivery log so the Delivery Monitor reflects actual sends.
 */
class JpcDatabaseChannel
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $payload = method_exists($notification, 'toJpcDatabase')
            ? $notification->toJpcDatabase($notifiable)
            : ['title' => class_basename($notification), 'data' => []];

        $type = $payload['type'] ?? 'system';
        $event = $this->eventType($type);

        if ($event !== null && ! $event->isCritical() && ! $this->preferences->isEnabled($notifiable, $event, 'in_app')) {
            // The recipient opted out of this event's in-app alerts. The
            // notification is not written, and no delivery is recorded —
            // nothing was delivered.
            return;
        }

        $row = NotificationModel::query()->create([
            'user_id' => $notifiable->getKey(),
            'type' => $type,
            'title' => $payload['title'],
            'message' => $payload['message'] ?? null,
            'data' => $payload['data'] ?? [],
            'priority' => $payload['priority'] ?? 'normal',
            'task_id' => $payload['task_id'] ?? null,
            'reminder_days' => $payload['reminder_days'] ?? null,
            'read_at' => null,
        ]);

        NotificationLog::query()->create([
            'notification_id' => $row->id,
            'user_id' => $row->user_id,
            'type' => $type,
            'channel' => 'in_app',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Map a notification type to its preference event. Most types share the
     * enum name; some legacy senders use distinct strings ('deadline_reminder',
     * review verbs) that map onto the closest event so per-user preferences
     * actually route them.
     */
    private function eventType(string $type): ?NotificationEvent
    {
        if (($event = NotificationEvent::tryFrom($type)) !== null) {
            return $event;
        }

        return match ($type) {
            'deadline_reminder' => NotificationEvent::TaskDeadline,
            'task_submitted' => NotificationEvent::ReportSubmitted,
            'task_resubmitted' => NotificationEvent::ReportVersionSubmitted,
            'task_approved' => NotificationEvent::ReportApproved,
            'task_rejected' => NotificationEvent::ReportRejected,
            'task_reviewed' => NotificationEvent::ReportUnderReview,
            default => null,
        };
    }
}
