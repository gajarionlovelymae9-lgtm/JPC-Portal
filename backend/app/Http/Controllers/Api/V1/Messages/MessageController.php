<?php

namespace App\Http\Controllers\Api\V1\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Messages\AddGroupParticipantsRequest;
use App\Http\Requests\Api\V1\Messages\StoreGroupConversationRequest;
use App\Http\Requests\Api\V1\Messages\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\Notifications\MessageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Internal messaging API: people directory, private + group conversations,
 * message threads, read receipts, archiving and secure attachments.
 *
 * Access is gated twice: the route middleware requires `notifications.view`,
 * and every conversation-scoped action is authorised through ConversationPolicy
 * (membership) so a user can never reach a thread they do not belong to.
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $conversations = $this->messageService->conversations($request->user(), [
            'filter' => (string) $request->input('filter', 'all'),
            'search' => (string) $request->input('search', ''),
        ]);

        return ApiResponse::success(
            'Conversations retrieved',
            $conversations->map(fn (Conversation $c) => $this->summaryPayload($c, (int) $request->user()->id))->values(),
        );
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $sinceId = $request->filled('since_id') ? (int) $request->input('since_id') : null;

        return ApiResponse::success(
            'Conversation retrieved',
            $this->messageService->conversationDetail($request->user(), $conversation, $sinceId),
        );
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $message = $this->messageService->sendMessage(
            $request->user(),
            $request->safe()->only(['conversation_id', 'recipient_id', 'subject', 'body', 'reply_to_message_id', 'client_token']),
            $request->file('attachment'),
        );

        return ApiResponse::success('Message sent', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
        ], 201);
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        return ApiResponse::success('Unread summary retrieved', $this->messageService->unreadSummary($request->user()));
    }

    public function recipients(Request $request): JsonResponse
    {
        $recipients = $this->messageService->authorizedRecipients($request->user(), [
            'search' => (string) $request->input('search', ''),
            'role' => $request->input('role'),
            'department_id' => $request->input('department_id'),
        ]);

        return ApiResponse::success(
            'Recipients retrieved',
            $recipients->map(fn ($u) => $this->messageService->directoryPayload($u))->values(),
        );
    }

    public function storeGroup(StoreGroupConversationRequest $request): JsonResponse
    {
        $conversation = $this->messageService->createGroup($request->user(), $request->validated());

        return ApiResponse::success(
            'Group created',
            $this->summaryPayload($conversation, (int) $request->user()->id),
            201,
        );
    }

    public function updateGroup(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:16'],
        ]);

        $updated = $this->messageService->updateGroup($request->user(), $conversation, $data);

        return ApiResponse::success('Group updated', $this->summaryPayload($updated, (int) $request->user()->id));
    }

    public function addParticipants(AddGroupParticipantsRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('manageParticipants', $conversation);

        $added = $this->messageService->addParticipants(
            $request->user(),
            $conversation,
            $request->validated()['participant_ids'],
        );

        return ApiResponse::success('Participants added', ['added' => $added]);
    }

    public function removeParticipant(Request $request, Conversation $conversation, int $user): JsonResponse
    {
        Gate::authorize('manageParticipants', $conversation);

        $this->messageService->removeParticipant($request->user(), $conversation, $user);

        return ApiResponse::success('Participant removed');
    }

    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('leave', $conversation);

        $this->messageService->leaveConversation($request->user(), $conversation);

        return ApiResponse::success('Conversation left');
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('leave', $conversation);

        $archived = $request->boolean('archived', true);
        $this->messageService->setArchived($request->user(), $conversation, $archived);

        return ApiResponse::success($archived ? 'Conversation archived' : 'Conversation restored');
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $this->messageService->markRead($request->user(), $conversation);

        return ApiResponse::success('Conversation marked as read');
    }

    /**
     * Legacy per-message read endpoint kept for backwards compatibility; it
     * marks the whole conversation read for the caller.
     */
    public function markMessageRead(Request $request, Message $message): JsonResponse
    {
        Gate::authorize('view', $message->conversation);

        $this->messageService->markRead($request->user(), $message->conversation);

        return ApiResponse::success('Conversation marked as read');
    }

    public function downloadAttachment(Request $request, Message $message): StreamedResponse
    {
        Gate::authorize('view', $message->conversation);

        return $this->messageService->downloadAttachment($request->user(), $message);
    }

    /**
     * List-panel payload: conversation meta + the counterpart/group summary.
     *
     * @return array<string, mixed>
     */
    private function summaryPayload(Conversation $conversation, int $viewerId): array
    {
        $participants = $conversation->participants;

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'description' => $conversation->description,
            'icon' => $conversation->icon,
            'type' => $conversation->type,
            'is_group' => $conversation->isGroup(),
            'created_by' => (int) $conversation->created_by,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'participants' => $participants->map(fn (ConversationParticipant $p) => [
                'user' => [
                    'id' => (int) $p->user_id,
                    'name' => $p->user?->name,
                    'email' => $p->user?->email,
                ],
                'role' => $p->role,
                'is_admin' => $p->isAdmin(),
                'last_read_at' => $p->last_read_at?->toIso8601String(),
            ])->values(),
            'latest_message' => $conversation->latestMessage ? [
                'id' => $conversation->latestMessage->id,
                'body' => $conversation->latestMessage->body,
                'sender' => [
                    'id' => (int) $conversation->latestMessage->sender_id,
                    'name' => $conversation->latestMessage->sender?->name,
                ],
                'created_at' => $conversation->latestMessage->created_at?->toIso8601String(),
            ] : null,
            'viewer_id' => $viewerId,
        ];
    }
}
