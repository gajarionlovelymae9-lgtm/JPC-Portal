<?php

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\Report;
use App\Models\TaskAssignment;
use Carbon\Carbon;

class AdminDepartmentManagementService
{
    public function listDepartments(array $filters = []): array
    {
        $departments = Department::with(['head.employee:id,first_name,last_name'])->get();
        $employees = Employee::with('position:id,name')->get();
        $assignments = TaskAssignment::with('task:id,deadline')->get();
        $reports = Report::all();

        return $departments->map(function ($d) use ($employees, $assignments, $reports) {
            $deptEmployees = $employees->where('department_id', $d->id);
            $empIds = $deptEmployees->pluck('id');
            $deptAssignments = $assignments->whereIn('employee_id', $empIds);

            $total = $deptAssignments->count();
            $completed = $deptAssignments->whereIn('status', ['completed', 'approved'])->count();
            $pending = $deptAssignments->whereIn('status', ['assigned', 'in_progress'])->count();

            $overdue = $deptAssignments->filter(function ($a) {
                $deadline = $a->due_date ?? $a->task?->deadline;
                return $deadline && Carbon::parse($deadline)->isPast() && !in_array($a->status, ['completed', 'approved']);
            })->count();

            $deptReports = $reports->whereIn('employee_id', $empIds)->count();
            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            $onTimeCount = $deptAssignments->filter(function ($a) {
                if (!$a->submitted_at) return false;
                $deadline = $a->due_date ?? $a->task?->deadline;
                return !$deadline || Carbon::parse($a->submitted_at)->lte(Carbon::parse($deadline));
            })->count();
            $onTimeRate = $completed > 0 ? round(($onTimeCount / $completed) * 100, 1) : 100;

            return [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'head_name' => $d->head?->employee ? $d->head->employee->first_name . ' ' . $d->head->employee->last_name : 'Assigned Head',
                'employees_count' => $deptEmployees->count(),
                'active_employees' => $deptEmployees->where('employment_status', 'active')->count(),
                'tasks_count' => $total,
                'completed_tasks' => $completed,
                'pending_tasks' => $pending,
                'overdue_tasks' => $overdue,
                'reports_count' => $deptReports,
                'completion_rate' => $rate,
                'on_time_rate' => $onTimeRate,
            ];
        })->values()->toArray();
    }

    public function showDepartment(Department $department): array
    {
        $department->load(['head.employee.position:id,name']);
        $employees = Employee::where('department_id', $department->id)
            ->with(['position:id,name', 'user:id,email,status'])
            ->get();
        $empIds = $employees->pluck('id');

        $assignments = TaskAssignment::whereIn('employee_id', $empIds)
            ->with(['task:id,title,deadline,priority', 'employee:id,first_name,last_name'])
            ->get();

        $reports = Report::whereIn('employee_id', $empIds)
            ->with('task:id,title')
            ->get();

        $target = DepartmentTarget::where('department_id', $department->id)->first();

        $totalTasks = $assignments->count();
        $completedTasks = $assignments->whereIn('status', ['completed', 'approved'])->count();
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description ?? null,
            'head' => [
                'name' => $department->head?->employee ? $department->head->employee->first_name . ' ' . $department->head->employee->last_name : 'Department Head',
                'position' => $department->head?->employee?->position?->name ?? 'Head',
            ],
            'metrics' => [
                'total_employees' => $employees->count(),
                'active_employees' => $employees->where('employment_status', 'active')->count(),
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $assignments->whereIn('status', ['assigned', 'in_progress'])->count(),
                'reports_count' => $reports->count(),
                'completion_rate' => $completionRate,
            ],
            'target' => $target ? [
                'name' => $target->name ?? $target->title ?? 'Department Annual Target',
                'target' => (int) ($target->target_value ?? 10),
                'completed' => min((int) ($target->target_value ?? 10), $completedTasks),
            ] : null,
            'employees' => $employees->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->first_name . ' ' . $e->last_name,
                'employee_number' => $e->employee_number,
                'position' => $e->position?->name ?? 'Faculty',
                'status' => $e->employment_status,
            ])->toArray(),
        ];
    }
}