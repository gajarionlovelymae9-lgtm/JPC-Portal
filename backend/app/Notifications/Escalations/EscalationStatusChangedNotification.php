<?php

namespace App\Notifications\Escalations;

use App\Models\DepartmentEscalation;
use App\Models\User;
use App\Notifications\Tasks\JpcNotification;

/**
 * Alerts the originating Department Head (and the assigned administrator) when
 * an escalation moves through the workflow.
 */
class EscalationStatusChangedNotification extends JpcNotification
{
    public function __construct(
        protected DepartmentEscalation $escalation,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $status = $this->escalation->status;
        $type = $status->isTerminal() ? 'escalation_resolved' : 'escalation_updated';

        $message = $status->isTerminal() && $this->escalation->resolution_notes
            ? "This escalation is now {$status->label()}: {$this->escalation->resolution_notes}"
            : "This escalation is now {$status->label()}.";

        return [
            'type' => $type,
            'title' => "Escalation update: {$this->escalation->title}",
            'message' => $message,
            'data' => [
                'escalation_id' => $this->escalation->id,
                'status' => $status->value,
                'discriminator' => 'status:'.$status->value,
            ],
            'priority' => $status->isTerminal() ? 'normal' : 'urgent',
            'task_id' => null,
            'reminder_days' => null,
        ];
    }
}
