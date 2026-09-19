<?php

namespace App\Http\Resources\Api\V1\TaskManagement;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task_assignment_id' => $this->task_assignment_id,
            'reviewed_by' => $this->reviewed_by,
            'decision' => $this->decision,
            'remarks' => $this->remarks,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
