<?php

namespace App\Services\Dashboard;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ManagementDashboardService
{
    private const MANAGER_ROLES = ['admin', 'hr', 'department_head'];

    /**
     * Resolve the effective department scope for the authenticated user.
     *
     * Admins and HR may filter by any department (or view all). Department
     * heads are always locked to their own department and receive a 403 when
     * they request another department. Non-manager roles are denied outright.
     */
    public function resolveDepartmentScope(User $user, ?int $requestedDepartmentId): ?int
    {
        if (! in_array($user->roleName(), self::MANAGER_ROLES, true)) {
            throw new AuthorizationException('Forbidden');
        }

        if ($user->roleName() !== 'department_head') {
            return $requestedDepartmentId;
        }

        $ownedDepartmentId = $user->employee?->department_id;

        if ($ownedDepartmentId === null) {
            throw new AuthorizationException('Forbidden');
        }

        if ($requestedDepartmentId !== null && $requestedDepartmentId !== (int) $ownedDepartmentId) {
            throw new AuthorizationException('Forbidden');
        }

        return (int) $ownedDepartmentId;
    }

    /**
     * Reject requests from a department head targeting an employee who does
     * not belong to their department.
     */
    public function resolveEmployeeScope(User $user, Employee $employee): void
    {
        if ($user->roleName() !== 'department_head') {
            return;
        }

        if ($employee->department_id !== (int) $user->employee?->department_id) {
            throw new AuthorizationException('Forbidden');
        }
    }

