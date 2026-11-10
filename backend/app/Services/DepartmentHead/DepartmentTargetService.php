<?php

namespace App\Services\DepartmentHead;

use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;

class DepartmentTargetService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context
    ) {}

    public function listTargets(User $user, array $filters = []): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);
        $employeeIds = Employee::where('department_id', $department->id)->pluck('id');
        $completedCount = TaskAssignment::whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['completed', 'approved'])
            ->count();
        $totalCount = TaskAssignment::whereIn('employee_id', $employeeIds)->count();

        $targets = DepartmentTarget::where('department_id', $department->id)->get();

        if ($targets->isEmpty()) {
            // Provide a default annual target if table is empty
            $targets = collect([
                (object) [
                    'id' => 1,
                    'target_name' => 'Annual Academic & Operational Deliverables',
                    'target_description' => 'Complete all scheduled departmental deliverables, student reports, and curriculum updates for the academic year.',
                    'target_value' => max(10, $totalCount),
                    'end_date' => '2027-05-31',
                ],
            ]);
        }

        return $targets->map(function ($t) use ($completedCount) {
            $val = max(1, (int) ($t->target_value ?? 10));
            $completed = min($val, $completedCount);
            $progress = round(($completed / $val) * 100, 1);
            $isAtRisk = $progress < 50 && Carbon::now()->month >= 6;

            return [
                'id' => $t->id,
                'name' => $t->target_name ?? 'Department Target',
                'description' => $t->target_description ?? null,
                'academic_year' => '2026-2027',
                'target' => $val,
                'completed' => $completed,
                'remaining' => max(0, $val - $completed),
                'progress_percent' => $progress,
                'is_at_risk' => $isAtRisk,
                'status' => $progress >= 100 ? 'Completed' : ($isAtRisk ? 'At Risk' : 'In Progress'),
                'deadline' => $t->end_date ?? '2027-05-31',
            ];
        })->toArray();
    }
}
