<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Enums\AiMessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\SendChatMessageRequest;
use App\Http\Resources\Ai\AiChatResponseResource;
use App\Models\AiChatConversation;
use App\Services\AI\AiAssistantScopeGuard;
use App\Services\AI\AiAssistantUsageService;
use App\Services\AI\AiChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    public function __construct(
        protected AiChatService $chatService,
        protected AiAssistantScopeGuard $scopeGuard,
        protected AiAssistantUsageService $usageService,
    ) {}

    public function usage(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'AI usage status retrieved successfully',
            $this->usageService->status($request->user())
        );
    }

    public function send(SendChatMessageRequest $request)
    {
        $user    = $request->user();
        $message = $request->validated('message');
        $conversationId = $request->validated('conversation_id');

        if ($conversationId) {
            $conversation = AiChatConversation::findOrFail($conversationId);
            Gate::authorize('update', $conversation);
        } else {
            $conversation = AiChatConversation::create([
                'user_id' => $user->id,
                'title' => Str::limit($message, 60),
            ]);
        }

        // 1. Backend scope guard — blocked/invalid requests do NOT consume usage quota.
        $refusal = $this->scopeGuard->check($user, $message);
        if ($refusal !== null) {
            $conversation->messages()->create([
                'role' => AiMessageRole::User,
                'content' => $message,
            ]);

            $assistantMsg = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $refusal,
                'intent' => 'DEFAULT',
                'tool_results' => ['blocked' => true, 'refusal' => $refusal],
            ]);

            return ApiResponse::success('Message processed successfully', new AiChatResponseResource([
                'conversation_id' => $conversation->id,
                'conversation' => $conversation,
                'message' => $assistantMsg,
                'intent' => 'DEFAULT',
                'period' => now()->format('Y-m'),
                'results' => ['blocked' => true, 'refusal' => $refusal],
                'actions' => [],
            ]));
        }

        // 2. Daily usage limit — enforced server-side, throws 429 when limit reached.
        $this->usageService->consume($user);

        $result = $this->chatService->handleMessage(
            $user,
            $conversation->id,
            $message,
            $request->validated('language_preference') ?? null,
        );

        return ApiResponse::success('Message processed successfully', new AiChatResponseResource(
            $result + ['conversation' => $conversation, 'conversation_id' => $conversation->id],
        ));
    }
}
