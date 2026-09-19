<?php

namespace App\Http\Resources\Api\V1\TaskManagement;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskProgressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task_assignment_id' => $this->task_assignment_id,
            'employee_id' => $this->employee_id,
            'previous_percentage' => $this->previous_percentage,
            'new_percentage' => $this->new_percentage,
            'status' => $this->status,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
