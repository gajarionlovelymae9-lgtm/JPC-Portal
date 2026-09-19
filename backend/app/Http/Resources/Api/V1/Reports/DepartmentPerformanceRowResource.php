<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row of the Department Performance Report (computed server-side).
 */
class DepartmentPerformanceRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = is_array($this->resource) ? $this->resource : [];

        return [
            'rank' => $row['rank'] ?? null,
            'department_id' => $row['department_id'] ?? null,
            'department_name' => $row['department_name'] ?? null,
            'department_code' => $row['department_code'] ?? null,
            'employee_count' => $row['employee_count'] ?? 0,
            'total_tasks' => $row['total_tasks'] ?? 0,
            'completed_tasks' => $row['completed_tasks'] ?? 0,
            'pending_tasks' => $row['pending_tasks'] ?? 0,
            'overdue_tasks' => $row['overdue_tasks'] ?? 0,
            'on_time' => $row['on_time'] ?? 0,
            'late' => $row['late'] ?? 0,
            'completion_rate' => $row['completion_rate'] ?? 0.0,
            'on_time_rate' => $row['on_time_rate'] ?? 0.0,
            'next_due_date' => $row['next_due_date'] ?? null,
            'recent_tasks' => $row['recent_tasks'] ?? null,
        ];
    }
}