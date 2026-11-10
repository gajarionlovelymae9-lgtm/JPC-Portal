<?php

namespace App\Notifications\Messages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Tasks\JpcNotification;

/**
 * In-app alert for a new internal message.
 *
 * Sent once per recipient per message through the JPC inbox channel, which
 * applies the recipient's notification preferences (the `message_received`
 * event is optional, so a user who muted message alerts is skipped). No
 * task/reminder metadata is emitted, so the notifications unique index never
 * rejects a legitimate second message.
 */
class NewMessageNotification extends JpcNotification
{
    public function __construct(
        protected Conversation $conversation,
        protected Message $message,
        protected User $sender,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        $isGroup = $this->conversation->isGroup();
        $label = $isGroup
            ? ($this->conversation->subject ?: 'Group conversation')
            : $this->sender->name;

        $preview = mb_strimwidth((string) $this->message->body, 0, 120, '…');

        if ($preview === '' && $this->message->attachment_name) {
            $preview = 'Sent an attachment: '.$this->message->attachment_name;
        }

        return [
            'type' => 'message_received',
            'title' => $isGroup ? "New message in {$label}" : "New message from {$label}",
            'message' => $preview !== '' ? $preview : 'New message received',
            'data' => [
                'action' => 'open_conversation',
                'record_id' => $this->conversation->id,
                'conversation_id' => $this->conversation->id,
                'message_id' => $this->message->id,
                'sender_id' => $this->sender->id,
                'sender_name' => $this->sender->name,
                'is_group' => $isGroup,
            ],
            'priority' => 'normal',
            'task_id' => null,
            'reminder_days' => null,
        ];
    }
}
