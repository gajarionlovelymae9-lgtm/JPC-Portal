<?php

namespace App\Services\Admin;

use App\Enums\ReportStatus;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\MonthlyAchievement;
use App\Models\Report;
use App\Models\TaskAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Admin operational monitoring: task monitoring, report monitoring and the
 * institution calendar.
 *
 * All three read the same underlying operational tables the rest of the system
 * writes to, so nothing shown here can drift from the task/report workflow.
 */
class AdminMonitoringService
{
    public const TASK_NOT_STARTED = 'not_started';

    public const TASK_IN_PROGRESS = 'in_progress';

    public const TASK_COMPLETED = 'completed';

    public const TASK_PENDING = 'pending';

    public const TASK_OVERDUE = 'overdue';

    private const DONE_STATUSES = ['completed', 'approved'];

    /**
     * An assignment may carry its own due_date, otherwise it inherits the
     * parent task's deadline. This SQL fragment is the single source of truth
     * for that fallback so filtering and sorting can never disagree.
     */
    private const DUE_SQL = 'coalesce(task_assignments.due_date, (select tasks.due_date from tasks where tasks.id = task_assignments.task_id))';

    /**
     * Report states that exist on the ReportStatus enum. Used to validate the
     * status filter without depending on a non-existent values() helper.
     */
    private const REPORT_STATUS_VALUES = [
        'draft',
        'submitted',
        'processing',
        'under_review',
        'revision_required',
        'resubmitted',
        'approved',
        'rejected',
        'cancelled',
        'archived',
    ];

    /**
     * Paginated task monitoring rows.
     *
     * @param  array<string, mixed>  $filters
     */
    public function tasks(array $filters): LengthAwarePaginator
    {
        $today = now()->toDateString();
        $query = $this->taskQuery($filters);
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query
            ->orderByRaw('case when '.self::DUE_SQL.' is null then 1 else 0 end')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(fn (TaskAssignment $assignment) => $this->taskRow($assignment, $today));
    }

    /**
     * Headline counts for the monitoring summary cards.
     *
     * Status/overdue filters are intentionally excluded from the scope so the
     * cards keep describing the whole filtered population instead of collapsing
     * to zero as soon as an operator filters the register by status.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: int, completed: int, in_progress: int, not_started: int, overdue: int}
     */
    public function taskSummary(array $filters): array
    {
        $today = now()->toDateString();

        $scope = $filters;
        unset($scope['status'], $scope['overdue']);

        $query = $this->taskQuery($scope);

        $completed = (clone $query)->whereIn('status', self::DONE_STATUSES)->count();

        $overdueQuery = clone $query;
        $this->applyOverdue($overdueQuery, $today);
        $overdue = $overdueQuery->count();

        $inProgressQuery = (clone $query)
            ->whereNotIn('status', self::DONE_STATUSES)
            ->whereIn('status', ['in_progress', 'submitted']);
        $inProgressOverdueQuery = clone $inProgressQuery;
        $this->applyOverdue($inProgressOverdueQuery, $today);

        $notStartedQuery = (clone $query)
            ->whereNotIn('status', self::DONE_STATUSES)
            ->whereIn('status', ['assigned', 'accepted', 'not_started']);
        $notStartedOverdueQuery = clone $notStartedQuery;
        $this->applyOverdue($notStartedOverdueQuery, $today);

        return [
            'total' => (clone $query)->count(),
            'completed' => $completed,
            // Status buckets are mutually exclusive: an open assignment that is
            // past its deadline is counted as overdue, not as in progress.
            'in_progress' => $inProgressQuery->count() - $inProgressOverdueQuery->count(),
            'not_started' => $notStartedQuery->count() - $notStartedOverdueQuery->count(),
            'overdue' => $overdue,
        ];
    }

