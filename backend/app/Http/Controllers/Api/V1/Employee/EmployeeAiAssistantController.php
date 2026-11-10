<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Enums\AiMessageRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ai\AiChatMessageResource;
use App\Http\Resources\Ai\AiConversationResource;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\AI\AiAssistantScopeGuard;
use App\Services\AI\AiAssistantUsageService;
use App\Services\AI\AiChatSummaryService;
use App\Services\AI\EmployeeAiAssistantService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EmployeeAiAssistantController extends Controller
{
    public function __construct(
        private readonly EmployeeAiAssistantService $assistantService,
        private readonly AiChatSummaryService $summaryService,
        private readonly AiAssistantScopeGuard $scopeGuard,
        private readonly AiAssistantUsageService $usageService,
    ) {}

    public function usage(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'AI usage status retrieved successfully',
            $this->usageService->status($request->user())
        );
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:ai_chat_conversations,id'],
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'language_preference' => ['nullable', 'string', 'in:auto,english,filipino,tagalog,taglish'],
        ]);

        $user = $request->user();
        $message = trim($validated['message']);
        $conversationId = isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null;

        // 1. Backend scope guard — blocked/invalid requests do NOT consume usage quota.
        $refusal = $this->scopeGuard->check($user, $message);
        if ($refusal !== null) {
            $conversation = $conversationId
                ? AiChatConversation::findOrFail($conversationId)
                : AiChatConversation::create([
                    'user_id' => $user->id,
                    'title' => Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 60),
                    'is_archived' => false,
                ]);

            if ((int) $conversation->user_id !== (int) $user->id) {
                abort(403, 'You do not have access to this conversation.');
            }

            $isFirstMessage = ! $conversation->messages()->exists();

            $userMessage = $conversation->messages()->create([
                'role' => AiMessageRole::User,
                'content' => $message,
                'is_visible' => true,
            ]);

            $assistantMessage = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $refusal,
                'intent' => 'employee_assistant_refusal',
                'tool_results' => ['blocked' => true, 'refusal' => $refusal],
                'is_visible' => true,
            ]);

            // Keep the history entry fresh: replace a placeholder title with the
            // employee's first message and bump the conversation timestamp.
            if ($isFirstMessage) {
                $placeholder = ['', 'new chat', 'new conversation', 'untitled', 'untitled conversation'];
                if (in_array(mb_strtolower(trim((string) $conversation->title)), $placeholder, true)) {
                    $conversation->title = Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 60);
                }
            }
            $conversation->forceFill(['updated_at' => now()])->save();

            return ApiResponse::success('Message processed successfully', [
                'conversation_id' => $conversation->id,
                'conversation' => new AiConversationResource($conversation),
                'message' => new AiChatMessageResource($assistantMessage),
                'user_message' => new AiChatMessageResource($userMessage),
                'ok' => true,
            ]);
        }

        // 2. Daily usage limit — enforced server-side.
        $this->usageService->consume($user);

        $result = $this->assistantService->chat(
            $user,
            $conversationId,
            $message,
            $validated['language_preference'] ?? null,
        );

        $conversation = AiChatConversation::find($result['conversation_id']);
        $messageModel = $result['message'];
        $userMessageModel = $result['user_message'] ?? null;

        return ApiResponse::success($result['ok'] ? 'Message processed successfully' : 'Message saved, but the AI assistant is unavailable', [
            'conversation_id' => $result['conversation_id'],
            'conversation' => $conversation ? new AiConversationResource($conversation) : null,
            'message' => new AiChatMessageResource($messageModel),
            'user_message' => $userMessageModel ? new AiChatMessageResource($userMessageModel) : null,
            'ok' => (bool) ($result['ok'] ?? false),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $conversations = AiChatConversation::query()
            ->where('user_id', $request->user()->id)
            ->where('is_archived', false)
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->withCount(['messages' => fn ($q) => $q->where('is_visible', true)->where('role', '!=', 'system')])
            ->latest('updated_at')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 20));

        return ApiResponse::success('Conversations retrieved successfully', AiConversationResource::collection($conversations)->response()->getData(true));
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = AiChatConversation::create([
            'user_id' => $request->user()->id,
            'title' => trim((string) ($validated['title'] ?? 'New Chat')) ?: 'New Chat',
            'is_archived' => false,
        ]);

        return ApiResponse::success('Conversation created successfully', new AiConversationResource($conversation), 201);
    }

    public function showConversation(Request $request, AiChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $this->assertOwner($request, $conversation);

        $conversation->loadCount(['messages' => fn ($q) => $q->where('is_visible', true)->where('role', '!=', 'system')]);

        return ApiResponse::success('Conversation retrieved successfully', new AiConversationResource($conversation));
    }

    public function messages(Request $request, AiChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $this->assertOwner($request, $conversation);

        $messages = $conversation->messages()
            ->where('is_visible', true)
            ->where('role', '!=', 'system')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            'Messages retrieved successfully',
            $messages->map(fn (AiChatMessage $message) => (new AiChatMessageResource($message))->resolve())->all(),
        );
    }

    public function updateConversation(Request $request, AiChatConversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);
        $this->assertOwner($request, $conversation);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $conversation->forceFill(['title' => trim($validated['title'])])->save();

        return ApiResponse::success('Conversation renamed successfully', new AiConversationResource($conversation));
    }

    public function destroyConversation(Request $request, AiChatConversation $conversation): JsonResponse
    {
        Gate::authorize('delete', $conversation);
        $this->assertOwner($request, $conversation);

        $conversation->update(['is_archived' => true]);

        return ApiResponse::success('Conversation deleted successfully');
    }

    public function summaries(Request $request): JsonResponse
    {
        $conversationId = $request->query('conversation_id');
        $items = $this->summaryService->listForUser(
            $request->user(),
            $conversationId !== null && $conversationId !== '' ? (int) $conversationId : null,
        );

        return ApiResponse::success('Saved summaries retrieved successfully', $items);
    }

    public function storeSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:ai_chat_conversations,id'],
            'assistant_message_id' => ['nullable', 'integer', 'exists:ai_chat_messages,id'],
            'summary_content' => ['nullable', 'string', 'max:50000'],
            'title' => ['nullable', 'string', 'max:120'],
            'summary_type' => ['nullable', 'string', 'max:50'],
            'related_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $summary = $this->summaryService->save($request->user(), $validated);

        return ApiResponse::success('Summary saved successfully', $summary, 201);
    }

    public function showSummary(Request $request, int $summary): JsonResponse
    {
        $item = $this->summaryService->findOwned($request->user(), $summary);

        return ApiResponse::success('Summary retrieved successfully', $this->summaryService->shape($item));
    }

    public function destroySummary(Request $request, int $summary): JsonResponse
    {
        $this->summaryService->delete($request->user(), $summary);

        return ApiResponse::success('Summary deleted successfully');
    }

    private function assertOwner(Request $request, AiChatConversation $conversation): void
    {
        if ((int) $conversation->user_id !== (int) $request->user()->id) {
            abort(403, 'You do not have access to this conversation.');
        }
    }
}
