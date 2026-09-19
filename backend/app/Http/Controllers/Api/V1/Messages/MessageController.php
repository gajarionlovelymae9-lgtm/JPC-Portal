<?php

namespace App\Http\Controllers\Api\V1\Messages;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\Notifications\MessageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $conversations = $this->messageService->conversations($request->user());

        return ApiResponse::success('Conversations retrieved', $conversations);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $detail = $this->messageService->getConversation($request->user(), $conversation);

        return ApiResponse::success('Conversation retrieved', $detail);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string|max:5000',
        ]);

        $message = $this->messageService->sendMessage(
            $request->user(),
            $request->only(['recipient_id', 'subject', 'body']),
        );

        return ApiResponse::success('Message sent', $message, 201);
    }

    public function markRead(Request $request, Message $message): JsonResponse
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $participant) {
            abort(403, 'Unauthorized');
        }

        $participant->update(['last_read_at' => now()]);

        return ApiResponse::success('Conversation marked as read');
    }

    public function recipients(Request $request): JsonResponse
    {
        $recipients = $this->messageService->authorizedRecipients($request->user());

        $mapped = $recipients->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->roles->first()?->name ?? '',
            'department' => $u->employee?->department?->name ?? null,
        ]);

        return ApiResponse::success('Recipients retrieved', $mapped->values());
    }
}
