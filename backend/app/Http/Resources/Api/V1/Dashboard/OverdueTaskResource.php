<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class OverdueTaskResource extends JsonResource
{
    public function toArray($request): array
    {
        $daysOverdue = $this->due_date !== null
            ? max(0, (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay()))
            : null;

        return [
            'task_id' => $this->id,
            'task_code' => $this->task_code,
            'title' => $this->title,
            'priority' => $this->priority,
            'status' => $this->status,
            'department_id' => $this->department?->id,
            'department_name' => $this->department?->name,
            'due_date' => $this->due_date?->toDateString(),
            'days_overdue' => $daysOverdue,
            'assignee_names' => $this->whenLoaded(
                'assignments',
                fn () => $this->assignments
                    ->map(fn ($assignment) => $assignment->employee?->fullName)
                    ->filter()
                    ->values()
                    ->all()
            ),
        ];
    }
}