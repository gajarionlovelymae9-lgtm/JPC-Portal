<?php

namespace App\Notifications\Escalations;

use App\Models\DepartmentEscalation;
use App\Models\User;
use App\Notifications\Tasks\JpcNotification;

/**
 * Alerts the responsible administrator that an escalation was assigned to
 * them, and the Department Head that the escalation is being handled.
 */
class EscalationAssignedNotification extends JpcNotification
{
    public function __construct(
        protected DepartmentEscalation $escalation,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $assignee = $this->escalation->assignee?->name ?? 'Administration';

        return [
            'type' => 'escalation_updated',
            'title' => 'Escalation assigned: '.$this->escalation->title,
            'message' => "This escalation has been assigned to {$assignee} for review.",
            'data' => [
                'escalation_id' => $this->escalation->id,
                'assigned_to' => $this->escalation->assigned_to,
                'status' => $this->escalation->status->value,
                'discriminator' => 'assigned:'.($this->escalation->assigned_to ?? 0),
            ],
            'priority' => 'normal',
            'task_id' => null,
            'reminder_days' => null,
        ];
    }
}
