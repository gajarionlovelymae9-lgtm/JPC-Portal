<?php

namespace App\Http\Resources\Api\V1\TaskManagement;

use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentTargetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'department_plan_id' => $this->department_plan_id,
            'title' => $this->title,
        ];
    }
}
