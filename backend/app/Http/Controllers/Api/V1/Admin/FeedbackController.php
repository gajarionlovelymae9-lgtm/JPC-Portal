<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserFeedback;
use App\Services\System\FeedbackService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin feedback management (Phase 13).
 *
 * GET    /admin/feedback              paginated user feedback (filterable)
 * GET    /admin/feedback/{feedback}   single feedback record
 * PATCH  /admin/feedback/{feedback}   update status/assignment/resolution
 * GET    /admin/feedback/ai           AI quality-feedback queue
 */
class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success('Feedback loaded successfully.', $this->service->list($request));
    }

    public function show(UserFeedback $feedback): JsonResponse
    {
        return ApiResponse::success('Feedback loaded successfully.', $feedback->load(['user:id,name,email', 'assignee:id,name']));
    }

    public function update(Request $request, UserFeedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:'.implode(',', UserFeedback::STATUSES),
            'priority' => 'sometimes|in:'.implode(',', UserFeedback::PRIORITIES),
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
            'resolution' => 'sometimes|nullable|string|max:4000',
        ]);

        FeedbackService::validateStatus($validated);

        $updated = $this->service->update($request->user(), $feedback, $validated);

        return ApiResponse::success('Feedback updated successfully.', $updated);
    }

    public function aiIndex(): JsonResponse
    {
        $rows = \App\Models\AiFeedback::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::success('AI feedback loaded successfully.', $rows);
    }
}