<?php

namespace App\Services\Notifications;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Messages\NewMessageNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Internal messaging between authorized users respecting role/department scope.
 *
 * Backed entirely by the existing `conversations`, `conversation_participants`
 * and `messages` tables (extended additively for group metadata, per-user
 * archiving, quoted replies and idempotent submits). No parallel messaging
 * tables exist and every read is membership-guarded by ConversationPolicy.
 */
class MessageService
{
    /** Conversation list filters exposed by the left panel. */
    public const FILTERS = ['all', 'unread', 'archived', 'private', 'group'];

    /**
     * List the caller's conversations, ordered by most recent activity.
     *
     * @param  array{filter?: string, search?: string}  $filters
     * @return Collection<int, Conversation>
     */
    public function conversations(User $user, array $filters = []): Collection
    {
        $filter = in_array($filters['filter'] ?? 'all', self::FILTERS, true)
            ? $filters['filter']
            : 'all';

        $participations = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('conversation_id');

        $query = Conversation::query()
            ->whereIn('id', $participations->keys())
            ->with([
                'participants.user:id,name,email',
                'participants.user.employee:id,user_id,department_id,position_id,profile_photo_path',
                'participants.user.employee.department:id,name',
                'participants.user.employee.position:id,title',
                'latestMessage.sender:id,name',
            ])
            ->select('conversations.*')
            ->addSelect(DB::raw($this->unreadCountSubquery($user)));

        match ($filter) {
            'archived' => $query->whereIn('id', $participations->filter(fn ($p) => $p->archived_at !== null)->keys()),
            'private' => $query->where('type', Conversation::TYPE_DIRECT)->whereIn('id', $participations->filter(fn ($p) => $p->archived_at === null)->keys()),
            'group' => $query->where('type', Conversation::TYPE_GROUP)->whereIn('id', $participations->filter(fn ($p) => $p->archived_at === null)->keys()),
            default => $query->whereIn('id', $participations->filter(fn ($p) => $p->archived_at === null)->keys()),
        };

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('subject', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('participants.user', fn ($uq) => $uq->where('name', 'like', $like))
                    ->orWhereHas('messages', fn ($mq) => $mq->where('body', 'like', $like));
            });
        }

        $conversations = $query
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get();

        // "Unread" keeps only threads the caller has unread messages in.
        if ($filter === 'unread') {
            $conversations = $conversations->filter(fn (Conversation $c) => (int) $c->unread_count > 0)->values();
        }

        return $conversations;
    }

    /**
     * Unread badge totals for the sidebar and the notification bell.
     *
     * @return array{unread_conversations: int, unread_messages: int}
     */
    public function unreadSummary(User $user): array
    {
        $participations = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->pluck('last_read_at', 'conversation_id');

        if ($participations->isEmpty()) {
            return ['unread_conversations' => 0, 'unread_messages' => 0];
        }

        $counts = Message::query()
            ->whereIn('conversation_id', $participations->keys())
            ->where('sender_id', '!=', $user->id)
            ->get(['conversation_id', 'created_at'])
            ->groupBy('conversation_id')
            ->map(function (Collection $messages, $conversationId) use ($participations): int {
                $lastRead = $participations[$conversationId] ?? null;
                $lastReadTs = $lastRead !== null ? strtotime((string) $lastRead) : null;

                return $messages->filter(
                    fn (Message $message) => $lastReadTs === null || $message->created_at->timestamp > $lastReadTs,
                )->count();
            })
            ->filter(fn (int $count) => $count > 0);

        return [
            'unread_conversations' => $counts->count(),
            'unread_messages' => (int) $counts->sum(),
        ];
    }

    /**
     * Conversation detail for the right pane. Marks the thread as read and
     * (optionally) returns only messages newer than `since_id` for polling.
     *
     * @return array<string, mixed>
     */
    public function conversationDetail(User $user, Conversation $conversation, ?int $sinceId = null): array
    {
        $this->assertParticipant($user, $conversation);
        $this->markRead($user, $conversation);

        $conversation->load([
            'participants.user:id,name,email',
            'participants.user.employee:id,user_id,department_id,position_id,profile_photo_path',
            'participants.user.employee.department:id,name',
            'participants.user.employee.position:id,title',
            'participants.user.role:id,name',
            'messages' => fn ($q) => $q->with(['sender:id,name', 'replyTo.sender:id,name'])
                ->when($sinceId !== null, fn ($mq) => $mq->where('id', '>', $sinceId)),
        ]);

        $participants = $conversation->participants;
        $messages = $conversation->messages->values();

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'description' => $conversation->description,
            'icon' => $conversation->icon,
            'type' => $conversation->type,
            'is_group' => $conversation->isGroup(),
            'created_by' => (int) $conversation->created_by,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'participants' => $participants->map(fn (ConversationParticipant $p) => [
                'user' => $this->userPayload($p->user),
                'role' => $p->role,
                'is_admin' => $p->isAdmin(),
                'last_read_at' => $p->last_read_at?->toIso8601String(),
                'archived' => $p->archived_at !== null,
            ])->values(),
            'messages' => $messages->map(fn (Message $m) => $this->messagePayload($user, $m, $participants))->values(),
            'read_receipts' => $participants
                ->reject(fn (ConversationParticipant $p) => (int) $p->user_id === (int) $user->id)
                ->map(fn (ConversationParticipant $p) => [
                    'user_id' => (int) $p->user_id,
                    'name' => $p->user?->name,
                    'last_read_at' => $p->last_read_at?->toIso8601String(),
                ])->values(),
        ];
    }

    /**
     * Send a message into an existing conversation or a direct thread.
     *
     * Duplicate submissions are prevented with the client-generated
     * `client_token`: a replayed submit returns the already-persisted message
     * instead of creating a second one.
     */
    public function sendMessage(User $sender, array $data, ?UploadedFile $attachment = null): Message
    {
        $clientToken = $data['client_token'] ?? null;

        if ($clientToken !== null) {
            $existing = Message::query()
                ->where('client_token', $clientToken)
                ->where('sender_id', $sender->id)
                ->first();

            if ($existing) {
                return $existing->load(['sender:id,name', 'replyTo.sender:id,name']);
            }
        }

        return DB::transaction(function () use ($sender, $data, $attachment, $clientToken) {
            $conversation = $this->resolveTargetConversation($sender, $data);

            $stored = $this->storeAttachment($attachment);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => (string) ($data['body'] ?? ''),
                'reply_to_message_id' => $this->resolveReplyTarget($conversation, $data['reply_to_message_id'] ?? null),
                'attachment_path' => $stored['path'] ?? null,
                'attachment_name' => $stored['name'] ?? null,
                'attachment_type' => $stored['type'] ?? null,
                'client_token' => $clientToken,
            ]);

            $conversation->update(['last_message_at' => now()]);

            // A new message revives the thread for everyone and clears the
            // sender's own unread marker.
            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->update(['archived_at' => null]);

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            $message->load(['sender:id,name', 'replyTo.sender:id,name']);

            $this->notifyParticipants($conversation, $message, $sender);

            return $message;
        });
    }

    /**
     * Create a group conversation with the caller as its administrator.
     */
    public function createGroup(User $creator, array $data): Conversation
    {
        $participantIds = $this->authorizeParticipantIds($creator, $data['participant_ids'] ?? []);

        return DB::transaction(function () use ($creator, $data, $participantIds) {
            $conversation = Conversation::create([
                'subject' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'type' => Conversation::TYPE_GROUP,
                'created_by' => $creator->id,
                'last_message_at' => now(),
            ]);

            $now = now();

            $rows = [[
                'conversation_id' => $conversation->id,
                'user_id' => $creator->id,
                'role' => ConversationParticipant::ROLE_ADMIN,
                'created_at' => $now,
                'updated_at' => $now,
            ]];

            foreach ($participantIds as $id) {
                $rows[] = [
                    'conversation_id' => $conversation->id,
                    'user_id' => $id,
                    'role' => ConversationParticipant::ROLE_MEMBER,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ConversationParticipant::insert($rows);

            return $conversation->load('participants.user:id,name,email');
        });
    }

    /**
     * Add members to a group (group administrators / creator only).
     */
    public function addParticipants(User $user, Conversation $conversation, array $participantIds): int
    {
        $this->assertCanManage($user, $conversation);

        $authorized = $this->authorizeParticipantIds($user, $participantIds);

        $existing = $conversation->participants()->pluck('user_id')->all();
        $toAdd = array_values(array_diff($authorized, $existing));

        if ($toAdd === []) {
            return 0;
        }

        $now = now();
        ConversationParticipant::insert(array_map(fn (int $id) => [
            'conversation_id' => $conversation->id,
            'user_id' => $id,
            'role' => ConversationParticipant::ROLE_MEMBER,
            'created_at' => $now,
            'updated_at' => $now,
        ], $toAdd));

        return count($toAdd);
    }

    /**
     * Remove a member from a group. The creator can never be removed and an
     * administrator cannot remove themselves here (they leave instead).
     */
    public function removeParticipant(User $user, Conversation $conversation, int $participantUserId): void
    {
        $this->assertCanManage($user, $conversation);

        if ($participantUserId === (int) $conversation->created_by) {
            abort(422, 'The group creator cannot be removed.');
        }

        if ($participantUserId === (int) $user->id) {
            abort(422, 'Use "Leave group" to remove yourself.');
        }

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $participantUserId)
            ->delete();
    }

    /**
     * Update a group's name, description or icon.
     */
    public function updateGroup(User $user, Conversation $conversation, array $data): Conversation
    {
        $this->assertCanManage($user, $conversation);

        $conversation->update(array_filter([
            'subject' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
        ], fn ($value) => $value !== null));

        return $conversation->load('participants.user:id,name,email');
    }

    /**
     * Leave a group conversation (removes the caller's membership).
     */
    public function leaveConversation(User $user, Conversation $conversation): void
    {
        $this->assertParticipant($user, $conversation);

        if (! $conversation->isGroup()) {
            // Direct threads cannot be "left"; archiving is the equivalent.
            $this->setArchived($user, $conversation, true);

            return;
        }

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Archive / unarchive a thread for the caller only.
     */
    public function setArchived(User $user, Conversation $conversation, bool $archived): void
    {
        $this->assertParticipant($user, $conversation);

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['archived_at' => $archived ? now() : null]);
    }

    /**
     * Mark every message in the conversation as read for the caller.
     */
    public function markRead(User $user, Conversation $conversation): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Authorized recipients for the People Directory.
     *
     * Scope mirrors the institutional hierarchy: Admin/HR reach everyone,
     * Department Heads reach their own department plus Admin/HR, and Employees
     * reach Admin/HR/Department Heads plus colleagues in their department.
     * Only active accounts are ever listed.
     *
     * @param  array{search?: string, role?: string, department_id?: int}  $filters
     * @return Collection<int, User>
     */
    public function authorizedRecipients(User $user, array $filters = []): Collection
    {
        $roleName = $user->roleName() ?? '';
        $deptId = $user->employee?->department_id;

        $query = User::query()
            ->where('id', '!=', $user->id)
            ->where('status', 'active')
            ->with([
                'role:id,name,label',
                'employee:id,user_id,department_id,position_id,profile_photo_path',
                'employee.department:id,name',
                'employee.position:id,title',
            ]);

        if ($roleName !== 'admin' && $roleName !== 'hr') {
            $query->where(function ($q) use ($roleName, $deptId): void {
                if ($roleName === 'department_head') {
                    $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $deptId))
                        ->orWhereHas('role', fn ($rq) => $rq->whereIn('name', ['admin', 'hr']));
                } else {
                    $q->whereHas('role', fn ($rq) => $rq->whereIn('name', ['admin', 'hr', 'department_head']))
                        ->when($deptId, fn ($sq) => $sq->orWhereHas('employee', fn ($eq) => $eq->where('department_id', $deptId)));
                }
            });
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereHas('role', fn ($rq) => $rq->where('name', 'like', $like)->orWhere('label', 'like', $like))
                    ->orWhereHas('employee.department', fn ($dq) => $dq->where('name', 'like', $like))
                    ->orWhereHas('employee.position', fn ($pq) => $pq->where('title', 'like', $like));
            });
        }

        if (! empty($filters['role'])) {
            $query->whereHas('role', fn ($rq) => $rq->where('name', $filters['role']));
        }

        if (! empty($filters['department_id'])) {
            $query->whereHas('employee', fn ($eq) => $eq->where('department_id', (int) $filters['department_id']));
        }

        return $query->orderBy('name')->get(['id', 'name', 'email', 'status']);
    }

    /**
     * Stream a message attachment to an authorised participant.
     */
    public function downloadAttachment(User $user, Message $message): StreamedResponse
    {
        $this->assertParticipant($user, $message->conversation);

        abort_if(blank($message->attachment_path), 404, 'This message has no attachment.');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($message->attachment_path), 404, 'Attachment file not found.');

        return $disk->download($message->attachment_path, $message->attachment_name ?? 'attachment');
    }

    /**
     * Public directory payload for one user row.
     *
     * @return array<string, mixed>
     */
    public function directoryPayload(User $u): array
    {
        return [
            'id' => (int) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role?->name ?? $u->roleName() ?? '',
            'role_label' => $u->role?->label ?? '',
            'department' => $u->employee?->department?->name,
            'position' => $u->employee?->position?->title,
            'status' => is_string($u->status) ? $u->status : $u->status?->value,
            'avatar_url' => $u->employee?->profile_photo_path
                ? Storage::disk('public')->url($u->employee->profile_photo_path)
                : null,
            'initials' => $this->initials($u->name),
        ];
    }

    // ── internals ────────────────────────────────────────────────

    private function resolveTargetConversation(User $sender, array $data): Conversation
    {
        if (! empty($data['conversation_id'])) {
            $conversation = Conversation::findOrFail($data['conversation_id']);
            $this->assertParticipant($sender, $conversation);

            return $conversation;
        }

        $recipient = User::findOrFail($data['recipient_id']);
        $this->assertMessagingScope($sender, $recipient);

        return $this->findOrCreateDirectConversation($sender, $recipient, $data['subject'] ?? null);
    }

    private function findOrCreateDirectConversation(User $sender, User $recipient, ?string $subject): Conversation
    {
        $existing = Conversation::query()
            ->where('type', Conversation::TYPE_DIRECT)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $sender->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $recipient->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create([
            'subject' => $subject,
            'type' => Conversation::TYPE_DIRECT,
            'created_by' => $sender->id,
            'last_message_at' => now(),
        ]);

        $now = now();

        ConversationParticipant::insert([
            ['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'role' => ConversationParticipant::ROLE_MEMBER, 'created_at' => $now, 'updated_at' => $now],
            ['conversation_id' => $conversation->id, 'user_id' => $recipient->id, 'role' => ConversationParticipant::ROLE_MEMBER, 'created_at' => $now, 'updated_at' => $now],
        ]);

        return $conversation;
    }

    private function resolveReplyTarget(Conversation $conversation, ?int $replyToId): ?int
    {
        if ($replyToId === null) {
            return null;
        }

        $exists = Message::query()
            ->where('id', $replyToId)
            ->where('conversation_id', $conversation->id)
            ->exists();

        return $exists ? $replyToId : null;
    }

    /**
     * @return array{path: string, name: string, type: string}|array{}
     */
    private function storeAttachment(?UploadedFile $attachment): array
    {
        if ($attachment === null) {
            return [];
        }

        $path = $attachment->store('messages/attachments', 'local');

        return [
            'path' => $path,
            'name' => $attachment->getClientOriginalName(),
            'type' => $attachment->getClientMimeType(),
        ];
    }

    /**
     * Notify every other participant. The JPC inbox channel automatically
     * honours per-user notification preferences for the `message_received`
     * event, and one alert is emitted per message per recipient so a message
     * is never double-notified.
     */
    private function notifyParticipants(Conversation $conversation, Message $message, User $sender): void
    {
        $conversation->participants()
            ->where('user_id', '!=', $sender->id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->each(fn (User $recipient) => $recipient->notify(
                new NewMessageNotification($conversation, $message, $sender),
            ));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function authorizeParticipantIds(User $user, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            abort(422, 'Select at least one participant.');
        }

        $authorized = $this->authorizedRecipients($user)->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($ids as $id) {
            if (! in_array($id, $authorized, true)) {
                abort(403, 'You are not authorized to message one of the selected users.');
            }
        }

        return $ids;
    }

    private function assertMessagingScope(User $sender, User $recipient): void
    {
        if ((int) $sender->id === (int) $recipient->id) {
            abort(422, 'You cannot message yourself.');
        }

        if (! $this->authorizedRecipients($sender)->contains('id', $recipient->id)) {
            abort(403, 'You are not authorized to message this user.');
        }
    }

    private function assertParticipant(User $user, ?Conversation $conversation): void
    {
        abort_if($conversation === null, 404, 'Conversation not found.');

        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($isParticipant, 403, 'You are not a participant in this conversation.');
    }

    private function assertCanManage(User $user, Conversation $conversation): void
    {
        abort_unless(
            Gate::forUser($user)->allows('manageParticipants', $conversation),
            403,
            'Only group administrators can manage this group.',
        );
    }

    /**
     * @param  Collection<int, ConversationParticipant>  $participants
     * @return array<string, mixed>
     */
    private function messagePayload(User $user, Message $message, Collection $participants): array
    {
        $isMine = (int) $message->sender_id === (int) $user->id;

        $readBy = $participants
            ->reject(fn (ConversationParticipant $p) => (int) $p->user_id === (int) $user->id)
            ->filter(fn (ConversationParticipant $p) => $p->last_read_at !== null && $p->last_read_at >= $message->created_at)
            ->count();

        return [
            'id' => $message->id,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'is_mine' => $isMine,
            'sender' => [
                'id' => (int) $message->sender_id,
                'name' => $message->sender?->name,
            ],
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'body' => mb_strimwidth((string) $message->replyTo->body, 0, 120, '…'),
                'sender_name' => $message->replyTo->sender?->name,
            ] : null,
            'attachment' => $message->attachment_path ? [
                'name' => $message->attachment_name,
                'type' => $message->attachment_type,
                'url' => '/messages/'.$message->id.'/attachment',
            ] : null,
            'read_by_count' => $readBy,
            'is_read' => $isMine ? $readBy > 0 : true,
        ];
    }

    /**
     * Correlated subquery returning the caller's unread message count for a
     * conversation. Database-portable (MySQL + SQLite) and immune to the
     * fragile nested-subselect pattern it replaces.
     */
    private function unreadCountSubquery(User $user): string
    {
        $uid = (int) $user->id;

        return '(SELECT COUNT(*) FROM messages m '
            .'WHERE m.conversation_id = conversations.id '
            ."AND m.sender_id <> {$uid} "
            .'AND m.created_at > COALESCE(('
            .'SELECT cp.last_read_at FROM conversation_participants cp '
            ."WHERE cp.conversation_id = conversations.id AND cp.user_id = {$uid} LIMIT 1"
            ."), '1970-01-01 00:00:00')) AS unread_count";
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(?User $u): array
    {
        if ($u === null) {
            return ['id' => 0, 'name' => 'Unknown', 'email' => null, 'role' => '', 'department' => null, 'position' => null, 'avatar_url' => null];
        }

        return [
            'id' => (int) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role?->name ?? '',
            'department' => $u->employee?->department?->name,
            'position' => $u->employee?->position?->title,
            'avatar_url' => $u->employee?->profile_photo_path
                ? Storage::disk('public')->url($u->employee->profile_photo_path)
                : null,
        ];
    }

    private function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        $letters = array_map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: 'U';
    }
}
