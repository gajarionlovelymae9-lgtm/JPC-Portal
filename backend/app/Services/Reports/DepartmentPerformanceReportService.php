<?php

namespace App\Services\Reports;

use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\EmployeeTarget;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Services\Hr\HrDashboardService;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Department Performance Report: ranked, sortable, paginated per-department
 * KPIs plus a single-department drill-down.
 *
 * KPI columns mirror the employee report so both tables reconcile to the same
 * totals when drilled into.
 */
class DepartmentPerformanceReportService
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
        $departments = Department::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $q) => $q->where('id', $departmentId), fn (Builder $q) => $bag->departmentId !== null ? $q->where('id', $bag->departmentId) : $q)
            ->when(! empty($bag->search), function (Builder $q) use ($bag) {
                $search = '%'.$bag->search.'%';
                $q->where(function (Builder $d) use ($search) {
                    $d->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search);
                });
            })
            ->get();

        $aggregates = $this->aggregates($departments->pluck('id')->all(), $bag);

        $departments = $departments
            ->map(fn (Department $department) => $this->row($department, $bag, $aggregates[$department->id] ?? []))
            ->all();

        $this->sortRows($departments, $bag);

        $ranked = [];
        foreach (array_values($departments) as $index => $row) {
            $ranked[] = ['rank' => $index + 1] + $row;
        }

        return $ranked;
    }

    /**
     * Single-department drill-down: KPIs plus a feed of recent tasks for the
     * department → task hop.
     *
     * @return array<string, mixed>
     */
    public function detail(User $user, Department $department, ReportFilterBag $bag): array
    {
        $aggregates = $this->aggregates([(int) $department->id], $bag);

        $row = $this->row($department, $bag, $aggregates[$department->id] ?? []);

        $recentTasks = Task::query()
            ->byDepartment((int) $department->id)
            ->with('assignments.employee')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Task $task) => [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'completed_at' => $task->completed_at?->toIso8601String(),
                'assignee_names' => $task->assignments
                    ->map(fn ($assignment) => $assignment->employee?->fullName)
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->all();

        return ['recent_tasks' => $recentTasks] + $row;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return array<string, mixed>
     */
    private function row(Department $department, ReportFilterBag $bag, array $aggregates = []): array
    {
        $base = Task::query()->byDepartment((int) $department->id);
        if ($bag->academicYearId !== null) {
            $base->whereHas('annualPlan', fn (Builder $q) => $q->where('academic_year_id', $bag->academicYearId));
        }
        if ($bag->status !== null) {
            $base->where('status', $bag->status);
        }
        if ($bag->priority !== null) {
            $base->where('priority', $bag->priority);
        }

        $total = $base->clone()->count();

        $completedQuery = $this->constrainCompletedBag($base->clone()->completed(), $bag);
        $completed = $completedQuery->clone()->count();
        $split = $this->onTimeSplit($completedQuery->get(['completed_at', 'due_date']));

        $nextDue = $base->clone()
            ->whereIn('status', ['not_started', 'assigned', 'accepted', 'in_progress', 'submitted', 'under_review'])
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->value('due_date');

        return [
            'department_id' => $department->id,
            'department_name' => $department->name,
            'department_code' => $department->code,
            'employee_count' => $department->employees()->active()->count(),
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $base->clone()->pending()->count(),
            'overdue_tasks' => $base->clone()->overdue()->count(),
            'on_time' => $split['on_time'],
            'late' => $split['late'],
            'completion_rate' => $this->rate($completed, $total),
            'on_time_rate' => $this->rate($split['on_time'], $completed),
            'reports_submitted' => $aggregates['reports_submitted'] ?? 0,
            'late_reports' => $aggregates['late_reports'] ?? 0,
            'approval_rate' => $aggregates['approval_rate'] ?? null,
            'on_time_submission_rate' => $aggregates['on_time_submission_rate'] ?? null,
            'target_achievement' => $aggregates['target_achievement'] ?? null,
            'total_targets' => $aggregates['total_targets'] ?? 0,
            'achieved_targets' => $aggregates['achieved_targets'] ?? 0,
            'next_due_date' => $nextDue?->toDateString(),
        ];
    }

    /**
     * Report/target aggregates per department, keyed by department id.
     *
     * Reports are linked through their submitting employee and use the
     * canonical lateness rule shared with the Reporting Hub.
     *
     * @param  list<int>  $departmentIds
     * @return array<int, array<string, mixed>>
     */
    private function aggregates(array $departmentIds, ReportFilterBag $bag): array
    {
        if ($departmentIds === []) {
            return [];
        }

        $excluded = [ReportStatus::Draft->value, ReportStatus::Cancelled->value, ReportStatus::Archived->value];

        $reportBase = fn () => Report::query()
            ->join('employees', 'employees.id', '=', 'reports.employee_id')
            ->whereNull('employees.deleted_at')
            ->whereIn('employees.department_id', $departmentIds)
            ->when($bag->academicYearId !== null, fn (Builder $q) => $q->whereHas(
                'task.annualPlan',
                fn (Builder $t) => $t->where('academic_year_id', $bag->academicYearId),
            ));

        $reports = $reportBase()
            ->selectRaw('employees.department_id as department_id, count(*) as reports_submitted')
            ->selectRaw('sum(case when reports.status = ? then 1 else 0 end) as approved_reports', [ReportStatus::Approved->value])
            ->selectRaw(
                'sum(case when reports.status in (?, ?, ?) then 1 else 0 end) as reviewed_reports',
                [ReportStatus::Approved->value, ReportStatus::Rejected->value, ReportStatus::RevisionRequired->value],
            )
            ->whereNotIn('reports.status', $excluded)
            ->groupBy('employees.department_id')
            ->get()
            ->keyBy('department_id');

        $late = $reportBase()
            ->selectRaw('employees.department_id as department_id, count(*) as late_reports')
            ->whereIn('reports.id', HrDashboardService::lateReportIdsQuery())
            ->groupBy('employees.department_id')
            ->get()
            ->keyBy('department_id');

        // On-time submission rate only considers reports that were actually
        // submitted, so outstanding overdue reports never inflate the
        // denominator.
        $lateSubmitted = $reportBase()
            ->selectRaw('employees.department_id as department_id, count(*) as late_reports')
            ->whereIn('reports.id', HrDashboardService::lateReportIdsQuery('submitted'))
            ->groupBy('employees.department_id')
            ->get()
            ->keyBy('department_id');

        $targets = EmployeeTarget::query()
            ->join('employees', 'employees.id', '=', 'employee_targets.employee_id')
            ->whereNull('employees.deleted_at')
            ->whereIn('employees.department_id', $departmentIds)
            ->when($bag->academicYearId !== null, fn (Builder $q) => $q->whereHas(
                'annualPlan',
                fn (Builder $a) => $a->where('academic_year_id', $bag->academicYearId),
            ))
            ->selectRaw('employees.department_id as department_id, count(*) as total_targets')
            ->selectRaw('sum(employee_targets.target_value) as total_target_value')
            ->selectRaw(
                'sum(case when employee_targets.status in (?, ?, ?) then 1 else 0 end) as achieved_targets',
                ['achieved', 'completed', 'approved'],
            )
            ->groupBy('employees.department_id')
            ->get()
            ->keyBy('department_id');

        $actuals = DB::table('tasks')
            ->join('employee_targets', 'tasks.employee_target_id', '=', 'employee_targets.id')
            ->join('employees', 'employees.id', '=', 'employee_targets.employee_id')
            ->whereNull('employees.deleted_at')
            ->whereNull('tasks.deleted_at')
            ->whereNull('employee_targets.deleted_at')
            ->whereIn('employees.department_id', $departmentIds)
            ->where('tasks.status', 'completed')
            ->selectRaw('employees.department_id as department_id, count(*) as actual_value')
            ->groupBy('employees.department_id')
            ->pluck('actual_value', 'department_id');

        $aggregates = [];
        foreach ($departmentIds as $departmentId) {
            $report = $reports->get($departmentId);
            $lateRow = $late->get($departmentId);
            $lateSubmittedRow = $lateSubmitted->get($departmentId);
            $target = $targets->get($departmentId);

            $submitted = (int) ($report->reports_submitted ?? 0);
            $reviewed = (int) ($report->reviewed_reports ?? 0);
            $approved = (int) ($report->approved_reports ?? 0);
            $lateCount = (int) ($lateRow->late_reports ?? 0);
            $lateSubmittedCount = (int) ($lateSubmittedRow->late_reports ?? 0);
            $targetValue = (float) ($target->total_target_value ?? 0);
            $actual = (int) ($actuals[$departmentId] ?? 0);

            $aggregates[$departmentId] = [
                'reports_submitted' => $submitted,
                'late_reports' => $lateCount,
                'approval_rate' => $reviewed > 0 ? round(($approved / $reviewed) * 100, 2) : null,
                'on_time_submission_rate' => $submitted > 0
                    ? round((max(0, $submitted - $lateSubmittedCount) / $submitted) * 100, 2)
                    : null,
                'target_achievement' => $targetValue > 0 ? round(($actual / $targetValue) * 100, 2) : null,
                'total_targets' => (int) ($target->total_targets ?? 0),
                'achieved_targets' => (int) ($target->achieved_targets ?? 0),
            ];
        }

        return $aggregates;
    }

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

            return strcasecmp($a['department_name'] ?? '', $b['department_name'] ?? '');
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
