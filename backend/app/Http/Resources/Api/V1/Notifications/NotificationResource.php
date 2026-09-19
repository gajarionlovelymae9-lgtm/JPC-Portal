<?php

namespace App\Http\Resources\Api\V1\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    private const CATEGORY_TYPES = [
        'deadline' => ['deadline_reminder', 'task_overdue'],
        'review' => ['task_submitted', 'task_resubmitted', 'revision_required', 'task_approved', 'task_rejected'],
        'task' => ['task_assigned', 'task_cancelled'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data ?? [],
            'priority' => $this->priority ?? 'normal',
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'action_url' => $this->actionUrl(),
            'category' => $this->category(),
        ];
    }

    private function category(): string
    {
        foreach (self::CATEGORY_TYPES as $category => $types) {
            if (in_array($this->type, $types, true)) {
                return $category;
            }
        }

        return 'system';
    }

    /**
     * Deep link for the "view" action: employees are taken to their own task
     * detail page, reviewers/managers to the management task page.
     */
    private function actionUrl(): ?string
    {
        $taskId = $this->data['task_id'] ?? $this->task_id;

        if (! $taskId) {
            return null;
        }

        $role = $this->user?->roleName();

        return $role === 'employee' ? "/employee/tasks/{$taskId}" : "/tasks/{$taskId}";
    }
}