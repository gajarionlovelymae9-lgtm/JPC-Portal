<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\CreateConversationRequest;
use App\Http\Resources\Ai\AiChatMessageResource;
use App\Http\Resources\Ai\AiConversationResource;
use App\Models\AiChatConversation;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Gate;

class AiConversationController extends Controller
{
    public function index()
    {
        $search = trim((string) request()->query('q', ''));

        $conversations = AiChatConversation::where('user_id', auth()->id())
            ->where('is_archived', false)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%');
            })
            ->latest('updated_at')
            ->latest('id')
            ->paginate((int) request()->query('per_page', 15));

        return ApiResponse::success('Conversations retrieved successfully', AiConversationResource::collection($conversations));
    }

    public function store(CreateConversationRequest $request)
    {
        $conversation = AiChatConversation::create([
            'user_id' => auth()->id(),
            'title' => $request->validated('title') ?? 'New Conversation',
        ]);

        return ApiResponse::success('Conversation created successfully', new AiConversationResource($conversation), 201);
    }

    public function show(AiChatConversation $conversation)
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['messages' => function ($q) {
            $q->where('is_visible', true)->where('role', '!=', 'system');
        }]);

        return ApiResponse::success('Conversation retrieved successfully', new AiConversationResource($conversation));
    }

    /**
     * The visible, non-system messages of a conversation (owner only).
     * Used by the dashboard assistant to restore the current thread.
     */
    public function messages(AiChatConversation $conversation)
    {
        Gate::authorize('view', $conversation);

        $messages = $conversation->messages()
            ->where('is_visible', true)
            ->where('role', '!=', 'system')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            'Messages retrieved successfully',
            $messages->map(fn ($message) => (new AiChatMessageResource($message))->resolve())->all(),
        );
    }

    public function update(\Illuminate\Http\Request $request, AiChatConversation $conversation)
    {
        Gate::authorize('update', $conversation);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $conversation->forceFill([
            'title' => trim($validated['title']),
        ])->save();

        return ApiResponse::success('Conversation updated successfully', new AiConversationResource($conversation));
    }

    public function destroy(AiChatConversation $conversation)
    {
        Gate::authorize('delete', $conversation);

        $conversation->update(['is_archived' => true]);

        return ApiResponse::success('Conversation archived successfully');
    }
}
