<?php

namespace App\Policies;

use App\Enums\Permissions;
use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    /**
     * Only users with notification access can list their inbox.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permissions::NOTIFICATIONS_VIEW);
    }

    /**
     * A user can only ever see their own notification.
     */
    public function view(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    /**
     * Marking a notification read is an owner-only operation.
     */
    public function update(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    /**
     * Deleting a notification is an owner-only operation. Deleting a row
     * removes the inbox entry but never the immutable activity/audit logs.
     */
    public function delete(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}