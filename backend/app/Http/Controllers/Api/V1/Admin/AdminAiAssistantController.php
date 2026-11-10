<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AiMessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\SendChatMessageRequest;
use App\Http\Resources\Ai\AiChatMessageResource;
use App\Http\Resources\Ai\AiConversationResource;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\AI\AdminAiAssistantService;
use App\Services\AI\AiAssistantScopeGuard;
use App\Services\AI\AiAssistantUsageService;
use App\Services\AiReports\Ai\Exceptions\AiProviderConfigurationException;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AdminAiAssistantController extends Controller
{
    public function __construct(
        private readonly AdminAiAssistantService $assistantService,
        private readonly AiAssistantScopeGuard $scopeGuard,
        private readonly AiAssistantUsageService $usageService,
    ) {}

    public function usage(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        return ApiResponse::success('AI usage status retrieved successfully', $this->usageService->status($request->user()));
    }

    public function chat(SendChatMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdminUser($user);
        $message = $request->validated('message');
        $conversationId = $request->validated('conversation_id');
        $language = $request->validated('language_preference');

        $refusal = $this->scopeGuard->check($user, $message);
        if ($refusal !== null) {
            $conversation = $this->resolveOwnedConversation($user, $conversationId, $message);
            $conversation->messages()->create([
                'role' => AiMessageRole::User,
                'content' => $message,
                'is_visible' => true,
            ]);
            $assistant = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $refusal,
                'intent' => 'blocked',
                'tool_results' => ['blocked' => true],
                'is_visible' => true,
            ]);
            $conversation->forceFill(['updated_at' => now()])->save();

            return ApiResponse::success('Message processed successfully', [
                'conversation_id' => $conversation->id,
                'conversation' => new AiConversationResource($conversation->fresh()),
                'message' => new AiChatMessageResource($assistant),
                'ok' => true,
            ]);
        }

        // Provider/config must be valid before consuming daily quota. The service also validates.
        try {
            $this->assistantService->ensureRealProviderConfigured();
        } catch (AiProviderConfigurationException $e) {
            return ApiResponse::error($e->getMessage(), [], 503);
        }

        $this->usageService->consume($user);

        $result = $this->assistantService->chat($user, $conversationId ? (int) $conversationId : null, $message, $language);

        return ApiResponse::success('Message processed successfully', [
            'conversation_id' => $result['conversation_id'],
            'conversation' => new AiConversationResource(AiChatConversation::query()->find($result['conversation_id'])),
            'message' => new AiChatMessageResource($result['message']),
            'user_message' => isset($result['user_message']) ? new AiChatMessageResource($result['user_message']) : null,
            'ok' => (bool) ($result['ok'] ?? false),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdminUser($user);
        $search = trim((string) $request->query('q', ''));

        $conversations = AiChatConversation::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->latest('updated_at')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success('Conversations retrieved successfully', AiConversationResource::collection($conversations));
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdminUser($user);
        $title = trim((string) $request->input('title', 'New Chat'));
        $conversation = AiChatConversation::create([
            'user_id' => $user->id,
            'title' => $title !== '' ? Str::limit($title, 120) : 'New Chat',
            'is_archived' => false,
        ]);

        return ApiResponse::success('Conversation created successfully', new AiConversationResource($conversation), 201);
    }

    public function showConversation(Request $request, AiChatConversation $conversation): JsonResponse
    {
        $this->assertAdminUser($request->user());
        $this->authorizeConversation($request->user(), $conversation);
        $conversation->load(['messages' => fn ($q) => $q->where('is_visible', true)->where('role', '!=', 'system')]);

        return ApiResponse::success('Conversation retrieved successfully', new AiConversationResource($conversation));
    }

    public function messages(Request $request, AiChatConversation $conversation): JsonResponse
    {
        $this->assertAdminUser($request->user());
        $this->authorizeConversation($request->user(), $conversation);
        $messages = $conversation->messages()
            ->where('is_visible', true)
            ->where('role', '!=', 'system')
            ->orderBy('id')
            ->get();

        return ApiResponse::success('Messages retrieved successfully', AiChatMessageResource::collection($messages));
    }

    public function updateConversation(Request $request, AiChatConversation $conversation): JsonResponse
    {
        $this->assertAdminUser($request->user());
        $this->authorizeConversation($request->user(), $conversation, true);
        $validated = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $conversation->forceFill(['title' => trim($validated['title'])])->save();

        return ApiResponse::success('Conversation updated successfully', new AiConversationResource($conversation));
    }

    public function destroyConversation(Request $request, AiChatConversation $conversation): JsonResponse
    {
        $this->assertAdminUser($request->user());
        $this->authorizeConversation($request->user(), $conversation, true);
        $conversation->update(['is_archived' => true]);

        return ApiResponse::success('Conversation archived successfully');
    }

    private function assertAdmin(Request $request): void
    {
        $this->assertAdminUser($request->user());
    }

    private function assertAdminUser($user): void
    {
        if (! $user || ! $user->hasAnyRole(['admin'])) {
            throw new AuthorizationException('The Admin AI assistant is available only to authorized administrators.');
        }
    }

    private function resolveOwnedConversation($user, $conversationId, string $message): AiChatConversation
    {
        if ($conversationId) {
            $conversation = AiChatConversation::query()->findOrFail($conversationId);
            $this->authorizeConversation($user, $conversation, true);

            return $conversation;
        }

        return AiChatConversation::create([
            'user_id' => $user->id,
            'title' => Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? $message) ?: 'New Chat', 120),
            'is_archived' => false,
        ]);
    }

    private function authorizeConversation($user, AiChatConversation $conversation, bool $own = false): void
    {
        Gate::authorize($own ? 'update' : 'view', $conversation);
        // Extra defense: admin assistant conversations must belong to this admin.
        if ((int) $conversation->user_id !== (int) $user->id) {
            throw new AuthorizationException('You are not authorized to access this conversation.');
        }
    }
}
