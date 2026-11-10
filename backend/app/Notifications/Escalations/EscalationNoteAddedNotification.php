<?php

namespace App\Notifications\Escalations;

use App\Models\DepartmentEscalation;
use App\Models\User;
use App\Notifications\Tasks\JpcNotification;

/**
 * Alerts the originating Department Head (and the assigned administrator) that
 * Administration has added a shared note and further action may be required.
 *
 * Internal notes are never sent through this notification.
 */
class EscalationNoteAddedNotification extends JpcNotification
{
    public function __construct(
        protected DepartmentEscalation $escalation,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        return [
            'type' => 'escalation_updated',
            'title' => 'Escalation note: '.$this->escalation->title,
            'message' => 'Administration added a note to this escalation. Please review the requested action.',
            'data' => [
                'escalation_id' => $this->escalation->id,
                'status' => $this->escalation->status->value,
                'discriminator' => 'note',
            ],
            'priority' => 'normal',
            'task_id' => null,
            'reminder_days' => null,
        ];
    }
}
