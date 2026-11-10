<?php

namespace App\Http\Resources\Api\V1\TaskManagement;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_code' => $this->task_code,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'start_date' => $this->start_date?->toIso8601String(),
            'due_date' => $this->due_date?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'estimated_hours' => $this->estimated_hours,
            'target_value' => $this->target_value,
            'target_unit' => $this->target_unit,
            'required_output' => $this->required_output,
            'is_overdue' => $this->isOverdue(),
            'annual_plan' => new AnnualPlanResource($this->whenLoaded('annualPlan')),
            'department_plan' => new DepartmentPlanResource($this->whenLoaded('departmentPlan')),
            'department_target' => new DepartmentTargetResource($this->whenLoaded('departmentTarget')),
            'employee_target' => new EmployeeTargetResource($this->whenLoaded('employeeTarget')),
            'assignments' => TaskAssignmentResource::collection($this->whenLoaded('assignments')),
        ];
    }
}
