<?php

namespace App\Notifications\Escalations;

use App\Models\DepartmentEscalation;
use App\Models\User;
use App\Notifications\Tasks\JpcNotification;

/**
 * Alerts Administration that a Department Head has raised a new escalation.
 */
class EscalationCreatedNotification extends JpcNotification
{
    public function __construct(
        protected DepartmentEscalation $escalation,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $department = $this->escalation->department?->name ?? 'Department';
        $creator = $this->escalation->creator?->name ?? 'Department Head';

        return [
            'type' => 'escalation_created',
            'title' => 'New escalation: '.$this->escalation->title,
            'message' => "{$department} raised an escalation ({$this->escalation->priority->label()} priority) submitted by {$creator}.",
            'data' => [
                'escalation_id' => $this->escalation->id,
                'department' => $department,
                'priority' => $this->escalation->priority->value,
                'status' => $this->escalation->status->value,
                'discriminator' => 'created',
            ],
            'priority' => $this->escalation->priority->value === 'critical' ? 'urgent' : 'normal',
            'task_id' => null,
            'reminder_days' => null,
        ];
    }
}
