<?php

namespace App\Services\Escalations;

use App\Enums\NotificationEvent;
use App\Models\DepartmentEscalation;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Escalations\EscalationAssignedNotification;
use App\Notifications\Escalations\EscalationCreatedNotification;
use App\Notifications\Escalations\EscalationNoteAddedNotification;
use App\Notifications\Escalations\EscalationStatusChangedNotification;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;

/**
 * Sends escalation notifications through the existing JPC notification inbox.
 *
 * Delivery is idempotent: before dispatching, the existence of a matching
 * (user, type, escalation, discriminator) row is checked, so re-running the
 * workflow never duplicates a notification. Non-critical events honour the
 * user's stored notification preferences; critical events (escalation created
 * / resolved) are always delivered per institutional policy.
 */
class EscalationNotificationService
{
    public function notifyCreated(DepartmentEscalation $escalation): void
    {
        $discriminator = 'created';

        foreach ($this->administrators() as $admin) {
            $this->sendOnce(
                $admin,
                new EscalationCreatedNotification($escalation, $admin),
                'escalation_created',
                $escalation->id,
                $discriminator,
            );
        }
    }

    public function notifyAssigned(DepartmentEscalation $escalation): void
    {
        $discriminator = 'assigned:'.($escalation->assigned_to ?? 0);

        foreach ($this->participants($escalation) as $user) {
            $this->sendOnce(
                $user,
                new EscalationAssignedNotification($escalation, $user),
                'escalation_updated',
                $escalation->id,
                $discriminator,
            );
        }
    }

    public function notifyStatusChanged(DepartmentEscalation $escalation): void
    {
        $type = $escalation->status->isTerminal() ? 'escalation_resolved' : 'escalation_updated';
        $discriminator = 'status:'.$escalation->status->value;

        foreach ($this->participants($escalation) as $user) {
            $this->sendOnce(
                $user,
                new EscalationStatusChangedNotification($escalation, $user),
                $type,
                $escalation->id,
                $discriminator,
            );
        }
    }

    /**
     * Alerts participants that a shared review note was added. The unique
     * discriminator keeps each note's notification distinct while remaining
     * idempotent if the same note is ever re-dispatched.
     */
    public function notifyNoteAdded(DepartmentEscalation $escalation, string $discriminator = 'note'): void
    {
        foreach ($this->participants($escalation) as $user) {
            $this->sendOnce(
                $user,
                new EscalationNoteAddedNotification($escalation, $user),
                'escalation_updated',
                $escalation->id,
                $discriminator,
            );
        }
    }

    /**
     * The Department Head who raised the escalation plus the assigned
     * administrator.
     *
     * @return Collection<int, User>
     */
    private function participants(DepartmentEscalation $escalation): Collection
    {
        return collect([$escalation->creator, $escalation->assignee])
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Administrators that receive new-escalation alerts.
     *
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        $roleNames = config('escalation.assignable_roles', ['admin']);
        $roleIds = Role::query()->whereIn('name', $roleNames)->pluck('id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return User::query()->whereIn('role_id', $roleIds)->get();
    }

    private function sendOnce(
        User $user,
        BaseNotification $notification,
        string $type,
        int $escalationId,
        string $discriminator,
    ): void {
        if (! $this->wantsInApp($user, $type)) {
            return;
        }

        if ($this->alreadySent($user, $type, $escalationId, $discriminator)) {
            return;
        }

        $user->notify($notification);
    }

    /**
     * Honour the user's stored preference for non-critical events.
     */
    private function wantsInApp(User $user, string $type): bool
    {
        $event = NotificationEvent::tryFrom($type);

        if ($event?->isCritical()) {
            return true;
        }

        $preference = NotificationPreference::query()
            ->forUser($user->id)
            ->forEvent($type)
            ->first();

        return $preference ? $preference->in_app_enabled : true;
    }

    private function alreadySent(User $user, string $type, int $escalationId, string $discriminator): bool
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('data->escalation_id', $escalationId)
            ->where('data->discriminator', $discriminator)
            ->exists();
    }
}
