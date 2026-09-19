<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeProgressResource extends JsonResource
{
    public function toArray($request): array
    {
        $assigned = (int) $this->assigned_tasks;
        $completed = (int) $this->completed_tasks;

        return [
            'employee_id' => $this->id,
            'employee_name' => $this->fullName,
            'assigned_tasks' => $assigned,
            'completed_tasks' => $completed,
            'pending_tasks' => (int) $this->pending_tasks,
            'overdue_tasks' => (int) $this->overdue_tasks,
            'completion_rate' => $assigned > 0 ? round(($completed / $assigned) * 100, 2) : 0.0,
        ];
    }
}