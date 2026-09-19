<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentProgressResource extends JsonResource
{
    public function toArray($request): array
    {
        $total = (int) $this->total_tasks;
        $completed = (int) $this->completed_tasks;

        return [
            'department_id' => $this->id,
            'department_name' => $this->name,
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => (int) $this->pending_tasks,
            'overdue_tasks' => (int) $this->overdue_tasks,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
        ];
    }
}