<?php

namespace App\Services\Hr;

use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Report;
use App\Models\TaskAssignment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HR dashboard + directory aggregation (Phase 3.3-W).
 *
 * Documents that HR operates on EMPLOYEE-centric, institution-wide surfaces
 * (dashboard KPIs, directory, monitoring) and is explicitly NOT a proxy for
 * Admin (settings/RBAC/AI-config/OCR-config/system-config live behind
 * `settings.manage` which HR does not hold — enforced at the route layer).
 *
 * Honesty contract — nothing in here invents a number:
 *  - every count is a SQL aggregate over the live tables;
 *  - a rate is `null` when its denominator is zero, never 0;
 *  - badges are READ from `employee_badges` (granted by
 *    AchievementEvaluationService) and are never synthesised for a name;
 *  - "late" always reuses the canonical effective-deadline rule
 *    (`due_date ?? task.due_date`) documented on
 *    AdminExecutiveDashboardService, rather than a second definition.
 *
 * The four headline cards on the dashboard (Total Employees, Reports Submitted,
 * Pending Reports, Late Reports) and the Task Completion Rate card all come out
 * of `kpis()`, so the cards, the tables below them and the employee profile
 * can never report different numbers for the same thing:
 *
 *  - "late" has ONE definition for the whole HR surface: the submission flag
 *    OR a report that is demonstrably late from its own dates (handed in after
 *    its effective deadline, or still outstanding past it). See
 *    `lateReportCondition()` — the same rule the row view applies.
 *  - "pending" is derived from `ReportStatus` itself, so a new status cannot
 *    silently drop out of the count. See `reportAwaitingActionStatuses()`.
 *  - the monthly figures use the calendar month on screen, and a month with no
 *    activity reports `null` for its rate rather than a fake 0%.
 *
 * HR is institution-wide by design: unlike the department-head portals there is
 * no department scope to resolve, because `hr.dashboard.view` is held only by
 * HR and by admin, both of which are institution-level roles.
 */
class HrDashboardService
{
    /**
     * `task_assignments.status` values that are finished work.
     *
     * `approved` is included because
     * AdminExecutiveDashboardService::DONE_STATUSES treats it as done, so HR
     * must not disagree with Admin on the same institution-wide number.
     */
    public const ASSIGNMENT_DONE = ['completed', 'approved'];

    /**
     * Statuses that end an assignment for lateness purposes.
     *
     * Deliberately NOT `ASSIGNMENT_DONE`: `approved` is terminal for the KPI
     * "completed tasks" counters but an approved assignment is still counted as
     * overdue if it was never completed, matching the rule already used by
     * `overdueScope()`/`overdueRaw()` and by
     * `AdminExecutiveDashboardService::overdueAssignments()`.
     */
    public const OVERDONE_STATUSES = ['completed', 'cancelled'];

    /**
     * `task_assignments.status` values that are still open, so they count as
     * "pending". `not_started` is included because it is the column's DB
     * default, so a row created without an explicit status must not silently
     * vanish from the pending count.
     */
    public const ASSIGNMENT_PENDING = ['not_started', 'assigned', 'accepted', 'in_progress', 'submitted'];

    /**
     * Report statuses that are still moving through review.
     * Sourced from App\Enums\ReportStatus so the string literals are not
     * scattered; `processing`/`resubmitted` are in-flight per the enum.
     */
    public const REPORT_PENDING = ['submitted', 'processing', 'resubmitted', 'under_review'];

    /** Report statuses the employee still has to act on. */
    public const REPORT_REVISION = 'revision_required';

    /**
     * Report statuses where the employee still owes the institution something:
     * a report that was never handed in, or one that came back for revision.
     *
     * This is the "awaiting submission" half of the Pending Reports card; the
     * other half is the in-flight review statuses in `REPORT_PENDING`. The two
     * sets are disjoint, so the card can show the split without any report
     * being counted twice.
     *
     * @var array<int, string>
     */
    public const REPORT_AWAITING_SUBMISSION = ['draft', 'revision_required'];

    /**
     * `task_assignments.status` values that stop being applicable work.
     *
     * A cancelled assignment is void: it is excluded from the monthly
     * completion rate so a cancelled task cannot drag the rate down.
     */
    public const ASSIGNMENT_CANCELLED = 'cancelled';

