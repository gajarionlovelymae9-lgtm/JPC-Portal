<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\Notifications\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use App\Services\Notifications\NotificationPreferenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reads and updates the caller's own notification preferences (§5, §37–§41).
 *
 * - `GET  /api/v1/notification-preferences`  → resolved routing for every event.
 * - `PATCH /api/v1/notification-preferences` → upsert optional-event toggles.
 *
 * The controller delegates mutation to NotificationPreferenceService, which
 * enforces the critical-event policy (never disabled) and deduplicates writes
 * through the (user_id, event) unique index.
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // Route middleware (permission:notifications.view) gates access.
        $events = $this->preferences->preferencesFor($request->user());

        return ApiResponse::success(
            'Notification preferences retrieved successfully',
            NotificationPreferenceResource::collection($events),
        );
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $written = $this->preferences->updatePreferences(
            $request->user(),
            $request->normalisedPreferences(),
        );

        return ApiResponse::success(
            'Notification preferences updated successfully',
            ['written' => $written],
        );
    }
}
