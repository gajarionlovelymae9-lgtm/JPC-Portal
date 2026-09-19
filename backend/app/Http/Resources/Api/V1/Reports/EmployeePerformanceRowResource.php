<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row of the Employee Performance Report (computed server-side).
 *
 * The supplied row is a pre-computed array produced by the report service —
 * never a raw Eloquent model — so the resource layer only enforces the
 * public API contract.
 */
class EmployeePerformanceRowResource extends JsonResource
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
            'pending_tasks' => $row['pending_tasks'] ?? 0,
            'overdue_tasks' => $row['overdue_tasks'] ?? 0,
            'on_time' => $row['on_time'] ?? 0,
            'late' => $row['late'] ?? 0,
            'completion_rate' => $row['completion_rate'] ?? 0.0,
            'on_time_rate' => $row['on_time_rate'] ?? 0.0,
            'next_due_date' => $row['next_due_date'] ?? null,
            'recent_assignments' => $row['recent_assignments'] ?? null,
        ];
    }
}