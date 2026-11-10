<?php

namespace App\Services\Reports;

use App\Enums\PerformanceRating;
use App\Enums\ReportStatus;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\Report;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Hr\HrDashboardService;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Employee Performance Report: ranked, sortable, paginated per-employee KPIs.
 *
 * All rates are derived from task assignments. Ranking is computed over the
 * full in-scope population (so ranks are genuine positions, not per-page
 * indexes) and only then is the result paginated.
 */
class EmployeePerformanceReportService
{
    use ComputesReportMetrics;

    public function table(User $user, ReportFilterBag $bag, ?int $departmentId): LengthAwarePaginator
    {
        return $this->paginate($this->rows($user, $bag, $departmentId), $bag);
    }

    /**
     * Ranked, sorted, full-scope rows — used by the API table and exports.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $employees = Employee::query()
            ->active()
            ->with('department')
            ->when($departmentId !== null, fn (Builder $q) => $q->byDepartment($departmentId), fn (Builder $q) => $bag->departmentId !== null ? $q->byDepartment($bag->departmentId) : $q)
            ->when($bag->academicYearId !== null, function (Builder $q) use ($bag) {
                $q->whereHas('taskAssignments.task.annualPlan', fn (Builder $t) => $t->where('academic_year_id', $bag->academicYearId));
            })
            ->when(! empty($bag->search), function (Builder $q) use ($bag) {
                $search = '%'.$bag->search.'%';
                $q->where(function (Builder $e) use ($search) {
                    $e->where('first_name', 'like', $search)
                        ->orWhere('middle_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search);
                });
            })
            ->get();

        // Bulk report/target aggregates — grouped SQL keyed by employee id so
        // the row mapper never issues a query per employee (no N+1).
        $aggregates = $this->aggregates($employees->pluck('id')->all(), $bag);

        $employees = $employees
            ->map(fn (Employee $employee) => $this->row($employee, $bag, $aggregates[$employee->id] ?? []))
            ->all();

        $this->sortRows($employees, $bag);

        $ranked = [];
        foreach (array_values($employees) as $index => $row) {
            $ranked[] = ['rank' => $index + 1] + $row;
        }

        return $ranked;
    }

    /**
     * Single-employee drill-down: summary, recent assignments and a monthly
     * series — the last hop before the task-level detail.
     *
     * @return array<string, mixed>
     */
    public function detail(User $user, Employee $employee, ReportFilterBag $bag): array
    {
        $aggregates = $this->aggregates([(int) $employee->id], $bag);

        $row = $this->row($employee, $bag, $aggregates[$employee->id] ?? []);

        $recent = TaskAssignment::query()
            ->forEmployee($employee->id)
            ->with('task.department')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (TaskAssignment $assignment) => [
                'assignment_id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'task_code' => $assignment->task?->task_code,
                'task_title' => $assignment->task?->title,
                'department_name' => $assignment->task?->department?->name,
                'status' => $assignment->status,
                'priority' => $assignment->task?->priority,
                'due_date' => $assignment->due_date?->toDateString(),
                'completed_at' => $assignment->completed_at?->toIso8601String(),
                'progress_percentage' => $assignment->progress_percentage,
            ])
            ->all();

        return ['recent_assignments' => $recent] + $row;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return array<string, mixed>
     */
    private function row(Employee $employee, ReportFilterBag $bag, array $aggregates = []): array
    {
        $base = TaskAssignment::query()->forEmployee($employee->id);
        if ($bag->academicYearId !== null) {
            $base->whereHas('task.annualPlan', fn (Builder $t) => $t->where('academic_year_id', $bag->academicYearId));
        }

        $total = $base->clone()->count();

        $completedQuery = $this->constrainCompletedBag($base->clone()->completed(), $bag);
        $completed = $completedQuery->clone()->count();
        $split = $this->onTimeSplit($completedQuery->get(['completed_at', 'due_date']));

        $nextDue = $base->clone()
            ->whereIn('status', ['assigned', 'accepted', 'in_progress', 'submitted'])
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->value('due_date');

        $completionRate = $this->rate($completed, $total);

        return [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->fullName,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name,
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $base->clone()->pending()->count(),
            'overdue_tasks' => $base->clone()->overdue()->count(),
            'on_time' => $split['on_time'],
            'late' => $split['late'],
            'completion_rate' => $completionRate,
            'on_time_rate' => $this->rate($split['on_time'], $completed),
            'reports_submitted' => $aggregates['reports_submitted'] ?? 0,
            'late_reports' => $aggregates['late_reports'] ?? 0,
            'approval_rate' => $aggregates['approval_rate'] ?? null,
            'target_achievement' => $aggregates['target_achievement'] ?? null,
            'total_targets' => $aggregates['total_targets'] ?? 0,
            'achieved_targets' => $aggregates['achieved_targets'] ?? 0,
            'performance_status' => $total > 0
                ? PerformanceRating::fromScore($completionRate)->value
                : null,
            'next_due_date' => $nextDue?->toDateString(),
        ];
    }

