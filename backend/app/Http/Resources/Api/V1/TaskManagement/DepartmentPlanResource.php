<?php

namespace App\Http\Resources\Api\V1\TaskManagement;

use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentPlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'annual_plan_id' => $this->annual_plan_id,
            'department_id' => $this->department_id,
            'title' => $this->title,
        ];
    }
}
