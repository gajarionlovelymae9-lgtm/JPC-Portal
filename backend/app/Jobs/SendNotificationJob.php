<?php

namespace App\Jobs;

use App\Enums\NotificationEvent;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued notification job with idempotency and preference checks.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $userId,
        public readonly string $eventType,
        public readonly string $title,
        public readonly string $message,
        public readonly array $data = [],
        public readonly string $priority = 'normal',
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $event = NotificationEvent::tryFrom($this->eventType);
        $prefService = app(NotificationPreferenceService::class);

        // Check in-app preference
        $inAppEnabled = $event
            ? $prefService->isEnabled($user, $event, 'in_app')
            : true;

        if ($inAppEnabled) {
            $this->sendInApp($user);
        }

        // Check email preference (email-ready but not sending actual emails without SMTP config)
        $emailEnabled = $event
            ? $prefService->isEnabled($user, $event, 'email')
            : false;

        if ($emailEnabled) {
            $this->logEmailReady($user);
        }
    }

    private function sendInApp(User $user): void
    {
        // Idempotency: check for existing notification with same signature
        $recordId = $this->data['record_id'] ?? null;

        if ($recordId) {
            $exists = Notification::query()
                ->where('user_id', $user->id)
                ->where('type', $this->eventType)
                ->whereJsonContains('data->record_id', $recordId)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();

            if ($exists) {
                return; // duplicate prevention
            }
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $this->eventType,
            'title' => $this->title,
            'message' => $this->message,
            'priority' => $this->priority,
            'data' => ! empty($this->data) ? json_encode($this->data) : null,
        ]);

        NotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'type' => $this->eventType,
            'channel' => 'in_app',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    private function logEmailReady(User $user): void
    {
        NotificationLog::create([
            'user_id' => $user->id,
            'type' => $this->eventType,
            'channel' => 'email',
            'status' => 'queued',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob failed', [
            'user_id' => $this->userId,
            'event' => $this->eventType,
            'error' => $exception->getMessage(),
        ]);

        NotificationLog::create([
            'user_id' => $this->userId,
            'type' => $this->eventType,
            'channel' => 'in_app',
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
