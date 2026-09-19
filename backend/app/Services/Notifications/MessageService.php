<?php

namespace App\Services\Notifications;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Internal messaging between authorized users respecting role/department scope.
 */
class MessageService
{
    /**
     * List conversations for a user, ordered by most recent activity.
     */
    public function conversations(User $user): Collection
    {
        $conversationIds = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->pluck('conversation_id');

        return Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with(['participants.user:id,name,email', 'latestMessage.sender:id,name'])
            ->withCount([
                'messages as unread_count' => function ($q) use ($user) {
                    $q->where('created_at', '>', function ($sub) use ($user) {
                        $sub->select('last_read_at')
                            ->from('conversation_participants')
                            ->whereColumn('conversation_participants.conversation_id', 'messages.conversation_id')
                            ->where('conversation_participants.user_id', $user->id)
                            ->limit(1);
                    })->orWhereNull(
                        DB::raw("(SELECT last_read_at FROM conversation_participants WHERE conversation_participants.conversation_id = messages.conversation_id AND conversation_participants.user_id = {$user->id} LIMIT 1)")
                    );
                },
            ])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Get conversation detail with messages; mark as read.
     */
    public function getConversation(User $user, Conversation $conversation): Conversation
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            abort(403, 'You are not a participant in this conversation.');
        }

        // Mark as read
        $participant->update(['last_read_at' => now()]);

        return $conversation->load([
            'participants.user:id,name,email',
            'messages.sender:id,name',
        ]);
    }

    /**
     * Send a message. Creates or reuses conversation.
     */
    public function sendMessage(User $sender, array $data): Message
    {
        $recipientId = (int) $data['recipient_id'];
        $recipient = User::findOrFail($recipientId);

        $this->validateMessagingScope($sender, $recipient);

        return DB::transaction(function () use ($sender, $recipient, $data) {
            $conversation = $this->findOrCreateConversation($sender, $recipient, $data['subject'] ?? null);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $data['body'],
                'attachment_path' => $data['attachment_path'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null,
                'attachment_type' => $data['attachment_type'] ?? null,
            ]);

            $conversation->update(['last_message_at' => now()]);

            // Mark sender's participation as read
            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            return $message->load('sender:id,name');
        });
    }

    /**
     * Get authorized recipients for a user based on role/department scope.
     */
    public function authorizedRecipients(User $user): Collection
    {
        $roleName = $user->role ?? $user->roles->first()?->name ?? '';
        $deptId = $user->employee?->department_id;

        $query = User::query()
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->with('roles:id,name', 'employee:id,user_id,department_id');

        // Admin can message anyone
        if ($roleName === 'admin') {
            return $query->get(['id', 'name', 'email']);
        }

        // HR can message admin + all employees
        if ($roleName === 'hr') {
            return $query->get(['id', 'name', 'email']);
        }

        // Department Head can message own department + admin + HR
        if ($roleName === 'department_head') {
            return $query->where(function ($q) use ($deptId) {
                $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $deptId))
                  ->orWhereHas('roles', fn ($rq) => $rq->whereIn('name', ['admin', 'hr']));
            })->get(['id', 'name', 'email']);
        }

        // Employee can message own dept head + HR + admin
        return $query->where(function ($q) use ($deptId) {
            $q->whereHas('roles', fn ($rq) => $rq->whereIn('name', ['admin', 'hr', 'department_head']))
              ->when($deptId, fn ($sq) => $sq->orWhereHas('employee', fn ($eq) => $eq->where('department_id', $deptId)));
        })->get(['id', 'name', 'email']);
    }

    private function validateMessagingScope(User $sender, User $recipient): void
    {
        $authorized = $this->authorizedRecipients($sender);

        if (! $authorized->contains('id', $recipient->id)) {
            abort(403, 'You are not authorized to message this user.');
        }
    }

    private function findOrCreateConversation(User $sender, User $recipient, ?string $subject): Conversation
    {
        // Look for existing direct conversation between the two
        $existing = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $sender->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $recipient->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create([
            'subject' => $subject,
            'type' => 'direct',
            'created_by' => $sender->id,
            'last_message_at' => now(),
        ]);

        ConversationParticipant::insert([
            ['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'created_at' => now(), 'updated_at' => now()],
            ['conversation_id' => $conversation->id, 'user_id' => $recipient->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return $conversation;
    }
}