    /**
     * Filter options for the dashboard toolbar: departments the current user
     * may scope to, and academic years available for target/time comparisons.
     */
    public function options(User $user, ?int $departmentId): array
    {
        $departments = Department::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($departmentId !== null) {
            $departments = $departments->where('id', $departmentId)->values();
        }

        $academicYears = AcademicYear::query()
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'status']);

        return [
            'departments' => $departments
                ->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name])
                ->values()
                ->all(),
            'academic_years' => $academicYears
                ->map(fn (AcademicYear $year) => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status])
                ->all(),
        ];
    }

    /**
     * Organisation-wide dashboard summary (KPI cards).
     */
    public function summary(User $user, array $filters, ?int $departmentId): array
    {
        $base = $this->taskQuery($departmentId, $filters);

        $total = $base->clone()->count();
        $completed = $base->clone()->completed()->count();
        $late = $base->clone()->completed()
            ->whereNotNull('due_date')
            ->whereColumn('completed_at', '>', 'due_date')
            ->count();
        $onTime = $completed - $late;

        $employees = Employee::query()->active();
        if ($departmentId !== null) {
            $employees->byDepartment($departmentId);
        }

        return [
            'total_employees' => $employees->count(),
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $base->clone()->pending()->count(),
            'overdue_tasks' => $base->clone()->overdue()->count(),
            'submitted_tasks' => $base->clone()->where('status', 'submitted')->count(),
            'under_review_tasks' => $base->clone()->where('status', 'under_review')->count(),
            'rejected_tasks' => $base->clone()->where('status', 'rejected')->count(),
            'cancelled_tasks' => $base->clone()->where('status', 'cancelled')->count(),
            'on_time' => $onTime,
            'late' => $late,
            'completion_rate' => $this->rate($completed, $total),
            'on_time_rate' => $this->rate($onTime, $completed),
        ];
    }

    /**
     * Paginated per-employee progress table.
     */
    public function employeeProgress(User $user, array $filters, ?int $departmentId): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $map = [
            'name' => 'name',
            'assigned' => 'assigned_tasks',
            'completed' => 'completed_tasks',
            'pending' => 'pending_tasks',
            'overdue' => 'overdue_tasks',
        ];

        $orderBy = $map[$sort] ?? 'name';

        $query = Employee::query()
            ->withCount([
                'taskAssignments as assigned_tasks',
                'taskAssignments as completed_tasks' => fn (Builder $q) => $q->completed(),
                'taskAssignments as pending_tasks' => fn (Builder $q) => $q->pending(),
                'taskAssignments as overdue_tasks' => fn (Builder $q) => $q->overdue(),
            ]);

        if ($departmentId !== null) {
            $query->byDepartment($departmentId);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'like', $search)
                    ->orWhere('middle_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search);
            });
        }

        if ($orderBy === 'name') {
            $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
        } else {
            $query->orderBy($orderBy, $direction);
        }

        return $query->paginate($this->perPage($filters));
    }

    /**
     * Paginated per-department progress table.
     */
    public function departmentProgress(User $user, array $filters, ?int $departmentId): LengthAwarePaginator
    {
        $allowedSorts = ['name', 'total_tasks', 'completed_tasks', 'pending_tasks', 'overdue_tasks'];
        $sort = in_array($filters['sort'] ?? null, $allowedSorts, true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Department::query()
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => fn (Builder $q) => $q->completed(),
                'tasks as pending_tasks' => fn (Builder $q) => $q->pending(),
                'tasks as overdue_tasks' => fn (Builder $q) => $q->overdue(),
            ]);

        if ($departmentId !== null) {
            $query->where('id', $departmentId);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search);
            });
        }

        $query->orderBy($sort, $direction);

        return $query->paginate($this->perPage($filters));
    }

    /**
     * Paginated task table scoped to the dashboard (search, status, priority,
     * department, academic year, date range, sorting).
     */
    public function tasks(User $user, array $filters, ?int $departmentId): LengthAwarePaginator
    {
        $allowedSorts = ['created_at', 'updated_at', 'due_date', 'completed_at', 'priority', 'status', 'title'];
        $sort = in_array($filters['sort'] ?? null, $allowedSorts, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = $this->taskQuery($departmentId, $filters)
            ->with(['department', 'assignments']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('task_code', 'like', $search)
                    ->orWhere('title', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        $query->orderBy($sort, $direction);

        return $query->paginate($this->perPage($filters));
    }

    /**
     * Paginated list of overdue tasks with days overdue.
     */
    public function overdueTasks(User $user, array $filters, ?int $departmentId): LengthAwarePaginator
    {
        $query = $this->taskQuery($departmentId, $filters)
            ->overdue()
            ->with(['department', 'assignments.employee']);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('task_code', 'like', $search)
                    ->orWhere('title', 'like', $search);
            });
        }

        $query->orderBy('due_date', 'asc');

        return $query->paginate($this->perPage($filters));
    }

    /**
     * Paginated review queue (submitted / under review assignments) with the
     * latest review attached.
     */
    public function reviewQueue(User $user, array $filters, ?int $departmentId): LengthAwarePaginator
    {
        $query = TaskAssignment::query()
            ->whereIn('status', ['submitted', 'under_review'])
            ->with([
                'task.department',
                'employee',
                'reviews' => fn ($q) => $q->latest('created_at')->with('reviewedBy'),
            ]);

        if ($departmentId !== null) {
            $query->whereHas('task', fn (Builder $q) => $q->byDepartment($departmentId));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('task', fn (Builder $t) => $t->where('title', 'like', $search)->orWhere('task_code', 'like', $search))
                    ->orWhereHas('employee', fn (Builder $e) => $e->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search));
            });
        }

        $query->orderByDesc('updated_at');

        return $query->paginate($this->perPage($filters));
    }

    /**
     * Progress metrics for a selected calendar month.
     */
    public function monthlyProgress(User $user, array $filters, ?int $departmentId): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);

        $base = $this->taskQuery($departmentId, $filters);

        $total = $base->clone()->whereYear('created_at', $year)->whereMonth('created_at', $month)->count();
        $completed = $base->clone()->completed()->whereYear('completed_at', $year)->whereMonth('completed_at', $month)->count();
        $late = $base->clone()->completed()
            ->whereNotNull('due_date')
            ->whereColumn('completed_at', '>', 'due_date')
            ->whereYear('completed_at', $year)
            ->whereMonth('completed_at', $month)
            ->count();
        $onTime = $completed - $late;

        return [
            'year' => $year,
            'month' => $month,
            'total' => $total,
            'completed' => $completed,
            'pending' => $base->clone()->pending()->whereYear('created_at', $year)->whereMonth('created_at', $month)->count(),
            'overdue' => $base->clone()->overdue()->whereYear('due_date', $year)->whereMonth('due_date', $month)->count(),
            'on_time' => $onTime,
            'late' => $late,
            'submitted' => $base->clone()->where('status', 'submitted')->whereYear('created_at', $year)->whereMonth('created_at', $month)->count(),
            'under_review' => $base->clone()->where('status', 'under_review')->whereYear('created_at', $year)->whereMonth('created_at', $month)->count(),
            'completion_rate' => $this->rate($completed, $total),
        ];
    }

    /**
     * Ranked monthly achievement leaderboard (period based).
     */
    public function monthlyAchievement(User $user, array $filters, ?int $departmentId): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);

        $employees = Employee::query()
            ->with('department')
            ->when($departmentId !== null, fn (Builder $q) => $q->byDepartment($departmentId))
            ->withCount([
                'taskAssignments as total_tasks',
                'taskAssignments as completed_tasks' => fn (Builder $q) => $q
                    ->completed()
                    ->whereYear('completed_at', $year)
                    ->whereMonth('completed_at', $month),
            ])
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->fullName,
                'department_id' => $employee->department_id,
                'department_name' => $employee->department?->name,
                'completed_tasks' => (int) $employee->completed_tasks,
                'completion_rate' => $this->rate((int) $employee->completed_tasks, (int) $employee->total_tasks),
            ])
            ->sortBy([
                ['completion_rate', 'desc'],
                ['completed_tasks', 'desc'],
                ['employee_name', 'asc'],
            ])
            ->values()
            ->map(fn (array $item, int $index) => ['rank' => $index + 1] + $item)
            ->values();

        return $employees->all();
    }

    /**
     * Target vs actual achievement derived from employee targets.
     *
     * `target` counts the employee targets in scope; `achieved` counts targets
     * that have at least one completed task (optionally completed within the
     * requested date range).
     */
    public function targetVsActual(User $user, array $filters, ?int $departmentId): array
    {
        $targets = EmployeeTarget::query()
            ->with([
                'employee',
                'tasks' => fn ($q) => $q->completed()
                    ->when(! empty($filters['from_date']), fn ($b) => $b->whereDate('completed_at', '>=', $filters['from_date']))
                    ->when(! empty($filters['to_date']), fn ($b) => $b->whereDate('completed_at', '<=', $filters['to_date'])),
            ])
            ->when($departmentId !== null, fn (Builder $q) => $q->whereHas('employee', fn (Builder $e) => $e->byDepartment($departmentId)))
            ->when(! empty($filters['academic_year_id']), fn (Builder $q) => $q->whereHas(
                'annualPlan',
                fn (Builder $b) => $b->where('academic_year_id', (int) $filters['academic_year_id'])
            ))
            ->get();

        $target = $targets->count();
        $achieved = $targets->filter(fn (EmployeeTarget $target) => $target->tasks->isNotEmpty())->count();

        $period = 'All time';
        if (! empty($filters['academic_year_id'])) {
            $year = AcademicYear::query()->find((int) $filters['academic_year_id']);
            $period = $year?->name ?? 'Academic year';
        } elseif (! empty($filters['month']) || ! empty($filters['year'])) {
            $period = 'Monthly';
        }

        return [
            'target' => $target,
            'achieved' => $achieved,
            'remaining' => max($target - $achieved, 0),
            'achievement_rate' => $this->rate($achieved, $target),
            'period' => $period,
        ];
    }

    /**
     * Detailed progress for a single employee: assignment KPIs, six-month
     * monthly series, and recent assignments.
     */
    public function employeeDetailProgress(User $user, Employee $employee, array $filters): array
    {
        $assignments = TaskAssignment::query()->forEmployee($employee->id);

        $total = $assignments->count();
        $completed = $assignments->clone()->completed()->count();
        $late = $assignments->clone()->completed()
            ->whereNotNull('due_date')
            ->whereColumn('completed_at', '>', 'due_date')
            ->count();
        $onTime = $completed - $late;

        $summary = [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $assignments->clone()->inProgress()->count(),
            'submitted' => $assignments->clone()->submitted()->count(),
            'under_review' => $assignments->clone()->where('status', 'under_review')->count(),
            'rejected' => $assignments->clone()->rejected()->count(),
            'pending' => $assignments->clone()->pending()->count(),
            'overdue' => $assignments->clone()->overdue()->count(),
            'on_time' => $onTime,
            'late' => $late,
            'completion_rate' => $this->rate($completed, $total),
            'on_time_rate' => $this->rate($onTime, $completed),
        ];

        $months = $this->monthlySeries($employee, $filters);

        $recent = TaskAssignment::query()
            ->forEmployee($employee->id)
            ->with('task')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (TaskAssignment $assignment) => [
                'assignment_id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'task_title' => $assignment->task?->title,
                'task_code' => $assignment->task?->task_code,
                'status' => $assignment->status,
                'due_date' => $assignment->due_date?->toDateString(),
                'completed_at' => $assignment->completed_at?->toIso8601String(),
                'progress_percentage' => $assignment->progress_percentage,
            ])
            ->all();

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name,
            'summary' => $summary,
            'months' => $months,
            'recent_assignments' => $recent,
        ];
    }

    /**
     * Base task query with department, academic year, and date-range scopes.
     */
    private function taskQuery(?int $departmentId, array $filters): Builder
    {
        $query = Task::query();

        if ($departmentId !== null) {
            $query->byDepartment($departmentId);
        }

        if (! empty($filters['academic_year_id'])) {
            $academicYearId = (int) $filters['academic_year_id'];
            $query->whereHas('annualPlan', fn (Builder $q) => $q->where('academic_year_id', $academicYearId));
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('due_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('due_date', '<=', $filters['to_date']);
        }

        return $query;
    }

    /**
     * Last six months of assignment volume for an employee.
     *
     * @return list<array{label: string, total: int, completed: int, completion_rate: float}>
     */
    private function monthlySeries(Employee $employee, array $filters): array
    {
        $anchor = now();
        if (! empty($filters['year']) && ! empty($filters['month'])) {
            $anchor = Carbon::create((int) $filters['year'], (int) $filters['month'], 1, 0, 0, 0);
        }

        $months = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $start = Carbon::create($anchor->year, $anchor->month, 1, 0, 0, 0)->subMonthsNoOverflow($offset);

            $total = TaskAssignment::query()
                ->forEmployee($employee->id)
                ->whereYear('assigned_at', $start->year)
                ->whereMonth('assigned_at', $start->month)
                ->count();
            $completed = TaskAssignment::query()
                ->forEmployee($employee->id)
                ->completed()
                ->whereYear('completed_at', $start->year)
                ->whereMonth('completed_at', $start->month)
                ->count();

            $months[] = [
                'label' => $start->format('Y-m'),
                'total' => $total,
                'completed' => $completed,
                'completion_rate' => $this->rate($completed, $total),
            ];
        }

        return $months;
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
    }

    private function perPage(array $filters): int
    {
        return min((int) ($filters['per_page'] ?? 15), 100);
    }
}