    /**
     * Shared assignment query for the task monitoring register and its summary.
     *
     * @param  array<string, mixed>  $filters
     */
    private function taskQuery(array $filters): Builder
    {
        $today = now()->toDateString();

        return TaskAssignment::query()
            ->with([
                'task:id,title,task_code,priority,start_date,due_date,department_id',
                'employee:id,first_name,last_name,employee_number,department_id',
                'employee.department:id,name',
                'assignedBy:id,name',
            ])
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->whereHas(
                'employee',
                fn (Builder $e) => $e->where('department_id', (int) $filters['department_id']),
            ))
            ->when(! empty($filters['employee_id']), fn (Builder $q) => $q->where('employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['priority']), fn (Builder $q) => $q->whereHas(
                'task',
                fn (Builder $t) => $t->where('priority', $filters['priority']),
            ))
            ->when(! empty($filters['from_date']), fn (Builder $q) => $q->whereRaw(
                self::DUE_SQL.' >= ?',
                [$filters['from_date']],
            ))
            ->when(! empty($filters['to_date']), fn (Builder $q) => $q->whereRaw(
                self::DUE_SQL.' <= ?',
                [$filters['to_date']],
            ))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(function (Builder $inner) use ($term) {
                    $inner->whereHas('task', fn (Builder $t) => $t->where('title', 'like', $term)->orWhere('task_code', 'like', $term))
                        ->orWhereHas('employee', fn (Builder $e) => $e->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
                });
            })
            ->when(($filters['status'] ?? null) === self::TASK_COMPLETED, fn (Builder $q) => $q->whereIn('status', self::DONE_STATUSES))
            ->when(($filters['status'] ?? null) === self::TASK_IN_PROGRESS, fn (Builder $q) => $q->whereIn('status', ['in_progress', 'submitted']))
            ->when(($filters['status'] ?? null) === self::TASK_NOT_STARTED, fn (Builder $q) => $q->whereIn('status', ['assigned', 'accepted', 'not_started']))
            ->when(($filters['status'] ?? null) === self::TASK_PENDING, fn (Builder $q) => $q->whereIn('status', ['assigned', 'accepted', 'in_progress', 'submitted']))
            ->when(($filters['status'] ?? null) === self::TASK_OVERDUE, fn (Builder $q) => $this->applyOverdue($q, $today))
            ->when(! empty($filters['overdue']), function (Builder $q) use ($today) {
                $this->applyOverdue($q, $today);

                return $q;
            });
    }

    /**
     * Paginated report monitoring rows.
     *
     * @param  array<string, mixed>  $filters
     */
    public function reports(array $filters): LengthAwarePaginator
    {
        $query = Report::query()
            ->with([
                'employee:id,first_name,last_name,employee_number,department_id',
                'employee.department:id,name',
                'task:id,title',
                // Eager-load callbacks receive the relation instance, so this
                // closure must not be type-hinted as an Eloquent Builder.
                'aiValidationResults' => fn ($results) => $results->latest('id'),
            ])
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->whereHas(
                'employee',
                fn (Builder $e) => $e->where('department_id', (int) $filters['department_id']),
            ))
            ->when(! empty($filters['employee_id']), fn (Builder $q) => $q->where('employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['task_id']), fn (Builder $q) => $q->where('task_id', (int) $filters['task_id']))
            ->withCount('approvals')
            ->when(! empty($filters['from_date']), fn (Builder $q) => $q->whereDate('deadline', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn (Builder $q) => $q->whereDate('deadline', '<=', $filters['to_date']))
            ->when(($filters['status'] ?? null) === 'late', fn (Builder $q) => $q->where('is_late', true))
            ->when(($filters['status'] ?? null) === 'not_submitted', fn (Builder $q) => $q->where('status', ReportStatus::Draft->value))
            ->when(! empty($filters['status']) && in_array($filters['status'], self::REPORT_STATUS_VALUES, true), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhereHas('employee', fn (Builder $e) => $e->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
                });
            })
            ->when(! empty($filters['ai_result']), function (Builder $q) use ($filters) {
                $filters['ai_result'] === 'scored'
                    ? $q->whereHas('aiValidationResults', fn (Builder $v) => $v->whereNotNull('overall_score'))
                    : $q->whereDoesntHave('aiValidationResults', fn (Builder $v) => $v->whereNotNull('overall_score'));
            });

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->orderByDesc('id')->paginate($perPage)->through(fn (Report $report) => $this->reportRow($report));
    }

    /**
     * Institution calendar events.
     *
     * Five real sources are merged into one timeline, each keeping its own
     * type so the UI can label and colour them distinctly:
     *   task_deadline | report_deadline | target_period | achievement | announcement
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function calendar(array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->endOfMonth()->toDateString();
        $limit = min((int) ($filters['limit'] ?? 200), 500);

        $departmentFilter = ! empty($filters['department_id']) ? (int) $filters['department_id'] : null;
        $employeeFilter = ! empty($filters['employee_id']) ? (int) $filters['employee_id'] : null;

        $events = [];

        $assignments = TaskAssignment::query()
            ->with(['task:id,title,department_id,due_date', 'employee:id,first_name,last_name,department_id', 'employee.department:id,name'])
            // A task deadline is the assignment's own due date, falling back to
            // the parent task's due date when the assignment has none. The
            // window must therefore be matched against either column, not just
            // the assignment column, or the fallback is unreachable.
            ->where(function (Builder $q) use ($from, $to) {
                $q->where(function (Builder $inner) use ($from, $to) {
                    $inner->whereNotNull('task_assignments.due_date')
                        ->whereDate('task_assignments.due_date', '>=', $from)
                        ->whereDate('task_assignments.due_date', '<=', $to);
                })->orWhere(function (Builder $inner) use ($from, $to) {
                    $inner->whereNull('task_assignments.due_date')
                        ->whereHas('task', fn (Builder $t) => $t
                            ->whereNotNull('tasks.due_date')
                            ->whereDate('tasks.due_date', '>=', $from)
                            ->whereDate('tasks.due_date', '<=', $to));
                });
            })
            ->when($departmentFilter !== null, fn (Builder $q) => $q->whereHas('employee', fn (Builder $e) => $e->where('department_id', $departmentFilter)))
            ->when($employeeFilter !== null, fn (Builder $q) => $q->where('employee_id', $employeeFilter))
            ->limit($limit)
            ->get();

        foreach ($assignments as $assignment) {
            $deadline = $assignment->due_date ?? $assignment->task?->due_date;
            if (! $deadline) {
                continue;
            }

            $events[] = [
                'id' => 'task-'.$assignment->id,
                'type' => 'task_deadline',
                'type_label' => 'Task Deadline',
                'title' => $assignment->task?->title ?? 'Untitled Task',
                'employee' => $assignment->employee?->full_name,
                'department' => $assignment->employee?->department?->name,
                'date' => Carbon::parse($deadline)->toDateString(),
                'time' => null,
                'description' => 'Assigned deliverable due date. Status: '.$assignment->status,
                'status' => $this->taskStatus($assignment, $deadline),
                'link' => '/admin/tasks?assignment='.$assignment->id,
            ];
        }

        $reports = Report::query()
            ->with(['employee:id,first_name,last_name,department_id', 'employee.department:id,name', 'task:id,title'])
            ->whereNotNull('deadline')
            ->whereDate('deadline', '>=', $from)
            ->whereDate('deadline', '<=', $to)
            ->when($departmentFilter !== null, fn (Builder $q) => $q->whereHas('employee', fn (Builder $e) => $e->where('department_id', $departmentFilter)))
            ->when($employeeFilter !== null, fn (Builder $q) => $q->where('employee_id', $employeeFilter))
            ->limit($limit)
            ->get();

        foreach ($reports as $report) {
            $events[] = [
                'id' => 'report-'.$report->id,
                'type' => 'report_deadline',
                'type_label' => 'Report Deadline',
                'title' => $report->title,
                'employee' => $report->employee?->full_name,
                'department' => $report->employee?->department?->name,
                'date' => $report->deadline?->toDateString(),
                'time' => null,
                'description' => 'Report submission deadline for '.($report->task?->title ?? 'assigned task'),
                'status' => $report->is_late ? 'late' : $report->status?->value,
                'link' => '/admin/reports/'.$report->id,
            ];
        }

        $targets = EmployeeTarget::query()
            ->with(['employee:id,first_name,last_name,department_id', 'employee.department:id,name'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $from)
            ->whereDate('end_date', '<=', $to)
            ->when($departmentFilter !== null, fn (Builder $q) => $q->whereHas('employee', fn (Builder $e) => $e->where('department_id', $departmentFilter)))
            ->when($employeeFilter !== null, fn (Builder $q) => $q->where('employee_id', $employeeFilter))
            ->limit($limit)
            ->get();

        foreach ($targets as $target) {
            $events[] = [
                'id' => 'target-'.$target->id,
                'type' => 'target_period',
                'type_label' => 'KPI Period End',
                'title' => $target->title,
                'employee' => $target->employee?->full_name,
                'department' => $target->employee?->department?->name,
                'date' => $target->end_date?->toDateString(),
                'time' => null,
                'description' => 'KPI period ends. Target: '
                    .($target->target_value ?? '—').' '.($target->target_unit ?? ''),
                'status' => $target->end_date?->isPast() ? 'closed' : 'active',
                'link' => '/admin/kpi-targets?target='.$target->id,
            ];
        }

        // Achievements are month-scoped records (year + month columns), not
        // point-in-time events, so they are matched on the month they describe
        // and placed on the first day of that month.
        $fromMonth = Carbon::parse($from)->startOfMonth();
        $toMonth = Carbon::parse($to)->endOfMonth();

        $achievements = MonthlyAchievement::query()
            ->with(['employee:id,first_name,last_name,department_id', 'employee.department:id,name'])
            ->where(function (Builder $q) use ($fromMonth) {
                $q->where(fn (Builder $inner) => $inner->where('year', $fromMonth->year)->where('month', '>=', $fromMonth->month))
                    ->orWhere('year', '>', $fromMonth->year);
            })
            ->where(function (Builder $q) use ($toMonth) {
                $q->where(fn (Builder $inner) => $inner->where('year', $toMonth->year)->where('month', '<=', $toMonth->month))
                    ->orWhere('year', '<', $toMonth->year);
            })
            ->when($departmentFilter !== null, fn (Builder $q) => $q->whereHas('employee', fn (Builder $e) => $e->where('department_id', $departmentFilter)))
            ->when($employeeFilter !== null, fn (Builder $q) => $q->where('employee_id', $employeeFilter))
            ->limit($limit)
            ->get();

        foreach ($achievements as $achievement) {
            $events[] = [
                'id' => 'achievement-'.$achievement->id,
                'type' => 'achievement',
                'type_label' => 'Achievement Recorded',
                'title' => $achievement->employee?->full_name ?? 'Employee',
                'employee' => $achievement->employee?->full_name,
                'department' => $achievement->employee?->department?->name,
                'date' => Carbon::create($achievement->year, $achievement->month, 1)->toDateString(),
                'time' => null,
                'description' => 'Achievement for '.$achievement->month.'/'.$achievement->year
                    .' with an overall score of '.$achievement->overall_score.'.',
                'status' => $achievement->is_employee_of_month ? 'employee_of_the_month' : 'recorded',
                'link' => '/admin/achievements',
            ];
        }

        // Published announcements are institutional events: only rows with an
        // event window are placed on the calendar, matched on the overlap of
        // [start_date, end_date (or start_date alone)] with [from, to].
        // Scheduled-but-unpublished rows are excluded so nothing appears
        // before its publication time.
        $announcementQuery = Announcement::query()
            ->where('status', 'published')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $to)
            ->where(function (Builder $q) use ($from) {
                $q->whereDate('end_date', '>=', $from)
                    ->orWhere(function (Builder $inner) use ($from) {
                        $inner->whereNull('end_date')->whereDate('start_date', '>=', $from);
                    });
            });

        if ($employeeFilter !== null) {
            // Mirror the audience rules of Announcement::scopeForUser for one
            // employee, without its visibility gates (the calendar shows
            // historical months too).
            $employee = Employee::query()->with(['user.roles', 'user.employee'])->find($employeeFilter);
            $user = $employee?->user;

            if ($user) {
                $roleName = $user->role ?? $user->roles->first()?->name;
                $deptId = $employee->department_id ?? $user->employee?->department_id;
                $userId = (string) $user->id;

                $announcementQuery->where(function (Builder $q) use ($roleName, $deptId, $userId) {
                    $q->where('audience_type', 'all')
                        ->orWhere(fn (Builder $sq) => $sq->where('audience_type', 'role')->where('audience_id', $roleName))
                        ->orWhere(fn (Builder $sq) => $sq->where('audience_type', 'department')->where('audience_id', (string) $deptId))
                        ->orWhere(fn (Builder $sq) => $sq->where('audience_type', 'user')->where('audience_id', $userId));
                });
            } else {
                $announcementQuery->whereRaw('1 = 0');
            }
        } elseif ($departmentFilter !== null) {
            $announcementQuery->where(function (Builder $q) use ($departmentFilter) {
                $q->where('audience_type', '!=', 'department')
                    ->orWhere('audience_id', (string) $departmentFilter);
            });
        }

        $announcements = $announcementQuery->limit($limit)->get();

        foreach ($announcements as $announcement) {
            $dateLabel = $announcement->start_date->toFormattedDateString();
            if ($announcement->end_date && ! $announcement->end_date->isSameDay($announcement->start_date)) {
                $dateLabel .= ' – '.$announcement->end_date->toFormattedDateString();
            }

            $events[] = [
                'id' => 'announcement-'.$announcement->id,
                'type' => 'announcement',
                'type_label' => 'Announcement',
                'title' => $announcement->title,
                'employee' => null,
                'department' => null,
                'date' => $announcement->start_date->toDateString(),
                'time' => $announcement->start_date->format('H:i'),
                'description' => ucfirst($announcement->category).' announcement ('.$announcement->priority
                    .' priority), '.$dateLabel.'.',
                'status' => 'published',
                'link' => '/admin/announcements',
            ];
        }

        usort($events, fn (array $a, array $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        return $events;
    }

    /**
     * Which calendar categories have a real data source, and which do not.
     *
     * The schema has no meetings, institutional-event or holiday tables, so
     * those categories are reported as unsupported instead of being rendered
     * as an empty list that looks like "nothing scheduled".
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendarSources(): array
    {
        return [
            [
                'type' => 'task_deadline',
                'label' => 'Task Deadlines',
                'supported' => true,
                'source' => 'task_assignments.due_date with fallback to tasks.due_date',
            ],
            [
                'type' => 'report_deadline',
                'label' => 'Report Deadlines',
                'supported' => true,
                'source' => 'reports.deadline',
            ],
            [
                'type' => 'target_period',
                'label' => 'KPI Period Ends',
                'supported' => true,
                'source' => 'employee_targets.end_date',
            ],
            [
                'type' => 'achievement',
                'label' => 'Achievements',
                'supported' => true,
                'source' => 'monthly_achievements (year + month)',
            ],
            [
                'type' => 'announcement',
                'label' => 'Announcements',
                'supported' => true,
                'source' => 'announcements.start_date/end_date (published)',
            ],
            [
                'type' => 'meeting',
                'label' => 'Meetings',
                'supported' => false,
                'source' => null,
                'note' => 'No meetings table exists in this schema, so no meeting events can be listed.',
            ],
            [
                'type' => 'institutional_event',
                'label' => 'Institutional Events',
                'supported' => false,
                'source' => null,
                'note' => 'No institutional events or academic calendar table exists in this schema.',
            ],
            [
                'type' => 'holiday',
                'label' => 'Holidays',
                'supported' => false,
                'source' => null,
                'note' => 'No holiday table exists in this schema.',
            ],
        ];
    }

    /**
     * Filter options shared by the monitoring toolbars.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'employees' => Employee::query()
                ->with('department:id,name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number', 'department_id', 'employment_status'])
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'employee_number' => $e->employee_number,
                    'department' => $e->department?->name,
                    'department_id' => $e->department_id,
                    'employment_status' => $e->employment_status,
                ])->all(),
            'task_statuses' => [
                ['value' => self::TASK_NOT_STARTED, 'label' => 'Not Started'],
                ['value' => self::TASK_IN_PROGRESS, 'label' => 'In Progress'],
                ['value' => self::TASK_COMPLETED, 'label' => 'Completed'],
                ['value' => self::TASK_PENDING, 'label' => 'Pending'],
                ['value' => self::TASK_OVERDUE, 'label' => 'Overdue'],
            ],
            'report_statuses' => [
                ['value' => 'not_submitted', 'label' => 'Not Submitted'],
                ['value' => ReportStatus::Submitted->value, 'label' => 'Submitted'],
                ['value' => ReportStatus::UnderReview->value, 'label' => 'Under Review'],
                ['value' => ReportStatus::Approved->value, 'label' => 'Approved'],
                ['value' => ReportStatus::RevisionRequired->value, 'label' => 'Needs Revision'],
                ['value' => 'late', 'label' => 'Late'],
            ],
        ];
    }

    private function applyOverdue(Builder $query, string $today): void
    {
        $query->whereNotIn('status', self::DONE_STATUSES)
            ->where(function (Builder $group) use ($today) {
                $group
                    ->where(fn (Builder $direct) => $direct->whereNotNull('task_assignments.due_date')
                        ->where('task_assignments.due_date', '<', $today))
                    ->orWhere(fn (Builder $fallback) => $fallback->whereNull('task_assignments.due_date')
                        ->whereHas('task', fn (Builder $t) => $t->whereNotNull('due_date')->where('due_date', '<', $today)));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRow(TaskAssignment $assignment, string $today): array
    {
        $deadline = $assignment->due_date ?? $assignment->task?->due_date;

        return [
            'assignment_id' => $assignment->id,
            'task_id' => $assignment->task_id,
            'task_name' => $assignment->task?->title ?? 'Untitled Task',
            'task_code' => $assignment->task?->task_code,
            'assigned_by' => $assignment->assignedBy?->name,
            'assigned_to' => $assignment->employee?->full_name,
            'employee_id' => $assignment->employee_id,
            'employee_number' => $assignment->employee?->employee_number,
            'department' => $assignment->employee?->department?->name,
            'priority' => $assignment->task?->priority,
            'start_date' => $assignment->start_date?->toDateString(),
            'due_date' => $deadline?->toDateString(),
            'progress' => (float) $assignment->progress_percentage,
            'raw_status' => $assignment->status,
            'status' => $this->taskStatus($assignment, $deadline),
            'days_overdue' => $deadline && $this->taskStatus($assignment, $deadline) === self::TASK_OVERDUE
                ? max(1, (int) Carbon::parse($deadline)->diffInDays(now()))
                : 0,
        ];
    }

    private function taskStatus(TaskAssignment $assignment, ?Carbon $deadline): string
    {
        if (in_array($assignment->status, self::DONE_STATUSES, true)) {
            return self::TASK_COMPLETED;
        }

        if ($deadline !== null && Carbon::parse($deadline)->isPast()) {
            return self::TASK_OVERDUE;
        }

        return match ($assignment->status) {
            'in_progress', 'submitted' => self::TASK_IN_PROGRESS,
            default => self::TASK_NOT_STARTED,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(Report $report): array
    {
        $validation = $report->aiValidationResults?->first();

        return [
            'id' => $report->id,
            'title' => $report->title,
            'employee' => $report->employee?->full_name,
            'employee_id' => $report->employee_id,
            'employee_number' => $report->employee?->employee_number,
            'department' => $report->employee?->department?->name,
            'task' => $report->task?->title,
            'task_id' => $report->task_id,
            'submission_date' => $report->submission_date?->toDateString(),
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'due_date' => $report->deadline?->toDateString(),
            'status' => $report->status?->value,
            'status_label' => $report->status?->label(),
            'is_late' => (bool) $report->is_late,
            'ai_status' => $report->ai_status,
            // overall_score is a 0..1 fraction; expose a 0..100 percentage and
            // null when the AI pipeline has not scored this report.
            'ai_validation_score' => $validation?->overall_score === null
                ? null
                : round(max(0.0, min(1.0, (float) $validation->overall_score)) * 100, 2),
            'ai_result' => $validation?->result,
            'approvals_count' => (int) $report->approvals_count,
            'approval_status' => (int) $report->approvals_count > 0 ? 'approved' : $report->status?->value,
        ];
    }
}
