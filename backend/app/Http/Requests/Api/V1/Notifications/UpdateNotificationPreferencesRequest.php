<?php

namespace App\Http\Requests\Api\V1\Notifications;

use App\Enums\NotificationEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a per-user notification-preference PATCH payload (§41, §37–§39).
 *
 * - `preferences` is validated as an array.
 * - Unknown event keys and critical events are rejected with a 422 so the
 *   repository is never asked to persist a nonsense or policy-forbidden row.
 * - Boolean channels (in_app / email) must be real booleans.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // channel-level authorization happens in the controller/policy.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $optional = $this->optionalEventValues();

        return [
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.in_app' => ['sometimes', 'boolean'],
            'preferences.*.email' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Normalise the validated payload: keep only known, non-critical events,
     * defaulting missing channels to true. Unknown event keys are rejected
     * with a 422.
     *
     * @return array<string, array{in_app: bool, email: bool}>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function normalisedPreferences(): array
    {
        $optional = $this->optionalEventValues();
        $normalised = [];
        $input = (array) $this->input('preferences');

        foreach ($input as $eventName => $channels) {
            $event = NotificationEvent::tryFrom((string) $eventName);

            // Critical institutional events are never persisted as disabled.
            if ($event !== null && $event->isCritical()) {
                continue;
            }

            if ($event === null || ! in_array((string) $eventName, $this->optionalEventValues(), true)) {
                abort(422, json_encode([
                    'message' => 'Unknown event.',
                    'errors' => ["preferences.{$eventName}" => 'Unknown event.'],
                ]));
            }

            $normalised[(string) $eventName] = [
                'in_app' => filter_var($channels['in_app'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'email' => filter_var($channels['email'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $normalised;
    }

    /**
     * @return list<string>
     */
    private function optionalEventValues(): array
    {
        return array_map(
            static fn (NotificationEvent $event): string => $event->value,
            array_filter(
                NotificationEvent::cases(),
                static fn (NotificationEvent $event): bool => ! $event->isCritical(),
            ),
        );
    }
}
