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
        $departments = Department::with(['departmentHeads.employee:id,first_name,last_name'])->get();
        $employees = Employee::with('position:id,name')->get();
        $assignments = TaskAssignment::with('task:id,due_date')->get();
        $reports = Report::all();

        return $departments->map(function ($d) use ($employees, $assignments, $reports) {
            $currentHead = $d->departmentHeads->firstWhere('is_current', true);
            $deptEmployees = $employees->where('department_id', $d->id);
            $empIds = $deptEmployees->pluck('id');
            $deptAssignments = $assignments->whereIn('employee_id', $empIds);

            $total = $deptAssignments->count();
            $completed = $deptAssignments->whereIn('status', ['completed', 'approved'])->count();
            $pending = $deptAssignments->whereIn('status', ['assigned', 'in_progress'])->count();

            $overdue = $deptAssignments->filter(function ($a) {
                $deadline = $a->due_date ?? $a->task?->due_date;
                return $deadline && Carbon::parse($deadline)->isPast() && !in_array($a->status, ['completed', 'approved']);
            })->count();

            $deptReports = $reports->whereIn('employee_id', $empIds)->count();
            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            $onTimeCount = $deptAssignments->filter(function ($a) {
                if (!$a->completed_at) return false;
                $deadline = $a->due_date ?? $a->task?->due_date;
                return !$deadline || Carbon::parse($a->completed_at)->lte(Carbon::parse($deadline));
            })->count();
            $onTimeRate = $completed > 0 ? round(($onTimeCount / $completed) * 100, 1) : 100;

            return [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'head_name' => $currentHead?->employee ? $currentHead->employee->first_name . ' ' . $currentHead->employee->last_name : 'Assigned Head',
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
        $department->load(['departmentHeads.employee.position:id,name']);
        $currentHead = $department->departmentHeads->firstWhere('is_current', true);
        $employees = Employee::where('department_id', $department->id)
            ->with(['position:id,name', 'user:id,email,status'])
            ->get();
        $empIds = $employees->pluck('id');

        $assignments = TaskAssignment::whereIn('employee_id', $empIds)
            ->with(['task:id,title,due_date,priority', 'employee:id,first_name,last_name'])
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
                'name' => $currentHead?->employee ? $currentHead->employee->first_name . ' ' . $currentHead->employee->last_name : 'Department Head',
                'position' => $currentHead?->employee?->position?->name ?? 'Head',
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