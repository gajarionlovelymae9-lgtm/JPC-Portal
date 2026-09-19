<?php

namespace App\Services\Hr;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use App\Models\TaskAssignment;
use App\Support\ApiResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * HR dashboard + directory aggregation (Phase 3.3-W).
 *
 * Documents that HR operates on EMPLOYEE-centric, institution-wide surfaces
 * (dashboard KPIs, directory, monitoring) and is explicitly NOT a proxy for
 * Admin (settings/RBAC/AI-config/OCR-config/system-config live behind
 * `settings.manage` which HR does not hold — enforced at the route layer).
 */
class HrDashboardService
{
    private const CACHE_TTL = 300;
    private const PREFIX = 'hr:dashboard:';

    public function employees(): Collection
    {
        return Employee::query()
            ->with(['department:id,name', 'position:id,name'])
            ->orderBy('last_name')
            ->get();
    }

    public function kpis(): array
    {
        $employees = Employee::query()->get(['employment_status']);
        $reports = Report::query()->get(['id', 'status']);
        $tasks = TaskAssignment::query()->get(['id', 'status']);

        return [
            'total_employees' => $employees->count(),
            'active_employees' => $employees->where('employment_status', 'active')->count(),
            'inactive_employees' => $employees->where('employment_status', '!=', 'active')->count(),
            'departments' => Department::query()->count(),
            'pending_reports' => $reports->where('status', 'submitted')->count(),
            'under_review_reports' => $reports->where('status', 'under_review')->count(),
            'revision_required_reports' => $reports->where('status', 'revision_required')->count(),
            'overdue_tasks' => $tasks->whereIn('status', ['assigned', 'in_progress', 'submitted'])->count(),
        ];
    }

    public function departments(): Collection
    {
        return Department::query()
            ->withCount(['employees' => fn ($q) => $q->where('employment_status', 'active')])
            ->orderBy('name')
            ->get();
    }

    public function summary(Employee $employee): array
    {
        $tasks = TaskAssignment::query()->where('employee_id', $employee->id)->get(['status']);

        return [
            'employee_id' => $employee->employee_no,
            'name' => trim($employee->first_name.' '.($employee->middle_name ?? '').' '.$employee->last_name),
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'status' => $employee->employment_status,
            'task_total' => $tasks->count(),
            'task_completed' => $tasks->whereIn('status', ['completed', 'approved', 'reviewed'])->count(),
            'task_in_progress' => $tasks->whereIn('status', ['in_progress', 'submitted'])->count(),
            'task_assigned' => $tasks->where('status', 'assigned')->count(),
        ];
    }

    public function key(string $segment, ?int $id = null): string
    {
        return self::PREFIX.$segment.($id !== null ? ':'.$id : '');
    }

    public static function clearAll(): void
    {
        // Intentionally a no-op marker: cache is TTL-scoped and there is no
        // shared admin mutation cache key that HR owns.
    }
}
