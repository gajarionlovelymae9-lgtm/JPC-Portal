<?php

namespace App\Http\Resources\Api\V1\TaskManagement;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskAssignmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'status' => $this->status,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'due_date' => $this->due_date?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completion_percentage' => $this->progress_percentage,
            'remarks' => $this->remarks,
            'is_overdue' => $this->due_date !== null && $this->due_date->isPast() && $this->status !== 'completed' && $this->status !== 'cancelled',
        ];
    }
}