    /**
     * Report/target aggregates for the supplied employees, keyed by id.
     *
     * Reports use the canonical lateness rule so the employee table and the
     * Submission Monitoring tab always agree on "late reports".
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function aggregates(array $employeeIds, ReportFilterBag $bag): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $excluded = [ReportStatus::Draft->value, ReportStatus::Cancelled->value, ReportStatus::Archived->value];

        $reports = Report::query()
            ->selectRaw('employee_id, count(*) as reports_submitted')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as approved_reports', [ReportStatus::Approved->value])
            ->selectRaw(
                'sum(case when status in (?, ?, ?) then 1 else 0 end) as reviewed_reports',
                [ReportStatus::Approved->value, ReportStatus::Rejected->value, ReportStatus::RevisionRequired->value],
            )
            ->whereIn('employee_id', $employeeIds)
            ->whereNotIn('status', $excluded)
            ->when($bag->academicYearId !== null, fn (Builder $q) => $q->whereHas(
                'task.annualPlan',
                fn (Builder $t) => $t->where('academic_year_id', $bag->academicYearId),
            ))
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $late = Report::query()
            ->selectRaw('employee_id, count(*) as late_reports')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('id', HrDashboardService::lateReportIdsQuery())
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $targets = EmployeeTarget::query()
            ->selectRaw('employee_id, count(*) as total_targets')
            ->selectRaw('sum(target_value) as total_target_value')
            ->selectRaw(
                'sum(case when status in (?, ?, ?) then 1 else 0 end) as achieved_targets',
                ['achieved', 'completed', 'approved'],
            )
            ->whereIn('employee_id', $employeeIds)
            ->when($bag->academicYearId !== null, fn (Builder $q) => $q->whereHas(
                'annualPlan',
                fn (Builder $a) => $a->where('academic_year_id', $bag->academicYearId),
            ))
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        // Actual accomplishment = completed tasks that operationalize the
        // employee's targets.
        $actuals = DB::table('tasks')
            ->join('employee_targets', 'tasks.employee_target_id', '=', 'employee_targets.id')
            ->whereIn('employee_targets.employee_id', $employeeIds)
            ->whereNull('tasks.deleted_at')
            ->whereNull('employee_targets.deleted_at')
            ->where('tasks.status', 'completed')
            ->selectRaw('employee_targets.employee_id as employee_id, count(*) as actual_value')
            ->groupBy('employee_targets.employee_id')
            ->pluck('actual_value', 'employee_id');

        $aggregates = [];
        foreach ($employeeIds as $employeeId) {
            $report = $reports->get($employeeId);
            $target = $targets->get($employeeId);
            $lateRow = $late->get($employeeId);

            $submitted = (int) ($report->reports_submitted ?? 0);
            $reviewed = (int) ($report->reviewed_reports ?? 0);
            $approved = (int) ($report->approved_reports ?? 0);
            $targetValue = (float) ($target->total_target_value ?? 0);
            $actual = (int) ($actuals[$employeeId] ?? 0);

            $aggregates[$employeeId] = [
                'reports_submitted' => $submitted,
                'late_reports' => (int) ($lateRow->late_reports ?? 0),
                'approval_rate' => $reviewed > 0 ? round(($approved / $reviewed) * 100, 2) : null,
                'target_achievement' => $targetValue > 0 ? round(($actual / $targetValue) * 100, 2) : null,
                'total_targets' => (int) ($target->total_targets ?? 0),
                'achieved_targets' => (int) ($target->achieved_targets ?? 0),
            ];
        }

        return $aggregates;
    }

    /**
     * Constrain a completed-assignment query to the requested period.
     */
    private function constrainCompletedBag(Builder $query, ReportFilterBag $bag): Builder
    {
        if ($bag->month !== null && $bag->year !== null) {
            return $query
                ->whereYear('completed_at', $bag->year)
                ->whereMonth('completed_at', $bag->month);
        }

        if ($bag->fromDate !== null) {
            $query->whereDate('completed_at', '>=', $bag->fromDate);
        }

        if ($bag->toDate !== null) {
            $query->whereDate('completed_at', '<=', $bag->toDate);
        }

        return $query;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sortRows(array &$rows, ReportFilterBag $bag): void
    {
        $field = $bag->sort;
        $direction = $bag->direction === 'desc' ? -1 : 1;

        usort($rows, function (array $a, array $b) use ($field, $direction) {
            $left = $a[$field] ?? null;
            $right = $b[$field] ?? null;

            $cmp = match (true) {
                is_numeric($left) && is_numeric($right) => $left <=> $right,
                $left === null && $right === null => 0,
                $left === null => 1,
                $right === null => -1,
                default => strcasecmp((string) $left, (string) $right),
            };

            if ($cmp !== 0) {
                return $cmp * $direction;
            }

            // Deterministic tie-break: name always ascends.
            return strcasecmp($a['employee_name'] ?? '', $b['employee_name'] ?? '');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function paginate(array $rows, ReportFilterBag $bag): LengthAwarePaginator
    {
        $total = count($rows);
        $offset = ($bag->page - 1) * $bag->perPage;
        $slice = array_slice($rows, $offset, $bag->perPage);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $bag->perPage,
            $bag->page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }
}
