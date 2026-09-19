<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row of the monthly achievement leaderboard (computed server-side).
 */
class AchievementRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = is_array($this->resource) ? $this->resource : [];

        return [
            'rank' => $row['rank'] ?? null,
            'employee_id' => $row['employee_id'] ?? null,
            'employee_name' => $row['employee_name'] ?? null,
            'department_id' => $row['department_id'] ?? null,
            'department_name' => $row['department_name'] ?? null,
            'total_tasks' => $row['total_tasks'] ?? 0,
            'completed_tasks' => $row['completed_tasks'] ?? 0,
            'completion_rate' => $row['completion_rate'] ?? 0.0,
            'on_time_rate' => $row['on_time_rate'] ?? 0.0,
            'overall_score' => $row['overall_score'] ?? 0.0,
            'is_employee_of_month' => (bool) ($row['is_employee_of_month'] ?? false),
        ];
    }
}