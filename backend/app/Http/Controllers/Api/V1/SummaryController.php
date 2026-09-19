<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Task;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function taskSummary(): JsonResponse
    {
        $this->authorize('viewAnalytics', Task::class);

        return ApiResponse::success('Task summary retrieved successfully', [
            'total_tasks' => Task::query()->count(),
            'completed_tasks' => Task::query()->where('status', 'completed')->count(),
            'pending_tasks' => Task::query()->pending()->count(),
            'overdue_tasks' => Task::query()->overdue()->count(),
            'submitted_tasks' => Task::query()->where('status', 'submitted')->count(),
            'under_review_tasks' => Task::query()->where('status', 'under_review')->count(),
            'rejected_tasks' => Task::query()->where('status', 'rejected')->count(),
            'cancelled_tasks' => Task::query()->where('status', 'cancelled')->count(),
        ]);
    }

    public function employeeProgress(): JsonResponse
    {
        $this->authorize('viewAnalytics', Task::class);

        $employees = Employee::query()
            ->withCount([
                'taskAssignments as assigned_tasks',
                'taskAssignments as completed_tasks' => fn ($q) => $q->where('status', 'completed'),
                'taskAssignments as pending_tasks' => fn ($q) => $q->whereIn('status', ['assigned', 'accepted', 'in_progress', 'submitted']),
                'taskAssignments as overdue_tasks' => fn ($q) => $q->overdue(),
            ])
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->fullName,
                'assigned_tasks' => (int) $employee->assigned_tasks,
                'completed_tasks' => (int) $employee->completed_tasks,
                'pending_tasks' => (int) $employee->pending_tasks,
                'overdue_tasks' => (int) $employee->overdue_tasks,
                'completion_rate' => $this->completionRate((int) $employee->completed_tasks, (int) $employee->assigned_tasks),
            ]);

        return ApiResponse::success('Employee progress retrieved successfully', $employees);
    }

    public function departmentProgress(): JsonResponse
    {
        $this->authorize('viewAnalytics', Task::class);

        $departments = Department::query()
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => fn ($q) => $q->where('status', 'completed'),
                'tasks as pending_tasks' => fn ($q) => $q->pending(),
                'tasks as overdue_tasks' => fn ($q) => $q->overdue(),
            ])
            ->get()
            ->map(fn (Department $department) => [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'total_tasks' => (int) $department->total_tasks,
                'completed_tasks' => (int) $department->completed_tasks,
                'pending_tasks' => (int) $department->pending_tasks,
                'overdue_tasks' => (int) $department->overdue_tasks,
                'completion_rate' => $this->completionRate((int) $department->completed_tasks, (int) $department->total_tasks),
            ]);

        return ApiResponse::success('Department progress retrieved successfully', $departments);
    }

    public function monthlyAchievement(Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', Task::class);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $employees = Employee::query()
            ->withCount([
                'taskAssignments as total_tasks',
                'taskAssignments as completed_tasks' => fn ($q) => $q
                    ->where('status', 'completed')
                    ->whereYear('completed_at', $year)
                    ->whereMonth('completed_at', $month),
            ])
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->fullName,
                'completed_tasks' => (int) $employee->completed_tasks,
                'completion_rate' => $this->completionRate((int) $employee->completed_tasks, (int) $employee->total_tasks),
            ])
            ->sortBy([
                ['completion_rate', 'desc'],
                ['completed_tasks', 'desc'],
                ['employee_name', 'asc'],
            ])
            ->values()
            ->map(fn (array $item, int $index) => ['rank' => $index + 1] + $item)
            ->values();

        return ApiResponse::success('Monthly achievement retrieved successfully', $employees);
    }

    private function completionRate(int $completed, int $total): float
    {
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
    }
}