    /**
     * Canonical `task_assignments.status` filter values, and the single source
     * for both `options()` and the controller's `status` allow-list.
     *
     * `not_started` is first because it is the column's DB default, and
     * `approved` is included because ASSIGNMENT_DONE counts it as finished —
     * omitting either would give HR a dropdown that cannot show rows the KPI
     * cards are already counting.
     *
     * @var array<string, string>
     */
    public const TASK_STATUSES = [
        'not_started' => 'Not Started',
        'assigned' => 'Assigned',
        'accepted' => 'Accepted',
        'in_progress' => 'In Progress',
        'submitted' => 'Submitted',
        'completed' => 'Completed',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    /**
     * `employees.employment_status` values this application writes. The column
     * is a free string, so this is a filter aid rather than a schema constraint;
     * it is the same list `EmployeeFactory` and the employee forms use.
     *
     * @var array<int, string>
     */
    public const EMPLOYMENT_STATUSES = ['active', 'inactive', 'on_leave', 'suspended', 'terminated'];

    /**
     * Report status values for the `status` allow-list, straight from the enum so
     * a new case cannot be filterable-in-theory-only.
     *
     * @return array<int, string>
     */
    public static function reportStatusValues(): array
    {
        return array_map(fn (ReportStatus $status) => $status->value, ReportStatus::cases());
    }

    /**
     * Report statuses that end the workflow.
     *
     * Derived from the enum rather than a hand-kept list, so a status that is
     * added later cannot silently change what "pending" or "late" means.
     *
     * @return array<int, string>
     */
    public static function reportTerminalStatuses(): array
    {
        return array_values(array_map(
            fn (ReportStatus $status) => $status->value,
            array_filter(ReportStatus::cases(), fn (ReportStatus $status) => $status->isTerminal()),
        ));
    }

    /**
     * Report statuses that still need somebody to act: the employee has to
     * submit or revise, or a reviewer has to finish the review.
     *
     * This is the Pending Reports card, and it follows the report status
     * definitions in `ReportStatus` exactly — every report sits in exactly one
     * status, so a report is counted once and only once.
     *
     * @return array<int, string>
     */
    public static function reportAwaitingActionStatuses(): array
    {
        return array_values(array_map(
            fn (ReportStatus $status) => $status->value,
            array_filter(ReportStatus::cases(), fn (ReportStatus $status) => ! $status->isTerminal()),
        ));
    }

    /**
     * Row shape for the dashboard KPI header block.
     *
     * @return array{employees: array<string, int>, tasks: array<string, mixed>, reports: array<string, mixed>, kpi: array<string, float|null>, trends: array<string, mixed>}
     */
    public function kpis(): array
    {
        $metrics = $this->institutionMetrics();
        $metrics['trends'] = $this->trends();

        return $metrics;
    }

    /**
     * Canonical institution-wide aggregates, shared by the HR dashboard and the
     * Phase 9 Reporting Hub so the two surfaces can never disagree.
     *
     * Pass a period to window tasks and reports (the Reporting Hub's period
     * selector); pass nothing for the dashboard's all-time cards. A department
     * narrows every count to that department's employees when the Reporting Hub
     * is scoped to one department.
     *
     * @return array{employees: array<string, int>, tasks: array<string, mixed>, reports: array<string, mixed>, kpi: array<string, float|null>, has_data: bool}
     */
    public function institutionMetrics(?Carbon $start = null, ?Carbon $end = null, ?int $departmentId = null): array
    {
        $periodStart = $start?->copy()->startOfDay();
        $periodEnd = $end?->copy()->endOfDay();
        // Keep the bound period values as instances (rather than null checks on
        // two variables) so a half-open range can never silently widen a query.
        $hasPeriod = $periodStart !== null && $periodEnd !== null;

        $employeeIds = null;
        if ($departmentId !== null) {
            $employeeIds = DB::table('employees')
                ->whereNull('deleted_at')
                ->where('department_id', $departmentId)
                ->select('id');
        }

        // `employees` is soft-deleted, so the raw aggregate filters
        // `deleted_at` explicitly to match Eloquent's default scope. The
        // tasks and reports aggregates do the same for the same reason: the
        // card above the table and the table itself must count the same rows.
        $employees = DB::table('employees')
            ->whereNull('deleted_at')
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(employment_status = "active"), 0) as active')
            ->first();

        $tasks = DB::table('task_assignments')
            ->whereNull('deleted_at')
            ->when($hasPeriod, fn ($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd]))
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(status in ('.$this->placeholders(self::ASSIGNMENT_DONE).')), 0) as completed', self::ASSIGNMENT_DONE)
            ->selectRaw('coalesce(sum(status in ('.$this->placeholders(self::ASSIGNMENT_PENDING).')), 0) as pending', self::ASSIGNMENT_PENDING)
            ->selectRaw('coalesce(sum(status = ?), 0) as cancelled', [self::ASSIGNMENT_CANCELLED])
            ->first();

        // Effective-deadline rule (`due_date ?? task.due_date`) so this matches
        // AdminExecutiveDashboardService::overdueAssignments().
        $overdueQuery = DB::table('task_assignments')
            ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->whereNull('task_assignments.deleted_at')
            ->when($hasPeriod, fn ($query) => $query->whereBetween('task_assignments.created_at', [$periodStart, $periodEnd]))
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('task_assignments.employee_id', $employeeIds));
        self::applyOverdueAssignmentRule($overdueQuery);

        // The period's report set: a report belongs to the window it was
        // submitted in, or - when it was never submitted - the window it was
        // created in. Drafts therefore stay visible as awaiting action without
        // being mistaken for submitted work.
        $reportBase = DB::table('reports as r')
            ->whereNull('r.deleted_at')
            ->when($hasPeriod, fn ($query) => $query->where(function ($window) use ($periodStart, $periodEnd) {
                $window
                    ->whereRaw('coalesce(r.submitted_at, r.submission_date) between ? and ?', [$periodStart, $periodEnd])
                    ->orWhere(function ($unsubmitted) use ($periodStart, $periodEnd) {
                        $unsubmitted
                            ->whereNull('r.submitted_at')
                            ->whereNull('r.submission_date')
                            ->whereBetween('r.created_at', [$periodStart, $periodEnd]);
                    });
            }))
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('r.employee_id', $employeeIds));

        $reports = (clone $reportBase)
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(r.status = "approved"), 0) as approved')
            ->selectRaw('coalesce(sum(r.status = "draft"), 0) as draft')
            ->selectRaw('coalesce(sum(r.status in ('.$this->placeholders(self::REPORT_PENDING).')), 0) as pending', self::REPORT_PENDING)
            ->selectRaw('coalesce(sum(r.status = ?), 0) as revision_required', [self::REPORT_REVISION])
            ->selectRaw('coalesce(sum(r.status = "rejected"), 0) as rejected')
            // One status IN (...) query, so a report can never be counted
            // twice: the pending card counts rows, not status buckets.
            ->selectRaw('coalesce(sum(r.status in ('.$this->placeholders(self::reportAwaitingActionStatuses()).')), 0) as awaiting_action', self::reportAwaitingActionStatuses())
            ->selectRaw('coalesce(sum(r.status in ('.$this->placeholders(self::REPORT_AWAITING_SUBMISSION).')), 0) as awaiting_submission', self::REPORT_AWAITING_SUBMISSION)
            ->first();

        $reportIds = (clone $reportBase)->select('r.id');

        // One definition of "late" for the whole HR surface: the submission
        // flag, plus a report that is demonstrably late from its own dates.
        $late = $this->lateReportsIn($reportIds, 'all');
        $lateSubmitted = $this->lateReportsIn($reportIds, 'submitted');
        $lateOutstanding = $this->lateReportsIn($reportIds, 'outstanding');

        $totalEmployees = (int) ($employees->total ?? 0);
        $totalTasks = (int) ($tasks->total ?? 0);
        $completedTasks = (int) ($tasks->completed ?? 0);
        $applicableTasks = max(0, $totalTasks - (int) ($tasks->cancelled ?? 0));
        $submitted = (int) $reports->total - (int) $reports->draft;

        return [
            'employees' => [
                'total' => $totalEmployees,
                'active' => (int) ($employees->active ?? 0),
                'inactive' => max(0, $totalEmployees - (int) ($employees->active ?? 0)),
                'departments' => Department::query()->count(),
            ],
            'tasks' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'pending' => (int) ($tasks->pending ?? 0),
                'cancelled' => $totalTasks - $applicableTasks,
                'overdue' => (int) $overdueQuery->count(),
                // The month the completion card is about, with the counts it
                // needs to draw the bar (completed vs total applicable).
                'month' => $this->monthlyTaskStats(),
            ],
            'reports' => [
                'total' => (int) $reports->total,
                'submitted' => $submitted,
                // Reports handed in during the calendar month on screen.
                'submitted_this_month' => $this->reportsSubmittedThisMonth(),
                'pending' => (int) ($reports->pending ?? 0),
                // Still require submission, revision or review.
                'awaiting_action' => (int) ($reports->awaiting_action ?? 0),
                'awaiting_submission' => (int) ($reports->awaiting_submission ?? 0),
                'late' => $late,
                'late_submitted' => $lateSubmitted,
                'late_outstanding' => $lateOutstanding,
                'approved' => (int) ($reports->approved ?? 0),
                'revision_required' => (int) ($reports->revision_required ?? 0),
                'rejected' => (int) ($reports->rejected ?? 0),
                'month' => $this->currentMonth(),
            ],
            'kpi' => [
                // A cancelled assignment is not applicable work, so it is not
                // in the denominator.
                'completion_rate' => $this->rate($completedTasks, $applicableTasks),
                // The on-time rate is about reports that were handed in, so it
                // only subtracts the late ones that were actually submitted —
                // an outstanding draft is not a missed submission.
                'on_time_rate' => $this->rate($submitted - $lateSubmitted, $submitted),
                'approval_rate' => $this->rate((int) ($reports->approved ?? 0), $submitted),
            ],
            'has_data' => $totalEmployees > 0 || $totalTasks > 0 || (int) $reports->total > 0,
        ];
    }

    /**
     * Count late reports inside an already-scoped report-id subquery.
     *
     * @param  'all'|'submitted'|'outstanding'  $mode
     */
    private function lateReportsIn(BaseBuilder $reportIds, string $mode = 'all'): int
    {
        [$sql, $bindings] = self::lateReportCondition($mode);

        return (int) DB::table('reports as r')
            ->leftJoin('tasks as t', 't.id', '=', 'r.task_id')
            ->whereNull('r.deleted_at')
            ->whereIn('r.id', $reportIds)
            ->whereRaw($sql, $bindings)
            ->count();
    }

    /**
     * The calendar month every monthly figure on the dashboard belongs to.
     *
     * @return array{period: string, label: string}
     */
    private function currentMonth(): array
    {
        $now = Carbon::now();

        return [
            'period' => $now->format('Y-m'),
            'label' => $now->format('F Y'),
        ];
    }

    /**
     * Task completion for the calendar month on screen.
     *
     * The window is the month the assignment was created, which is the same
     * window the trend series buckets by, so the card and the chart can never
     * describe different months.
     *
     * `cancelled` assignments are excluded: a cancelled assignment is not
     * applicable work, so it must not drag the rate down. Every other
     * assignment created in the month counts towards the denominator.
     *
     * @return array{period: string, label: string, total: int, completed: int, completion_rate: float|null}
     */
    private function monthlyTaskStats(): array
    {
        $month = $this->currentMonth();
        $now = Carbon::now();

        $row = DB::table('task_assignments')
            ->whereNull('deleted_at')
            ->where('status', '!=', self::ASSIGNMENT_CANCELLED)
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->where('created_at', '<=', $now->copy()->endOfMonth())
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(status in ('.$this->placeholders(self::ASSIGNMENT_DONE).')), 0) as completed', self::ASSIGNMENT_DONE)
            ->first();

        $total = (int) ($row->total ?? 0);
        $completed = (int) ($row->completed ?? 0);

        return [
            'period' => $month['period'],
            'label' => $month['label'],
            'total' => $total,
            'completed' => $completed,
            // null when nothing was assigned this month: "unmeasured", not 0%.
            'completion_rate' => $this->rate($completed, $total),
        ];
    }

    /**
     * Reports handed in during the calendar month on screen.
     *
     * "Submitted" follows the same rule as the all-time counter: a report that
     * is not a draft. The timestamp is `submitted_at`, with the older
     * `submission_date` column as the fallback.
     */
    private function reportsSubmittedThisMonth(): int
    {
        $now = Carbon::now();

        return (int) DB::table('reports')
            ->whereNull('deleted_at')
            ->where('status', '!=', ReportStatus::Draft->value)
            ->whereRaw('coalesce(submitted_at, submission_date) >= ?', [$now->copy()->startOfMonth()])
            ->whereRaw('coalesce(submitted_at, submission_date) <= ?', [$now->copy()->endOfMonth()])
            ->count();
    }

    /**
     * Real multi-month series used by the dashboard charts.
     *
     * Two passes over the same shape so the charts share an axis:
     *  - report submissions + late reports, per calendar month;
     *  - task completions + totals, per calendar month.
     *
     * Months with no activity are emitted as zeros so a gap in the data reads
     * as "nothing happened that month" rather than a broken line.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function trends(int $months = 6, ?Carbon $to = null): array
    {
        $to = $to ? $to->copy() : Carbon::now();
        $to->startOfMonth();
        $from = $to->copy()->subMonths($months - 1)->startOfMonth();

        $labels = [];
        for ($cursor = $from->copy(); $cursor->lessThanOrEqualTo($to); $cursor->addMonth()) {
            $labels[$cursor->format('Y-m')] = [
                'period' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
            ];
        }

        $reportRows = DB::table('reports')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $from->startOfDay())
            ->selectRaw('date_format(created_at, "%Y-%m") as period')
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(is_late = 1), 0) as late')
            ->selectRaw('coalesce(sum(status = "approved"), 0) as approved')
            ->groupBy('period')
            ->get();

        $taskRows = DB::table('task_assignments')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $from->startOfDay())
            ->selectRaw('date_format(created_at, "%Y-%m") as period')
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(status in ('.$this->placeholders(self::ASSIGNMENT_DONE).')), 0) as completed', self::ASSIGNMENT_DONE)
            ->groupBy('period')
            ->get();

        $reportSeries = $this->bucket($labels, $reportRows);
        $taskSeries = $this->bucket($labels, $taskRows);

        return [
            'reports' => $reportSeries,
            'tasks' => $taskSeries,
        ];
    }

    /**
     * Options for every HR dashboard filter, in one call so the page does not
     * issue a request per dropdown.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $now = Carbon::now();

        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name])->all(),
            'positions' => Position::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Position $position) => ['id' => $position->id, 'name' => $position->name])->all(),
            'academic_years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status'])
                ->map(fn (AcademicYear $year) => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status])->all(),
            'years' => $this->rangeOptions(2019, (int) $now->format('Y') + 1),
            'months' => $this->monthOptions(),
            'report_statuses' => collect(ReportStatus::cases())
                ->map(fn (ReportStatus $status) => ['value' => $status->value, 'label' => $status->label()])->all(),
            'task_statuses' => collect(self::TASK_STATUSES)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'employment_statuses' => self::EMPLOYMENT_STATUSES,
            'account_statuses' => array_map(
                fn (UserStatus $status) => $status->value,
                UserStatus::cases(),
            ),
        ];
    }

    /**
     * Employee Progress Overview — one row per employee, every column a real
     * aggregate, sortable and searchable server-side.
     *
     * @param  array<string, mixed>  $filters
     */
    public function employees(array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->perPage($filters);

        $employees = $this->employeeQuery($filters)
            ->paginate($perPage)
            ->through(fn (Employee $employee) => $this->employeeRow($employee));

        return $employees;
    }

    /**
     * Counts that sit above the employee table, so the section header does not
     * need a second request.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function employeeSummary(array $filters = []): array
    {
        $base = $this->employeeQuery($filters);

        return [
            'total' => (clone $base)->toBase()->count(),
            'active' => (clone $base)->where('employment_status', 'active')->toBase()->count(),
            'inactive' => (clone $base)->where('employment_status', '!=', 'active')->toBase()->count(),
            'with_overdue' => (clone $base)->whereHas('taskAssignments', fn (Builder $q) => $this->overdueScope($q))->toBase()->count(),
            'with_late_reports' => (clone $base)->whereHas('reports', fn (Builder $q) => $q->where('is_late', true))->toBase()->count(),
        ];
    }

    /**
     * Task Progress — assignment-level rows, which is where start date,
     * deadline, progress % and status actually live.
     *
     * @param  array<string, mixed>  $filters
     */
    public function tasks(array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->taskQuery($filters)
            // `due_date` must be selected on `task` as well: the effective
            // deadline falls back to it when the assignment has no own due date.
            ->with(['task:id,title,task_code,due_date', 'employee:id,first_name,middle_name,last_name,suffix,employee_number,department_id'])
            ->with('employee.department:id,name')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters));

        // One grouped query for the whole page instead of one per row.
        $statuses = $this->reportStatusesFor(
            collect($paginator->items())->pluck('task_id')->filter()->unique()->values()->all()
        );

        $paginator->setCollection(
            collect($paginator->items())->map(
                fn (TaskAssignment $assignment) => $this->taskRow($assignment, $statuses[$assignment->task_id] ?? null)
            )
        );

        return $paginator;
    }

    /**
     * Latest report status per task for the given task ids, so the Task
     * Progress table can show Report Status without an N+1 lookup.
     *
     * @param  array<int, mixed>  $taskIds
     * @return array<int, string>
     */
    private function reportStatusesFor(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        return DB::table('reports')
            ->select('task_id', 'status', 'updated_at')
            ->whereIn('task_id', $taskIds)
            ->orderBy('task_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('task_id')
            ->map(fn ($group) => (string) $group->first()->status)
            ->all();
    }

    /**
     * Counts above the task table.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function taskSummary(array $filters = []): array
    {
        $base = $this->taskQuery($filters);

        $row = (clone $base)
            ->toBase()
            ->selectRaw('count(*) as total')
            // Same constants as the KPI header, so the panel and the card above
            // it can never report different totals for the same filter.
            ->selectRaw('coalesce(sum(task_assignments.status in ('.$this->placeholders(self::ASSIGNMENT_DONE).')), 0) as completed', self::ASSIGNMENT_DONE)
            ->selectRaw('coalesce(sum(task_assignments.status in ('.$this->placeholders(self::ASSIGNMENT_PENDING).')), 0) as pending', self::ASSIGNMENT_PENDING)
            ->first();

        // `tasks` is joined for the inherited deadline; a LEFT JOIN keeps
        // assignments whose task row is missing (defensive: task_id is nullable
        // on the column) from silently dropping out of the totals.
        $overdue = (clone $base)
            ->toBase()
            ->leftJoin('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->select('task_assignments.id')
            ->where('task_assignments.completed_at', null)
            ->whereNotIn('task_assignments.status', ['completed', 'cancelled'])
            ->whereRaw('coalesce(task_assignments.due_date, tasks.due_date) is not null')
            ->whereRaw('coalesce(task_assignments.due_date, tasks.due_date) < ?', [now()])
            ->count();

        return [
            'total' => (int) ($row->total ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'overdue' => $overdue,
        ];
    }

    /**
     * Report Monitoring — report-level rows joined to the employee and task so
     * the table can show all three without N+1 lookups.
     *
     * `late=1` narrows the same query to the Late Reports alert table.
     *
     * @param  array<string, mixed>  $filters
     */
    public function reports(array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->perPage($filters);

        return $this->reportQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (Report $report) => $this->reportRow($report));
    }

    /**
     * Counts above the report table.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function reportSummary(array $filters = []): array
    {
        $row = $this->reportQuery($filters)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(status = "approved"), 0) as approved')
            ->selectRaw('coalesce(sum(status = "draft"), 0) as draft')
            ->selectRaw('coalesce(sum(status in ('.$this->placeholders(self::REPORT_PENDING).')), 0) as pending', self::REPORT_PENDING)
            ->selectRaw('coalesce(sum(status = ?), 0) as revision_required', [self::REPORT_REVISION])
            ->first();

        $total = (int) ($row->total ?? 0);
        $submitted = $total - (int) ($row->draft ?? 0);

        // The same rule as the Late Reports card, so the panel summary and the
        // card above it can never report different numbers.
        $late = (int) $this->reportQuery($filters)->toBase()
            ->whereIn('reports.id', self::lateReportIdsQuery())
            ->count();

        // Only the late reports that were actually handed in may reduce the
        // on-time rate: an outstanding draft is not a missed submission.
        $lateSubmitted = (int) $this->reportQuery($filters)->toBase()
            ->whereIn('reports.id', self::lateReportIdsQuery('submitted'))
            ->count();

        return [
            'total' => $total,
            'submitted' => $submitted,
            'pending' => (int) ($row->pending ?? 0),
            'late' => $late,
            'approved' => (int) ($row->approved ?? 0),
            'revision_required' => (int) ($row->revision_required ?? 0),
            'on_time_rate' => $this->rate($submitted - $lateSubmitted, $submitted),
            'approval_rate' => $this->rate((int) ($row->approved ?? 0), $submitted),
        ];
    }

    /**
     * Monthly Employee Achievement.
     *
     * `monthly_achievements` is the system-owned rollup written by
     * AchievementReportService, so `overall_score` and
     * `is_employee_of_month` are read as stored rather than recomputed here
     * (recomputing would create a fourth, divergent scoring formula).
     *
     * Badges come from the real `employee_badges` grants. An employee with no
     * grants returns an empty list — they are never given one by name.
     *
     * @param  array<string, mixed>  $filters
     */
    public function achievements(array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->perPage($filters);

        $query = DB::table('monthly_achievements as ma')
            ->join('employees', 'employees.id', '=', 'ma.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->when(! empty($filters['year']), fn ($q) => $q->where('ma.year', (int) $filters['year']))
            ->when(! empty($filters['month']), fn ($q) => $q->where('ma.month', (int) $filters['month']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('employees.department_id', (int) $filters['department_id']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn ($inner) => $inner
                    ->where('employees.first_name', 'like', $term)
                    ->orWhere('employees.last_name', 'like', $term)
                    ->orWhere('employees.employee_number', 'like', $term));
            })
            ->orderByDesc('ma.year')
            ->orderByDesc('ma.month')
            ->orderByDesc('ma.overall_score')
            ->orderBy('employees.last_name')
            ->select([
                'ma.id', 'ma.employee_id', 'ma.month', 'ma.year', 'ma.completion_rate',
                'ma.on_time_rate', 'ma.approved_reports', 'ma.completed_tasks', 'ma.overall_score',
                'ma.rank', 'ma.is_employee_of_month',
                'employees.first_name', 'employees.middle_name', 'employees.last_name',
                'employees.employee_number', 'departments.name as department_name',
            ]);

        $page = $query->paginate($perPage);

        // One extra query for the whole page's badge grants rather than one
        // per employee.
        $badges = $this->badgesFor($page->pluck('employee_id')->all());

        return $page->through(function (object $row) use ($badges) {
            return [
                'id' => (int) $row->id,
                'employee_id' => (int) $row->employee_id,
                // Same rule as the Employee::full_name accessor: skip the empty
                // parts instead of sprintf-ing them into a double space.
                'name' => collect([$row->first_name, $row->middle_name, $row->last_name])
                    ->filter(fn (?string $part): bool => filled($part))
                    ->implode(' '),
                'employee_number' => $row->employee_number,
                'department' => $row->department_name,
                'period' => sprintf('%d-%02d', $row->year, $row->month),
                'month' => (int) $row->month,
                'year' => (int) $row->year,
                'completion_rate' => round((float) $row->completion_rate, 2),
                'on_time_rate' => round((float) $row->on_time_rate, 2),
                'approved_reports' => (int) $row->approved_reports,
                'completed_tasks' => (int) $row->completed_tasks,
                'overall_score' => round((float) $row->overall_score, 2),
                'rank' => $row->rank === null ? null : (int) $row->rank,
                'employee_of_the_month' => (bool) $row->is_employee_of_month,
                'badges' => $badges[$row->employee_id] ?? [],
            ];
        });
    }

    /**
     * Badge grants for the given employees, read from `employee_badges`.
     *
     * @param  array<int, mixed>  $employeeIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function badgesFor(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return DB::table('employee_badges')
            ->join('badges', 'badges.id', '=', 'employee_badges.badge_id')
            ->whereIn('employee_badges.employee_id', $employeeIds)
            ->whereNull('badges.deleted_at')
            ->orderBy('employee_badges.awarded_at')
            ->select([
                'employee_badges.employee_id',
                'badges.id', 'badges.name', 'badges.code', 'badges.icon',
                'badges.description', 'badges.criteria_type', 'badges.criteria_value',
                'employee_badges.period_start', 'employee_badges.period_end', 'employee_badges.awarded_at',
            ])
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($group) => $group->map(fn (object $badge) => [
                'id' => (int) $badge->id,
                'name' => $badge->name,
                'code' => $badge->code,
                'icon' => $badge->icon,
                'description' => $badge->description,
                'criteria_type' => $badge->criteria_type,
                'criteria_value' => json_decode((string) $badge->criteria_value, true),
                'period_start' => $badge->period_start,
                'period_end' => $badge->period_end,
                'awarded_at' => $badge->awarded_at,
            ])->values()->all())
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Query builders */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $filters
     */
    private function employeeQuery(array $filters): Builder
    {
        $sort = $this->sortKey($filters, 'sort', [
            'name', 'completion_rate', 'completed_tasks', 'pending_tasks', 'late_tasks',
        ]);

        return Employee::query()
            ->with('department:id,name')
            ->with('position:id,name')
            ->with('user:id,name,email,status')
            ->withCount([
                'taskAssignments as assigned_tasks',
                'taskAssignments as completed_tasks' => fn (Builder $q) => $q->whereIn('status', self::ASSIGNMENT_DONE),
                'taskAssignments as pending_tasks' => fn (Builder $q) => $q->whereIn('status', self::ASSIGNMENT_PENDING),
                'taskAssignments as late_tasks' => fn (Builder $q) => $this->overdueScope($q),
                'reports as submitted_reports' => fn (Builder $q) => $q->where('status', '!=', 'draft'),
                'reports as approved_reports' => fn (Builder $q) => $q->where('status', 'approved'),
                // The same canonical lateness rule the Late Reports card uses,
                // so the directory and the card agree about who is late.
                'reports as late_reports' => fn (Builder $q) => $q->whereIn('reports.id', self::lateReportIdsQuery()),
                // Only the late reports that were handed in may reduce the
                // on-time rate.
                'reports as late_submitted_reports' => fn (Builder $q) => $q->whereIn('reports.id', self::lateReportIdsQuery('submitted')),
            ])
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->where('department_id', (int) $filters['department_id']))
            ->when(! empty($filters['position_id']), fn (Builder $q) => $q->where('position_id', (int) $filters['position_id']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('employment_status', (string) $filters['status']))
            ->when(! empty($filters['from_date']), fn (Builder $q) => $q->where('created_at', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn (Builder $q) => $q->where('created_at', '<=', $filters['to_date'].' 23:59:59'))
            ->when(! empty($filters['account_status']), fn (Builder $q) => $q->whereHas(
                'user',
                fn (Builder $inner) => $inner->where('status', (string) $filters['account_status']),
            ))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('first_name', 'like', $term)
                    ->orWhere('middle_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_number', 'like', $term)
                    // Email lives on the linked login account, so the directory
                    // search reaches through `users` rather than a second copy.
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $term)));
            })
            // `name` is a first/last sort, so it is whitelisted explicitly; the
            // metric sorts use the withCount aliases, which cannot be injected.
            ->when($sort === 'name', fn (Builder $q) => $q->orderBy('last_name')->orderBy('first_name'))
            ->when($sort === 'completion_rate', fn (Builder $q) => $q->orderBy('completed_tasks')->orderByDesc('assigned_tasks'))
            ->when($sort === 'completed_tasks', fn (Builder $q) => $q->orderByDesc('completed_tasks'))
            ->when($sort === 'pending_tasks', fn (Builder $q) => $q->orderByDesc('pending_tasks'))
            ->when($sort === 'late_tasks', fn (Builder $q) => $q->orderByDesc('late_tasks'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function taskQuery(array $filters): Builder
    {
        // Columns are table-qualified because `taskSummary()` reuses this base
        // for an aggregate that joins `tasks` — `tasks` also has `status` and
        // `due_date`, so an unqualified reference is ambiguous there.
        return TaskAssignment::query()
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->whereHas(
                'employee',
                fn (Builder $e) => $e->where('department_id', (int) $filters['department_id'])
            ))
            ->when(! empty($filters['employee_id']), fn (Builder $q) => $q->where('task_assignments.employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('task_assignments.status', (string) $filters['status']))
            // Date filters use the assignment's own deadline, which is what the
            // column means; the inherited parent deadline is the overdue rule,
            // not a filter range.
            ->when(! empty($filters['from_date']), fn (Builder $q) => $q->where('task_assignments.due_date', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn (Builder $q) => $q->where('task_assignments.due_date', '<=', $filters['to_date']))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->whereHas('task', fn (Builder $t) => $t
                    ->where('title', 'like', $term)
                    ->orWhere('task_code', 'like', $term));
            })
            // Applied here rather than in `tasks()` so the rows, the panel
            // summary and `meta.total` all describe the same filtered set.
            ->when(($filters['overdue'] ?? null) === '1', fn (Builder $q) => $this->overdueScope($q));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportQuery(array $filters): Builder
    {
        return Report::query()
            ->with(['employee:id,first_name,middle_name,last_name,employee_number,department_id'])
            ->with(['task:id,title,task_code,due_date'])
            ->with('employee.department:id,name')
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->whereHas(
                'employee',
                fn (Builder $e) => $e->where('department_id', (int) $filters['department_id'])
            ))
            ->when(! empty($filters['employee_id']), fn (Builder $q) => $q->where('employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', (string) $filters['status']))
            // `late=1` is the Late Reports alert table. It uses the same rule
            // as the Late Reports card, so the filtered list and the card
            // always show the same set of reports.
            ->when(($filters['late'] ?? null) === '1', fn (Builder $q) => $q->whereIn('reports.id', self::lateReportIdsQuery()))
            ->when(! empty($filters['from_date']), fn (Builder $q) => $q->where('created_at', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn (Builder $q) => $q->where('created_at', '<=', $filters['to_date'].' 23:59:59'))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', $term)
                    ->orWhereHas('employee', fn (Builder $e) => $e
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('employee_number', 'like', $term)));
            });
    }

    /* ------------------------------------------------------------------ */
    /* Row shaping */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $filters
     */
    private function employeeRow(Employee $employee): array
    {
        $assigned = (int) $employee->assigned_tasks;
        $completed = (int) $employee->completed_tasks;
        $submitted = (int) $employee->submitted_reports;

        return [
            'id' => $employee->id,
            'employee_id' => $employee->id,
            'name' => $employee->full_name,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'employee_number' => $employee->employee_number,
            // Identity and account state come from the linked `users` row when
            // one exists; an employee without an account reports null rather
            // than a fabricated address or status.
            'email' => $employee->user?->email,
            'has_account' => $employee->user !== null,
            'account_status' => $employee->user?->status,
            'role' => $employee->user?->roleName(),
            'department_id' => $employee->department_id,
            'department' => $employee->department?->name,
            'position_id' => $employee->position_id,
            'position' => $employee->position?->name,
            'phone' => $employee->phone,
            'employment_status' => $employee->employment_status,
            'employment_type' => $employee->employment_type,
            'date_hired' => $employee->date_hired?->toDateString(),
            'status' => $employee->employment_status,
            'assigned_tasks' => $assigned,
            'completed_tasks' => $completed,
            'pending_tasks' => (int) $employee->pending_tasks,
            'late_tasks' => (int) $employee->late_tasks,
            'submitted_reports' => $submitted,
            'approved_reports' => (int) $employee->approved_reports,
            'late_reports' => (int) $employee->late_reports,
            'completion_rate' => $this->rate($completed, $assigned),
            'on_time_rate' => $this->rate($submitted - (int) $employee->late_submitted_reports, $submitted),
            'approval_rate' => $this->rate((int) $employee->approved_reports, $submitted),
            'current_status' => $this->employeeStatus(
                (int) $employee->late_tasks,
                (int) $employee->late_reports,
                $employee->employment_status,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRow(TaskAssignment $assignment, ?string $reportStatus = null): array
    {
        $deadline = $assignment->due_date ?? $assignment->task?->due_date;
        $overdue = $this->assignmentIsOverdue($assignment);

        return [
            'id' => $assignment->id,
            'task_assignment_id' => $assignment->id,
            'task_id' => $assignment->task_id,
            'task_name' => $assignment->task?->title,
            'task_code' => $assignment->task?->task_code,
            'employee_id' => $assignment->employee_id,
            'employee_name' => $assignment->employee?->full_name,
            'employee_number' => $assignment->employee?->employee_number,
            'department' => $assignment->employee?->department?->name,
            'start_date' => $assignment->start_date?->toDateString(),
            'deadline' => $deadline?->toDateString(),
            'progress_percentage' => $assignment->progress_percentage === null
                ? null
                : (float) $assignment->progress_percentage,
            // Uses the same effective deadline as the `deadline` column above, so
            // a row can never show a passed inherited deadline next to a
            // non-overdue status.
            'status' => $overdue ? 'overdue' : $assignment->status,
            'raw_status' => $assignment->status,
            'is_overdue' => $overdue,
            'completed_at' => $assignment->completed_at?->toDateTimeString(),
            'report_status' => $reportStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(Report $report): array
    {
        $deadline = $report->deadline ?? $report->task?->due_date;
        $submittedAt = $report->submitted_at ?? $report->submission_date?->startOfDay();
        $status = $report->status instanceof ReportStatus
            ? $report->status
            : ReportStatus::tryFrom((string) $report->status);

        // `is_late` is the flag written at submission time. A report that is
        // still in review can also be demonstrably late from its own dates, so
        // the row derives the day count rather than trusting the flag alone.
        // Carbon's `A.diffInDays(B, false)` is `B - A`, so the deadline is the
        // left operand: the result is positive exactly when the reference
        // (submission) is later than the deadline, which is what "late" means.
        $daysLate = 0;
        if (! $report->is_late && $deadline !== null && ! ($status?->isTerminal() ?? false)) {
            $reference = $submittedAt ?? Carbon::now();
            $daysLate = (int) $deadline->startOfDay()->diffInDays($reference->startOfDay(), false);
        }

        return [
            'id' => $report->id,
            'report_id' => $report->id,
            'title' => $report->title,
            'task_name' => $report->task?->title,
            'task_code' => $report->task?->task_code,
            'employee_id' => $report->employee_id,
            'employee_name' => $report->employee?->full_name,
            'employee_number' => $report->employee?->employee_number,
            'department' => $report->employee?->department?->name,
            'submission_date' => $submittedAt?->toDateString(),
            'submitted_at' => $report->submitted_at?->toDateTimeString(),
            'deadline' => $deadline?->toDateString(),
            'status' => $status?->value ?? (string) $report->status,
            'status_label' => $status?->label() ?? (string) $report->status,
            'is_late' => (bool) $report->is_late || $daysLate > 0,
            'days_late' => max(0, $daysLate),
            'needs_revision' => $status === ReportStatus::RevisionRequired,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Percentage, or null when the denominator is zero.
     *
     * A 0 would be indistinguishable from "measured and found to be nothing",
     * which is exactly the kind of fake number this dashboard must not show.
     */
    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    /**
     * Derived label for the employee table's Current Status column. Derived
     * from real counts, never from a stored or hardcoded opinion.
     */
    private function employeeStatus(int $lateTasks, int $lateReports, string $employmentStatus): string
    {
        if ($employmentStatus !== 'active') {
            return 'inactive';
        }

        if ($lateTasks > 0 || $lateReports > 0) {
            return 'needs_attention';
        }

        return 'on_track';
    }

    /**
     * Overdue predicate on an Eloquent `TaskAssignment` query, using the
     * effective deadline `task_assignments.due_date ?? tasks.due_date`.
     *
     * `TaskAssignment::scopeOverdue()` is intentionally NOT reused here: it only
     * looks at the assignment's own `due_date`, so an assignment that inherits
     * its deadline from the parent task is displayed with that inherited
     * deadline (see `taskRow()`) yet would not be counted, filtered or sorted
     * as overdue. That is the same definition
     * AdminExecutiveDashboardService::overdueAssignments() applies, and the two
     * dashboards would otherwise report different institution-wide totals.
     *
     * The subquery keeps this correct without widening the outer query with a
     * join, which would make `task_assignments.` prefixes mandatory everywhere.
     */
    private function overdueScope(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function (Builder $outer) use ($now) {
                $outer
                    ->where(function (Builder $direct) use ($now) {
                        $direct
                            ->whereNotNull('task_assignments.due_date')
                            ->where('task_assignments.due_date', '<', $now);
                    })
                    ->orWhere(function (Builder $inherited) use ($now) {
                        $inherited
                            ->whereNull('task_assignments.due_date')
                            ->whereHas('task', fn (Builder $task) => $task
                                ->whereNotNull('due_date')
                                ->where('due_date', '<', $now));
                    });
            })
            ->whereNull('task_assignments.completed_at')
            ->whereNotIn('task_assignments.status', ['completed', 'cancelled']);
    }

    /**
     * Same effective-deadline rule for a raw `task_assignments` aggregate,
     * which has no model scopes to call. Callers must join `tasks` so the
     * inherited deadline is visible to `coalesce()`.
     */
    private function overdueRaw(Builder $query): Builder
    {
        return self::applyOverdueAssignmentRule($query);
    }

    /**
     * The single definition of "an assignment is overdue", shared with
     * `HrEmployeeService` so a per-employee profile and the directory row for
     * that same employee can never report different lateness totals.
     *
     * Requires the caller to LEFT JOIN `tasks` (or otherwise make
     * `tasks.due_date` visible) because the deadline is inherited from the
     * parent task when the assignment has none of its own.
     */
    public static function applyOverdueAssignmentRule(Builder|BaseBuilder $query): Builder|BaseBuilder
    {
        return $query
            ->whereNull('task_assignments.completed_at')
            ->whereNotIn('task_assignments.status', self::OVERDONE_STATUSES)
            ->whereRaw('coalesce(task_assignments.due_date, tasks.due_date) is not null')
            ->whereRaw('coalesce(task_assignments.due_date, tasks.due_date) < ?', [now()]);
    }

    /**
     * Row-level twin of `overdueScope()`, for a hydrated assignment whose parent
     * task has already been eager-loaded.
     */
    private function assignmentIsOverdue(TaskAssignment $assignment): bool
    {
        $deadline = $assignment->due_date ?? $assignment->task?->due_date;

        return $deadline !== null
            && $deadline->isPast()
            && $assignment->completed_at === null
            && ! in_array($assignment->status, ['completed', 'cancelled'], true);
    }

    /* ------------------------------------------------------------------ */
    /* The one definition of a late report */
    /* ------------------------------------------------------------------ */

    /**
     * SQL fragment + bindings for the canonical "late report" rule.
     *
     * A report is late when either
     *  - the submission workflow flagged it (`reports.is_late`), or
     *  - it was handed in after its effective deadline
     *    (`reports.deadline ?? tasks.due_date`), or
     *  - it is still outstanding and that deadline has already passed.
     *
     * This is exactly the rule the row view applies (`reportRow()`), so the
     * Late Reports card, the report summary, the "Late only" filter and the
     * per-employee count can never report different numbers. The effective
     * deadline is the one the rest of the app documents.
     *
     * The modes split the rule into disjoint halves, so a report is never
     * counted twice:
     *  - `all` — every late report (the card);
     *  - `submitted` — late reports that were actually handed in (the on-time
     *    rate, whose denominator is the submitted reports);
     *  - `outstanding` — late reports that were never handed in.
     *
     * @param  'all'|'submitted'|'outstanding'  $mode
     * @return array{0: string, 1: array<int, mixed>}
     */
    public static function lateReportCondition(string $mode = 'all'): array
    {
        $terminal = self::reportTerminalStatuses();
        $terminalPlaceholders = implode(',', array_fill(0, max(1, count($terminal)), '?'));

        $flagged = 'r.is_late = 1';

        // Handed in after the deadline: the submission timestamp (or the older
        // submission_date column) is later than the effective deadline.
        $submittedLate = '(coalesce(r.submitted_at, r.submission_date) is not null'
            .' and date(coalesce(r.submitted_at, r.submission_date)) > date(coalesce(r.deadline, t.due_date)))';

        // Never handed in and the deadline has passed.
        $outstandingLate = '(coalesce(r.submitted_at, r.submission_date) is null'
            .' and date(coalesce(r.deadline, t.due_date)) < ?)';

        $common = 'coalesce(r.deadline, t.due_date) is not null and r.status not in ('.$terminalPlaceholders.')';

        if ($mode === 'submitted') {
            // The flag only counts here when the report really was handed in:
            // this mode feeds the on-time rate, whose denominator is the
            // reports that were submitted, so a flagged draft must not be
            // subtracted from a number it was never part of.
            return [
                '(r.is_late = 1 and coalesce(r.submitted_at, r.submission_date) is not null) or ('.$common.' and '.$submittedLate.')',
                $terminal,
            ];
        }

        if ($mode === 'outstanding') {
            return [
                '('.$common.' and '.$outstandingLate.')',
                array_merge($terminal, [Carbon::now()->toDateString()]),
            ];
        }

        return [
            $flagged.' or ('.$common.' and ('.$submittedLate.' or '.$outstandingLate.'))',
            array_merge($terminal, [Carbon::now()->toDateString()]),
        ];
    }

    /**
     * Ids of the late reports, for the filters and counts that run inside an
     * Eloquent query.
     *
     * The rule needs the task's due date (the inherited deadline), so the
     * reports are joined to their task inside the subquery — the outer query
     * stays untouched, which keeps `reports.` prefixes unambiguous there.
     *
     * @param  'all'|'submitted'|'outstanding'  $mode
     */
    public static function lateReportIdsQuery(string $mode = 'all'): BaseBuilder
    {
        [$sql, $bindings] = self::lateReportCondition($mode);

        return DB::table('reports as r')
            ->leftJoin('tasks as t', 't.id', '=', 'r.task_id')
            ->whereNull('r.deleted_at')
            ->whereRaw($sql, $bindings)
            ->select('r.id');
    }

    /**
     * How many reports are late, under the canonical rule.
     *
     * @param  'all'|'submitted'|'outstanding'  $mode
     */
    public static function lateReportCount(string $mode = 'all'): int
    {
        [$sql, $bindings] = self::lateReportCondition($mode);

        return (int) DB::table('reports as r')
            ->leftJoin('tasks as t', 't.id', '=', 'r.task_id')
            ->whereNull('r.deleted_at')
            ->whereRaw($sql, $bindings)
            ->count();
    }

    /**
     * Build a `?,?,?` placeholder list so status lists can be bound as real
     * parameters instead of being interpolated into the SQL string.
     *
     * @param  array<int, string>  $values
     */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, max(1, count($values)), '?'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return min((int) ($filters['per_page'] ?? 15), 100);
    }

    /**
     * Whitelist a sort key so nothing user-supplied reaches `orderBy`.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $allowed
     */
    private function sortKey(array $filters, string $key, array $allowed): ?string
    {
        $value = $filters[$key] ?? null;

        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * Fill every month in range so a quiet month renders as 0 rather than a gap.
     *
     * @param  array<string, array<string, string>>  $labels
     * @param  Collection<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function bucket(array $labels, $rows): array
    {
        $byPeriod = $rows->keyBy('period');

        $series = [];
        foreach ($labels as $period => $label) {
            $row = $byPeriod->get($period);

            $series[] = [
                'period' => $period,
                'label' => $label['label'],
                'total' => (int) ($row->total ?? 0),
                'late' => (int) ($row->late ?? 0),
                'completed' => (int) ($row->completed ?? 0),
                'approved' => (int) ($row->approved ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rangeOptions(int $from, int $to): array
    {
        $options = [];
        for ($year = $to; $year >= $from; $year--) {
            $options[] = ['value' => $year, 'label' => (string) $year];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function monthOptions(): array
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = ['value' => $month, 'label' => Carbon::now()->month($month)->startOfMonth()->format('F')];
        }

        return $months;
    }
}
