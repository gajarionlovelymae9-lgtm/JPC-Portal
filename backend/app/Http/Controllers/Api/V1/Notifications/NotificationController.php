<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Notifications\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    private const CATEGORY_TYPES = [
        'deadline' => ['deadline_reminder', 'task_overdue'],
        'review' => ['task_submitted', 'task_resubmitted', 'revision_required', 'task_approved', 'task_rejected'],
        'task' => ['task_assigned', 'task_cancelled'],
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $query = $this->applyFilters(
            Notification::query()->forUser($request->user()->id),
            $request,
        )->with('user')->latest('created_at')->latest('id');

        return ApiResponse::collection(
            'Notifications retrieved successfully',
            NotificationResource::collection($query->paginate($this->perPage($request))),
        );
    }

    public function unread(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $query = $this->applyFilters(
            Notification::query()->forUser($request->user()->id)->unread(),
            $request,
        )->with('user')->latest('created_at')->latest('id');

        return ApiResponse::collection(
            'Unread notifications retrieved successfully',
            NotificationResource::collection($query->paginate($this->perPage($request))),
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()->forUser($request->user()->id)->unread()->count();

        return ApiResponse::success('Unread notifications count retrieved', ['count' => $count]);
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        return ApiResponse::success('Notification retrieved successfully', new NotificationResource($notification->load('user')));
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        if ($notification->read_at === null) {
            DB::transaction(function () use ($notification): void {
                $notification->update(['read_at' => now()]);

                // Record the read transition on the recipient's own delivery
                // log only — reading is strictly per-recipient.
                NotificationLog::query()
                    ->where('notification_id', $notification->id)
                    ->where('user_id', $notification->user_id)
                    ->where('channel', 'in_app')
                    ->where('status', '!=', 'failed')
                    ->update(['status' => 'read']);
            });
        }

        return ApiResponse::success('Notification marked as read', new NotificationResource($notification->fresh('user')));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::query()
            ->forUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read', ['updated' => $updated]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return ApiResponse::noContent();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('filter')) {
            match ($request->input('filter')) {
                'unread' => $query->unread(),
                'read' => $query->read(),
                default => null,
            };
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('category')) {
            $this->applyCategoryFilter($query, (string) $request->input('category'));
        }

        return $query;
    }

    private function applyCategoryFilter(Builder $query, string $category): Builder
    {
        if (isset(self::CATEGORY_TYPES[$category])) {
            return $query->whereIn('type', self::CATEGORY_TYPES[$category]);
        }

        // "system": anything outside the task/review/deadline workflow buckets.
        return $query->whereNotIn('type', array_merge(...array_values(self::CATEGORY_TYPES)));
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->input('per_page', 20), 100);
    }
}
