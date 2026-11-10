<?php

namespace App\Services\DepartmentHead;

use App\Models\Employee;
use App\Models\Report;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;

class DepartmentEmployeeService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context
    ) {}

    public function listEmployees(User $user, array $filters = []): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);

        $query = Employee::where('department_id', $department->id)
            ->with(['position:id,name', 'user:id,email,status']);

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('employment_status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('last_name')->get();
        $employeeIds = $employees->pluck('id');

        $assignments = TaskAssignment::whereIn('employee_id', $employeeIds)->get();
        $reports = Report::whereIn('employee_id', $employeeIds)->get();

        return $employees->map(function ($emp) use ($assignments, $reports) {
            $empAssignments = $assignments->where('employee_id', $emp->id);
            $totalTasks = $empAssignments->count();
            $completedTasks = $empAssignments->whereIn('status', ['completed', 'approved'])->count();
            $empReports = $reports->where('employee_id', $emp->id)->count();

            return [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'first_name' => $emp->first_name,
                'last_name' => $emp->last_name,
                'full_name' => $emp->first_name.' '.$emp->last_name,
                'position' => $emp->position?->name ?? 'Faculty',
                'employment_status' => $emp->employment_status,
                'email' => $emp->user?->email,
                'account_status' => $emp->user?->status ?? 'active',
                'tasks_count' => $totalTasks,
                'completed_tasks_count' => $completedTasks,
                'reports_count' => $empReports,
                'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
            ];
        })->toArray();
    }

    public function showEmployee(User $user, Employee $employee): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeEmployee($department, $employee);

        $employee->load([
            'position:id,name',
            'user:id,email,status,last_login_at',
            'department:id,name',
        ]);

        $assignments = TaskAssignment::where('employee_id', $employee->id)
            ->with('task:id,title,due_date,priority')
            ->orderBy('created_at', 'desc')
            ->get();

        $reports = Report::where('employee_id', $employee->id)
            ->with('task:id,title')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalTasks = $assignments->count();
        $completedTasks = $assignments->whereIn('status', ['completed', 'approved'])->count();
        $pendingTasks = $assignments->whereIn('status', ['assigned', 'in_progress'])->count();

        $now = Carbon::now();
        $overdueTasks = $assignments->filter(function ($a) {
            $deadline = $a->due_date ?? $a->task?->due_date;

            return $deadline && Carbon::parse($deadline)->isPast() && ! in_array($a->status, ['completed', 'approved']);
        })->count();

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'full_name' => $employee->first_name.' '.$employee->last_name,
            'department_name' => $employee->department?->name,
            'position_name' => $employee->position?->name ?? 'Faculty',
            'employment_status' => $employee->employment_status,
            'employment_type' => $employee->employment_type ?? 'Regular',
            'date_hired' => $employee->date_hired,
            'email' => $employee->user?->email,
            'account_status' => $employee->user?->status ?? 'active',
            'last_login_at' => $employee->user?->last_login_at,
            'metrics' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $pendingTasks,
                'overdue_tasks' => $overdueTasks,
                'reports_submitted' => $reports->count(),
                'completion_rate' => $completionRate,
            ],
            'assignments' => $assignments->map(fn ($a) => [
                'id' => $a->id,
                'task_id' => $a->task_id,
                'task_title' => $a->task?->title ?? '—',
                'priority' => $a->task?->priority ?? 'medium',
                'status' => $a->status,
                'progress' => (int) ($a->progress_percentage ?? 0),
                'due_date' => $a->due_date ?? $a->task?->due_date,
                'submitted_at' => $a->completed_at,
            ]),
            'reports' => $reports->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'task_title' => $r->task?->title ?? '—',
                'status' => $r->status,
                'submitted_at' => $r->submitted_at,
            ]),
        ];
    }
}
