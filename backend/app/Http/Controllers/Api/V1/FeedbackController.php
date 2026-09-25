<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiFeedback;
use App\Models\UserFeedback;
use App\Services\System\FeedbackService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User-facing feedback submission (Phase 13).
 *
 * POST /feedback          submit bug/incorrect-info/UI/AI/OCR/feature/usability
 * GET  /feedback/mine     list the caller's own feedback
 * POST /ai-feedback       rate an AI summary/validation/similarity/finding
 *
 * AI feedback never mutates the original AI result or performance scores.
 */
class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $service,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|in:'.implode(',', UserFeedback::CATEGORIES),
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:4000',
            'priority' => 'nullable|in:'.implode(',', UserFeedback::PRIORITIES),
        ]);

        $feedback = $this->service->submit($request->user(), $validated);

        return ApiResponse::success('Feedback submitted successfully.', $feedback, 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $rows = UserFeedback::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::success('Your feedback loaded successfully.', $rows);
    }

    public function storeAiFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_type' => 'required|in:'.implode(',', AiFeedback::AI_TYPES),
            'ai_record_id' => 'nullable|integer',
            'category' => 'nullable|string|max:40',
            'verdict' => 'required|in:'.implode(',', AiFeedback::VERDICTS),
            'comment' => 'nullable|string|max:2000',
        ]);

        $feedback = $this->service->recordAiFeedback($request->user(), $validated);

        return ApiResponse::success('AI feedback recorded successfully.', $feedback, 201);
    }
}