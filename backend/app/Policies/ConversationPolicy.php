<?php

namespace App\Policies;

use App\Enums\Permissions;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Authorization for the internal-messaging module.
 *
 * Every rule is membership-based: a user can only ever read or write a
 * conversation they participate in, and only the group creator/administrators
 * may manage a group's membership. The route middleware already gates the
 * module behind `notifications.view`; this policy closes the per-conversation
 * gaps so a URL guessing attack cannot reach another user's thread.
 */
class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permissions::NOTIFICATIONS_VIEW);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->participates($user, $conversation);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->participates($user, $conversation);
    }

    /**
     * Adding or removing group members. Only group creators/administrators may
     * change membership; direct threads have no membership to manage.
     */
    public function manageParticipants(User $user, Conversation $conversation): bool
    {
        if (! $conversation->isGroup()) {
            return false;
        }

        $participant = $this->participant($user, $conversation);

        if (! $participant) {
            return false;
        }

        return $participant->isAdmin() || (int) $conversation->created_by === (int) $user->id;
    }

    /**
     * Renaming / re-describing a group. Same rules as membership management.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $this->manageParticipants($user, $conversation);
    }

    /**
     * Leaving a group or archiving a thread is available to any participant.
     */
    public function leave(User $user, Conversation $conversation): bool
    {
        return $this->participates($user, $conversation);
    }

    private function participates(User $user, Conversation $conversation): bool
    {
        return $this->participant($user, $conversation) !== null;
    }

    private function participant(User $user, Conversation $conversation): ?ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
