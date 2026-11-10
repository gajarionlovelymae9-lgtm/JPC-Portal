<?php

namespace App\Policies;

use App\Models\AiChatConversation;
use App\Models\User;

class AiChatConversationPolicy
{
    /**
     * AI access is restricted to Admin and Employee roles (enforced by route
     * middleware). HR/Department users must not access AI features.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole((array) config('ai.assistant.allowed_roles', ['admin', 'employee']));
    }

    public function view(User $user, AiChatConversation $conversation): bool
    {
        if (! $user->hasAnyRole((array) config('ai.assistant.allowed_roles', ['admin', 'employee']))) {
            return false;
        }

        return (int) $user->id === (int) $conversation->user_id || $user->hasAnyRole(['admin']);
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $this->ownsAllowedConversation($user, $conversation);
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $this->ownsAllowedConversation($user, $conversation);
    }

    private function ownsAllowedConversation(User $user, AiChatConversation $conversation): bool
    {
        if (! $user->hasAnyRole((array) config('ai.assistant.allowed_roles', ['admin', 'employee']))) {
            return false;
        }

        return (int) $user->id === (int) $conversation->user_id;
    }
}
