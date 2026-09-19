<?php

namespace App\Http\Resources\Api\V1\Notifications;

use App\Enums\NotificationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Per-event channel routing, resolved through NotificationPreferenceService.
 * Critical events always report every channel enabled (policy §40).
 */
class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = NotificationEvent::tryFrom(
            $this->resource['event'] instanceof NotificationEvent
                ? $this->resource['event']->value
                : (string) $this->resource['event'],
        );

        return [
            'event' => $event ? $event->value : $this->resource['event'],
            'title' => $event ? $event->value : $this->resource['event'],
            'critical' => $event ? $event->isCritical() : false,
            'channels' => [
                'in_app' => (bool) $this->resource['in_app_enabled'],
                'email' => (bool) $this->resource['email_enabled'],
            ],
        ];
    }
}
