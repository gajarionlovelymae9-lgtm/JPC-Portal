<?php

namespace App\Services\Reporting;

use App\Enums\PerformanceRating;
use App\Enums\ReportStatus;
use App\Models\Badge;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeTarget;
use App\Models\MonthlyAchievement;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\DataQuality\DataQualityStatisticsService;
use App\Services\Reporting\Concerns\ResolvesReportingScope;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * Phase 9 — Report Generation Service (spec item 25).
 *
 * Transforms existing data into management information. Every number is read
 * from authoritative sources: EmployeePerformanceRecord snapshots (Phase 6),
 * TaskAssignment/Report/EmployeeTarget rows (Phase 1/5) and the Data Quality
 * statistics service (Phase 8). KPI/productivity/achievement calculations are
 * NEVER re-implemented here — when a performance snapshot exists it is the
 * source of truth, otherwise a computed fallback is clearly flagged with
 * `source = computed`.
 *
 * All generators funnel through periodBounds() for server-side date handling
 * (spec item 4) and ResolvesReportingScope for backend authorization
 * (spec item 6): admin/hr = institution, department_head = own department,
 * employee = own records only.
 */
class ReportingService
{
    use ResolvesReportingScope;

    /** Statuses that count as a submitted (non-draft) report. */
    private const SUBMITTED_STATUSES = [
        ReportStatus::Submitted,
        ReportStatus::Processing,
        ReportStatus::UnderReview,
        ReportStatus::RevisionRequired,
        ReportStatus::Resubmitted,
        ReportStatus::Approved,
        ReportStatus::Rejected,
    ];

    /** Statuses that still require reviewer action ("pending"). */
    public const PENDING_STATUSES = [
        ReportStatus::Submitted,
        ReportStatus::Processing,
        ReportStatus::UnderReview,
        ReportStatus::Resubmitted,
        ReportStatus::RevisionRequired,
    ];

    /** Target statuses that count as achieved (mirrors KpiCalculationService). */
    private const TARGET_ACHIEVED_STATUSES = ['achieved', 'completed', 'approved'];

    public function __construct(
        private readonly ReportCacheService $cache,
        private readonly DataQualityStatisticsService $dataQualityStatistics,
    ) {}

    /* ---------------------------------------------------------------------
     | Employee reports
     * ------------------------------------------------------------------- */

    /**
     * Employee Progress Report (spec item 7).
     *
     * @throws AuthorizationException
     */
    public function generateEmployeeReport(int $employeeId, array $filters, User $user): array
    {
        $this->assertEmployeeScope($user, $employeeId);

        $employee = Employee::with(['department:id,name', 'position:id,name'])
            ->find($employeeId);

        if ($employee === null) {
            throw new AuthorizationException('Forbidden');
        }

        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('employee_report:%d:%s', $user->id, hash('sha256', $employeeId.'|'.json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($employee, $period) {
            [$start, $end] = $period['start'] ? [$period['start'], $period['end']] : [null, null];

            // --- Tasks assigned / completed (period scoped on assignment) ---
            $assignedQuery = TaskAssignment::query()->where('employee_id', $employee->id);
            if ($start) {
                $assignedQuery->whereBetween('assigned_at', [$start, $end]);
            }
            $tasksAssigned = (clone $assignedQuery)->count();

            $completedQuery = TaskAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'completed');
            if ($start) {
                $completedQuery->where(function ($q) use ($start, $end) {
                    $q->whereBetween('completed_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('completed_at')->whereBetween('created_at', [$start, $end]);
                        });
                });
            }
            $tasksCompleted = $completedQuery->count();

            // --- Reports expected / submitted / approved / on-time ---
            $reportsExpected = $tasksAssigned;

            $reportQuery = Report::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', self::SUBMITTED_STATUSES);
            if ($start) {
                $reportQuery->where(function ($q) use ($start, $end) {
                    $q->whereBetween('submitted_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('submitted_at')->whereBetween('created_at', [$start, $end]);
                        });
                });
            }
            $reports = (clone $reportQuery)->get(['id', 'status', 'is_late', 'submitted_at']);
            $reportsSubmitted = $reports->count();
            $lateSubmitted = $reports->where('is_late', true)->count();
            $onTimeSubmitted = $reportsSubmitted - $lateSubmitted;
            $reportsApproved = $reports->filter(
                fn ($r) => $r->status instanceof ReportStatus && $r->status === ReportStatus::Approved
            )->count();

            // --- Targets ---
            $targetQuery = EmployeeTarget::query()->where('employee_id', $employee->id);
            if ($start) {
                $targetQuery->where(function ($q) use ($start, $end) {
                    $q->whereBetween('created_at', [$start, $end])
                        ->orWhereBetween('start_date', [$start, $end]);
                });
            }
            $targets = $targetQuery->get(['id', 'status', 'target_value']);
            $targetTotal = $targets->count();
            $targetAchieved = $targets
                ->filter(fn ($t) => in_array($t->status, self::TARGET_ACHIEVED_STATUSES, true))
                ->count();

            // --- Authoritative performance snapshot (Phase 6) ---
            $perfRecord = $start
                ? EmployeePerformanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('period_start', [$start, $end])
                    ->first()
                : null;

            // --- Rates: snapshot when present, otherwise computed with N/A ---
            $computed = [
                'task_completion_rate' => $this->rate($tasksCompleted, $tasksAssigned),
                'report_completion_rate' => $this->rate($reportsSubmitted, $reportsExpected),
                'on_time_submission_rate' => $this->rate($onTimeSubmitted, $reportsSubmitted),
                'approval_rate' => $this->rate($reportsApproved, $reportsSubmitted),
                'target_achievement_rate' => $this->rate($targetAchieved, $targetTotal),
            ];

            $snapshotAvailable = $perfRecord !== null;

            $rates = $snapshotAvailable ? [
                'task_completion_rate' => $this->round($perfRecord->completion_rate),
                'report_completion_rate' => $this->round($perfRecord->report_completion_rate),
                'on_time_submission_rate' => $this->round($perfRecord->on_time_rate),
                'approval_rate' => $this->round($perfRecord->approval_rate),
                'target_achievement_rate' => $this->round($perfRecord->target_achievement_rate),
            ] : $computed;

            $productivity = $snapshotAvailable
                ? $this->round($perfRecord->productivity_score ?? $perfRecord->overall_score)
                : $this->weightedProductivity($computed);

            $rating = $productivity === null
                ? null
                : ($snapshotAvailable && $perfRecord->rating
                    ? $perfRecord->rating
                    : PerformanceRating::fromScore((float) $productivity)->value);

            return [
                'report_type' => 'employee_progress',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'source' => $snapshotAvailable ? 'performance_snapshot' : 'computed',
                'employee' => [
                    'id' => $employee->id,
                    'name' => trim($employee->first_name.' '.$employee->last_name),
                    'employee_number' => $employee->employee_number,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->name,
                ],
                'metrics' => [
                    'tasks_assigned' => $tasksAssigned,
                    'tasks_completed' => $tasksCompleted,
                    'task_completion_rate' => $rates['task_completion_rate'],
                    'reports_expected' => $reportsExpected,
                    'reports_submitted' => $reportsSubmitted,
                    'report_completion_rate' => $rates['report_completion_rate'],
                    'on_time_submission_rate' => $rates['on_time_submission_rate'],
                    'approval_rate' => $rates['approval_rate'],
                    'target_achievement_rate' => $rates['target_achievement_rate'],
                    'targets_total' => $targetTotal,
                    'targets_achieved' => $targetAchieved,
                    'productivity_score' => $productivity,
                    'rating' => $rating,
                ],
                'calculation_version' => $perfRecord?->calculation_version ?? config('performance.calculation_version', 'v1.0'),
                'has_data' => $tasksAssigned > 0 || $reportsSubmitted > 0 || $targetTotal > 0 || $snapshotAvailable,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /* ---------------------------------------------------------------------
     | Department reports
     * ------------------------------------------------------------------- */

    /**
     * Department Performance Report (spec item 8).
     *
     * @throws AuthorizationException
     */
    public function generateDepartmentReport(int $departmentId, array $filters, User $user): array
    {
        $effectiveDepartmentId = $this->effectiveDepartmentId($user, $departmentId);

        if ($effectiveDepartmentId === null) {
            throw new AuthorizationException('Forbidden');
        }

        $department = Department::find($effectiveDepartmentId);

        if ($department === null) {
            throw new AuthorizationException('Forbidden');
        }

        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('department_report:%d:%s', $user->id, hash('sha256', $effectiveDepartmentId.'|'.json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($department, $period) {
            [$start, $end] = $period['start'] ? [$period['start'], $period['end']] : [null, null];

            $employeeCount = Employee::query()
                ->where('department_id', $department->id)
                ->active()
                ->count();

            $recordQuery = EmployeePerformanceRecord::query()
                ->whereHas('employee', fn ($q) => $q->where('department_id', $department->id));
            if ($start) {
                $recordQuery->whereBetween('period_start', [$start, $end]);
            }
            $records = $recordQuery->get();

            // Aggregate task/report totals for the department in the period.
            $assignmentQuery = TaskAssignment::query()
                ->whereHas('employee', fn ($q) => $q->where('department_id', $department->id));
            if ($start) {
                $assignmentQuery->whereBetween('assigned_at', [$start, $end]);
            }
            $tasksAssigned = (clone $assignmentQuery)->count();
            $tasksCompleted = (clone $assignmentQuery)->where('status', 'completed')->count();

            $reportQuery = Report::query()
                ->whereHas('employee', fn ($q) => $q->where('department_id', $department->id))
                ->whereIn('status', self::SUBMITTED_STATUSES);
            if ($start) {
                $reportQuery->where(function ($q) use ($start, $end) {
                    $q->whereBetween('submitted_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('submitted_at')->whereBetween('created_at', [$start, $end]);
                        });
                });
            }
            $reports = $reportQuery->get(['id', 'status', 'is_late']);
            $reportsSubmitted = $reports->count();
            $reportsApproved = $reports->filter(
                fn ($r) => $r->status === ReportStatus::Approved->value || $r->status === ReportStatus::Approved
            )->count();
            $lateSubmitted = $reports->where('is_late', true)->count();

            $snapshotAvailable = $records->isNotEmpty();

            $metrics = [
                'task_completion_rate' => $snapshotAvailable
                    ? $this->round($records->avg('completion_rate'))
                    : $this->rate($tasksCompleted, $tasksAssigned),
                'report_completion_rate' => $snapshotAvailable
                    ? $this->round($records->avg('report_completion_rate'))
                    : $this->rate($reportsSubmitted, max($tasksAssigned, $reportsSubmitted)),
                'on_time_rate' => $snapshotAvailable
                    ? $this->round($records->avg('on_time_rate'))
                    : $this->rate($reportsSubmitted - $lateSubmitted, $reportsSubmitted),
                'approval_rate' => $snapshotAvailable
                    ? $this->round($records->avg('approval_rate'))
                    : $this->rate($reportsApproved, $reportsSubmitted),
                'target_achievement_rate' => $snapshotAvailable
                    ? $this->round($records->avg('target_achievement_rate'))
                    : null,
                'average_productivity' => $snapshotAvailable
                    ? $this->round($records->avg(fn ($r) => $r->productivity_score ?? $r->overall_score))
                    : null,
                'employees' => $employeeCount,
                'tasks_assigned' => $tasksAssigned,
                'tasks_completed' => $tasksCompleted,
                'reports_submitted' => $reportsSubmitted,
                'late_reports' => $lateSubmitted,
            ];

            $avgProductivity = $metrics['average_productivity'];

            $metrics['rating'] = $avgProductivity === null
                ? null
                : PerformanceRating::fromScore((float) $avgProductivity)->value;

            return [
                'report_type' => 'department_performance',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'source' => $snapshotAvailable ? 'performance_snapshot' : 'computed',
                'department' => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'employee_count' => $employeeCount,
                ],
                'metrics' => $metrics,
                'calculation_version' => config('performance.calculation_version', 'v1.0'),
                'has_data' => $employeeCount > 0 || $tasksAssigned > 0 || $reportsSubmitted > 0 || $snapshotAvailable,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /* ---------------------------------------------------------------------
     | Institution reports
     * ------------------------------------------------------------------- */

    /**
     * Institution Performance Report (spec item 9).
     *
     * @throws AuthorizationException
     */
    public function generateInstitutionReport(array $filters, User $user): array
    {
        if (! in_array($user->roleName(), ['admin', 'hr'], true)) {
            throw new AuthorizationException('Forbidden');
        }

        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('institution_report:%d:%s', $user->id, hash('sha256', json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($user, $period) {
            [$start, $end] = $period['start'] ? [$period['start'], $period['end']] : [null, null];

            $totalEmployees = Employee::query()->active()->count();
            $totalDepartments = Department::query()->count();

            $assignmentQuery = TaskAssignment::query();
            if ($start) {
                $assignmentQuery->whereBetween('assigned_at', [$start, $end]);
            }
            $tasksAssigned = (clone $assignmentQuery)->count();
            $tasksCompleted = (clone $assignmentQuery)->where('status', 'completed')->count();

            $reportQuery = Report::query();
            if ($start) {
                $reportQuery->where(function ($q) use ($start, $end) {
                    $q->whereBetween('submitted_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('submitted_at')->whereBetween('created_at', [$start, $end]);
                        });
                });
            }
            $reports = $reportQuery->get(['id', 'status', 'is_late'])->map(function ($report) {
                $report->status_key = $report->status instanceof ReportStatus
                    ? $report->status->value
                    : (string) $report->status;

                return $report;
            });
            $reportsSubmitted = $reports
                ->filter(fn ($r) => in_array($r->status_key, array_map(fn ($s) => $s->value, self::SUBMITTED_STATUSES), true))
                ->count();
            $lateReports = $reports->where('is_late', true)->count();
            $pendingReports = $reports
                ->filter(fn ($r) => in_array($r->status_key, array_map(fn ($s) => $s->value, self::PENDING_STATUSES), true))
                ->count();
            $reportsApproved = $reports->filter(
                fn ($r) => $r->status_key === ReportStatus::Approved->value
            )->count();
            $reportsReviewed = $reportsApproved + $reports->filter(
                fn ($r) => $r->status_key === ReportStatus::Rejected->value
            )->count();

            $recordQuery = EmployeePerformanceRecord::query();
            if ($start) {
                $recordQuery->whereBetween('period_start', [$start, $end]);
            }
            $records = $recordQuery->get();

            $dqStats = $this->dataQualityStatistics->getDashboardStatistics($user);

            $snapshotAvailable = $records->isNotEmpty();

            return [
                'report_type' => 'institution_performance',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'source' => $snapshotAvailable ? 'performance_snapshot' : 'computed',
                'summary' => [
                    'total_employees' => $totalEmployees,
                    'total_departments' => $totalDepartments,
                    'tasks_assigned' => $tasksAssigned,
                    'tasks_completed' => $tasksCompleted,
                    'task_completion_rate' => $this->rate($tasksCompleted, $tasksAssigned),
                    'reports_expected' => $tasksAssigned,
                    'reports_submitted' => $reportsSubmitted,
                    'late_reports' => $lateReports,
                    'pending_reports' => $pendingReports,
                    'approval_rate' => $this->rate($reportsApproved, $reportsReviewed),
                    'target_achievement_rate' => $snapshotAvailable
                        ? $this->round($records->avg('target_achievement_rate'))
                        : null,
                    'average_productivity' => $snapshotAvailable
                        ? $this->round($records->avg(fn ($r) => $r->productivity_score ?? $r->overall_score))
                        : null,
                    'data_quality_score' => $this->round($dqStats['score'] ?? null),
                ],
                'calculation_version' => config('performance.calculation_version', 'v1.0'),
                'data_sources' => ['tasks', 'reports', 'targets', 'performance_snapshots', 'data_quality_issues'],
                'has_data' => $tasksAssigned > 0 || $reportsSubmitted > 0 || $snapshotAvailable || $totalEmployees > 0,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /**
     * Monthly Performance Report (spec item 10) — one of the primary reports.
     *
     * @throws AuthorizationException
     */
    public function generateMonthlyReport(int $year, int $month, User $user): array
    {
        if (! in_array($user->roleName(), ['admin', 'hr', 'department_head'], true)) {
            throw new AuthorizationException('Forbidden');
        }

        $departmentId = $user->roleName() === 'department_head'
            ? (int) ($user->employee?->department_id ?? 0)
            : null;

        $period = $this->periodBounds(['year' => $year, 'month' => $month]);
        $cacheKey = sprintf('monthly_report:%d:%s', $user->id, hash('sha256', $year.'-'.$month.'-'.($departmentId ?? 'all')));

        return $this->cache->remember($cacheKey, function () use ($year, $month, $departmentId, $period) {
            [$start, $end] = [$period['start'], $period['end']];

            $recordQuery = EmployeePerformanceRecord::with(['employee:id,first_name,last_name,department_id', 'employee.department:id,name'])
                ->whereBetween('period_start', [$start, $end]);
            if ($departmentId) {
                $recordQuery->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
            }
            $records = $recordQuery->get();

            $sorted = $records->sortByDesc(fn ($r) => (float) ($r->productivity_score ?? $r->overall_score ?? 0));
            $topPerformer = $sorted->first();
            $highestCompletion = $records->sortByDesc(fn ($r) => (float) ($r->completion_rate ?? 0))->first();
            $highestTarget = $records->sortByDesc(fn ($r) => (float) ($r->target_achievement_rate ?? 0))->first();

            // Most improved: difference of stored scores vs the previous month
            // (no KPI recalculation — both numbers come from snapshots).
            $prevStart = (clone $start)->copy()->subMonthNoOverflow();
            $prevEnd = (clone $end)->copy()->subMonthNoOverflow();
            $previousRecords = EmployeePerformanceRecord::query()
                ->whereBetween('period_start', [$prevStart, $prevEnd])
                ->get(['employee_id', 'overall_score', 'productivity_score'])
                ->keyBy('employee_id');

            $mostImproved = $records
                ->map(function ($r) use ($previousRecords) {
                    $previous = $previousRecords->get($r->employee_id);

                    if ($previous === null) {
                        return null;
                    }

                    $currentScore = (float) ($r->productivity_score ?? $r->overall_score ?? 0);
                    $previousScore = (float) ($previous->productivity_score ?? $previous->overall_score ?? 0);

                    return [
                        'employee_id' => $r->employee_id,
                        'employee_name' => trim(($r->employee->first_name ?? '').' '.($r->employee->last_name ?? '')),
                        'department' => $r->employee?->department?->name,
                        'delta' => round($currentScore - $previousScore, 1),
                        'score' => round($currentScore, 1),
                    ];
                })
                ->filter()
                ->sortByDesc('delta')
                ->first();

            // Department rankings from stored snapshots (Phase 6 values only).
            $departmentRankings = $records
                ->groupBy(fn ($r) => $r->employee?->department_id)
                ->map(function ($group) {
                    $department = $group->first()?->employee?->department;

                    return [
                        'department_id' => $group->first()?->employee?->department_id,
                        'department_name' => $department?->name ?? 'Unassigned',
                        'employees_evaluated' => $group->count(),
                        'average_score' => $this->round($group->avg(fn ($r) => $r->productivity_score ?? $r->overall_score)),
                    ];
                })
                ->filter(fn ($row) => $row['department_id'] !== null)
                ->sortByDesc('average_score')
                ->values();

            $achievements = MonthlyAchievement::query()
                ->forMonth($month, $year)
                ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId)))
                ->count();

            $previousPeriod = $period['start']->copy()->subMonthNoOverflow();
            $previousRecordsAll = EmployeePerformanceRecord::query()
                ->whereBetween('period_start', [$previousPeriod, $previousPeriod->copy()->endOfMonth()])
                ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId)))
                ->get();

            $currentAvg = $records->avg(fn ($r) => $r->productivity_score ?? $r->overall_score);
            $previousAvg = $previousRecordsAll->avg(fn ($r) => $r->productivity_score ?? $r->overall_score);

            $assignmentQuery = TaskAssignment::query();
            $reportQuery = Report::query();
            if ($departmentId) {
                $assignmentQuery->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
                $reportQuery->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
            }
            $lateReports = (clone $reportQuery)
                ->where('is_late', true)
                ->whereBetween('submitted_at', [$start, $end])
                ->count();

            $label = Carbon::createFromDate($year, $month, 1)->format('F Y');

            return [
                'report_type' => 'monthly_performance',
                'period' => $label,
                'period_key' => sprintf('%04d-%02d', $year, $month),
                'scope' => $departmentId ? 'department' : 'institution',
                'top_performer' => $topPerformer ? [
                    'employee_id' => $topPerformer->employee_id,
                    'name' => trim(($topPerformer->employee->first_name ?? '').' '.($topPerformer->employee->last_name ?? '')),
                    'department' => $topPerformer->employee?->department?->name,
                    'score' => $this->round($topPerformer->productivity_score ?? $topPerformer->overall_score),
                ] : null,
                'highest_completion' => $highestCompletion ? [
                    'employee_id' => $highestCompletion->employee_id,
                    'name' => trim(($highestCompletion->employee->first_name ?? '').' '.($highestCompletion->employee->last_name ?? '')),
                    'completion_rate' => $this->round($highestCompletion->completion_rate),
                ] : null,
                'highest_target' => $highestTarget ? [
                    'employee_id' => $highestTarget->employee_id,
                    'name' => trim(($highestTarget->employee->first_name ?? '').' '.($highestTarget->employee->last_name ?? '')),
                    'target_rate' => $this->round($highestTarget->target_achievement_rate),
                ] : null,
                'most_improved' => $mostImproved,
                'top_department' => $departmentRankings->first(),
                'department_rankings' => $departmentRankings->all(),
                'summary' => [
                    'employees_evaluated' => $records->count(),
                    'average_productivity' => $this->round($currentAvg),
                    'previous_average_productivity' => $this->round($previousAvg),
                    'performance_change' => $currentAvg !== null && $previousAvg !== null
                        ? round((float) $currentAvg - (float) $previousAvg, 1)
                        : null,
                    'monthly_achievements' => $achievements,
                    'late_reports' => $lateReports,
                ],
                'calculation_version' => config('performance.calculation_version', 'v1.0'),
                'data_sources' => ['performance_snapshots', 'monthly_achievements', 'reports'],
                'has_data' => $records->isNotEmpty(),
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /* ---------------------------------------------------------------------
     | Achievement / late / target / pending / data-quality reports
     * ------------------------------------------------------------------- */

    /**
     * Monthly Achievement Report (spec item 11) — reuses Phase 6 achievement
     * records (MonthlyAchievement + EmployeeBadge); never awards anything.
     */
    public function generateAchievementReport(array $filters, User $user): array
    {
        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('achievement_report:%d:%s', $user->id, hash('sha256', json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($user, $period, $filters) {
            [$start, $end] = $period['start'] ? [$period['start'], $period['end']] : [null, null];
            $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));
            $employeeId = $this->nullableInt($filters['employee_id'] ?? null);

            if ($employeeId !== null) {
                $this->assertEmployeeScope($user, $employeeId);
            }

            $query = MonthlyAchievement::with(['employee.department:id,name'])
                ->when($start, function ($q) use ($start, $end) {
                    $year = (int) $start->year;
                    $month = (int) $start->month;

                    if ($start->format('Y-m-d') === $start->copy()->startOfMonth()->format('Y-m-d')
                        && $end->format('Y-m-d') === $start->copy()->endOfMonth()->format('Y-m-d')) {
                        $q->forMonth($month, $year);
                    } else {
                        $q->whereBetween('created_at', [$start, $end]);
                    }
                })
                ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId)))
                ->when($employeeId !== null, fn ($q) => $q->where('employee_id', $employeeId));

            $achievements = $query
                ->get()
                ->sortBy(fn ($a) => $a->rank ?? PHP_INT_MAX)
                ->values();

            // Badges awarded inside the period (Phase 6 awarding only).
            $badgeRows = $start
                ? EmployeeBadge::with('badge:id,name,code,icon')
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('period_start', [$start, $end])
                            ->orWhereBetween('awarded_at', [$start, $end]);
                    })
                    ->get()
                    ->groupBy('employee_id')
                : collect();

            $rows = $achievements->map(function ($a) use ($badgeRows) {
                $badge = $badgeRows->get($a->employee_id)?->first()?->badge;

                return [
                    'id' => $a->id,
                    'employee_id' => $a->employee_id,
                    'employee_name' => $a->employee
                        ? trim($a->employee->first_name.' '.$a->employee->last_name)
                        : 'N/A',
                    'department' => $a->employee?->department?->name,
                    'period' => sprintf('%04d-%02d', $a->year, $a->month),
                    'performance_score' => $this->round($a->overall_score),
                    'completion_rate' => $this->round($a->completion_rate),
                    'on_time_rate' => $this->round($a->on_time_rate),
                    'completed_tasks' => (int) $a->completed_tasks,
                    'approved_reports' => (int) $a->approved_reports,
                    'rank' => $a->rank,
                    'is_employee_of_month' => (bool) $a->is_employee_of_month,
                    'badge' => $badge ? [
                        'name' => $badge->name,
                        'code' => $badge->code,
                        'icon' => $badge->icon,
                    ] : null,
                ];
            })->all();

            return [
                'report_type' => 'achievement_summary',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'total' => count($rows),
                'total_badges' => count($rows),
                'achievements' => $rows,
                'rankings' => array_values(array_filter($rows, fn ($row) => $row['rank'] !== null)),
                'data_sources' => ['monthly_achievements', 'employee_badges'],
                'has_data' => $rows !== [],
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /**
     * Late Report Analytics (spec items 12-13): counts, rates, per-employee /
     * per-department breakdowns and a month-over-month change.
     */
    public function generateLateReport(array $filters, User $user): array
    {
        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('late_report:%d:%s', $user->id, hash('sha256', json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($user, $period, $filters) {
            [$start, $end] = $period['start'] ? [$period['start'], $period['end']] : [null, null];
            $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));

            $baseQuery = Report::with(['employee.department:id,name', 'task:id,title'])
                ->whereHas('employee');
            $this->scopeByEmployeeRelation($baseQuery, $user, $departmentId);

            $allQuery = clone $baseQuery;
            if ($start) {
                $allQuery->where(function ($q) use ($start, $end) {
                    $q->whereBetween('submitted_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('submitted_at')->whereBetween('created_at', [$start, $end]);
                        });
                });
            }
            $totalReports = (clone $allQuery)->count();

            $lateQuery = clone $allQuery;
            $lateQuery->where('is_late', true);
            $lateRows = $lateQuery->get();

            $lateByEmployee = $lateRows
                ->groupBy(fn ($r) => $r->employee_id)
                ->map(fn ($group, $employeeId) => [
                    'employee_id' => (int) $employeeId,
                    'employee_name' => $group->first()->employee
                        ? trim($group->first()->employee->first_name.' '.$group->first()->employee->last_name)
                        : 'N/A',
                    'department' => $group->first()->employee?->department?->name,
                    'late_reports' => $group->count(),
                ])
                ->values();

            $lateByDepartment = $lateRows
                ->groupBy(fn ($r) => $r->employee?->department_id)
                ->map(fn ($group, $departmentId) => [
                    'department_id' => $departmentId !== null ? (int) $departmentId : null,
                    'department_name' => $group->first()->employee?->department?->name ?? 'Unassigned',
                    'late_reports' => $group->count(),
                ])
                ->sortByDesc('late_reports')
                ->values();

            // Previous comparable window for the trend/change figure.
            $previousChange = null;
            if ($start && $end) {
                $previousStart = $start->copy()->subMonthsNoOverflow(max(1, (int) $start->diffInMonths($end) + 1 ?: 1));
                $previousEnd = $end->copy()->subMonthsNoOverflow(max(1, (int) $start->diffInMonths($end) + 1 ?: 1));

                // For a single month window use exact previous month.
                if ($start->isSameMonth($end)) {
                    $previousStart = $start->copy()->subMonthNoOverflow();
                    $previousEnd = $end->copy()->subMonthNoOverflow();
                }

                $previousAll = clone $baseQuery;
                $previousAll->where(function ($q) use ($previousStart, $previousEnd) {
                    $q->whereBetween('submitted_at', [$previousStart, $previousEnd])
                        ->orWhere(function ($q2) use ($previousStart, $previousEnd) {
                            $q2->whereNull('submitted_at')->whereBetween('created_at', [$previousStart, $previousEnd]);
                        });
                });
                $previousTotal = (clone $previousAll)->count();

                $previousLate = (clone $previousAll)->where('is_late', true)->count();
                $currentLate = $lateRows->count();

                if ($previousLate > 0) {
                    $previousChange = [
                        'period' => $previousStart->format('M Y'),
                        'late_reports' => $previousLate,
                        'total_reports' => $previousTotal,
                        'change_pct' => round((($currentLate - $previousLate) / $previousLate) * 100, 1),
                    ];
                }
            }

            $lateRate = $totalReports > 0 ? round(($lateRows->count() / $totalReports) * 100, 1) : null;

            $lateEmployees = $lateByEmployee->count();
            $lateDepartments = $lateByDepartment->count();

            return [
                'report_type' => 'late_reports',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'summary' => [
                    'late_reports' => $lateRows->count(),
                    'total_reports' => $totalReports,
                    'late_submission_rate' => $lateRate,
                    'late_employees' => $lateEmployees,
                    'late_departments' => $lateDepartments,
                ],
                'comparison' => $previousChange,
                'by_employee' => $lateByEmployee->all(),
                'by_department' => $lateByDepartment->all(),
                'reports' => $lateRows->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'employee' => $r->employee
                        ? trim($r->employee->first_name.' '.$r->employee->last_name)
                        : 'N/A',
                    'department' => $r->employee?->department?->name,
                    'task' => $r->task?->title,
                    'status' => $r->status instanceof ReportStatus ? $r->status->value : (string) $r->status,
                    'submitted_at' => $r->submitted_at?->toDateTimeString(),
                    'deadline' => $r->deadline?->toDateTimeString(),
                ])->values()->all(),
                'data_sources' => ['reports'],
                'has_data' => $totalReports > 0,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /**
     * Target analytics (spec item 15): total / achieved / partial /
     * unachieved / achievement rate with period + scope support.
     */
    public function generateTargetReport(array $filters, User $user): array
    {
        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('target_report:%d:%s', $user->id, hash('sha256', json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($user, $period, $filters) {
            [$start, $end] = $period['start'] ? [$period['start'], $period['end']] : [null, null];
            $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));
            $employeeId = $this->nullableInt($filters['employee_id'] ?? null);

            if ($employeeId !== null) {
                $this->assertEmployeeScope($user, $employeeId);
            }

            $query = EmployeeTarget::with(['employee.department:id,name']);
            $this->scopeByEmployeeRelation($query, $user, $departmentId);

            if ($employeeId !== null) {
                $query->where('employee_id', $employeeId);
            }

            if ($start) {
                $query->where(function ($q) use ($start, $end) {
                    $q->whereBetween('created_at', [$start, $end])
                        ->orWhereBetween('start_date', [$start, $end])
                        ->orWhereBetween('end_date', [$start, $end]);
                });
            }

            $targets = $query->get(['id', 'employee_id', 'title', 'status', 'target_value', 'target_unit', 'start_date', 'end_date']);

            $total = $targets->count();
            $achieved = $targets->filter(fn ($t) => in_array($t->status, self::TARGET_ACHIEVED_STATUSES, true))->count();
            $partial = $targets->filter(fn ($t) => in_array($t->status, ['in_progress', 'active', 'submitted'], true))->count();
            $unachieved = max(0, $total - $achieved - $partial);

            $byDepartment = $targets
                ->groupBy(fn ($t) => $t->employee?->department_id)
                ->map(function ($group) {
                    $deptAchieved = $group->filter(fn ($t) => in_array($t->status, self::TARGET_ACHIEVED_STATUSES, true))->count();

                    return [
                        'department_id' => $group->first()?->employee?->department_id,
                        'department_name' => $group->first()?->employee?->department?->name ?? 'Unassigned',
                        'total_targets' => $group->count(),
                        'achieved' => $deptAchieved,
                        'achievement_rate' => $this->rate($deptAchieved, $group->count()),
                    ];
                })
                ->filter(fn ($row) => $row['department_id'] !== null)
                ->sortByDesc('achievement_rate')
                ->values();

            return [
                'report_type' => 'targets_summary',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'total_targets' => $total,
                'achieved_targets' => $achieved,
                'partially_achieved' => $partial,
                'unachieved' => $unachieved,
                'in_progress' => $partial,
                'achievement_rate' => $this->rate($achieved, $total),
                'by_department' => $byDepartment->all(),
                'targets' => $targets->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                    'target_value' => (float) $t->target_value,
                    'target_unit' => $t->target_unit,
                    'employee' => $t->employee
                        ? trim($t->employee->first_name.' '.$t->employee->last_name)
                        : 'N/A',
                    'department' => $t->employee?->department?->name,
                    'start_date' => $t->start_date?->toDateString(),
                    'end_date' => $t->end_date?->toDateString(),
                ])->values()->all(),
                'data_sources' => ['employee_targets'],
                'has_data' => $total > 0,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /**
     * Pending report analytics (spec item 13).
     */
    public function generatePendingReport(array $filters, User $user): array
    {
        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('pending_report:%d:%s', $user->id, hash('sha256', json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($user, $period, $filters) {
            [$start, $end] = $period['start'] ? [$period['start'], $period['end']] : [null, null];
            $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));

            $query = Report::with(['employee.department:id,name', 'task:id,title']);
            $this->scopeByEmployeeRelation($query, $user, $departmentId);

            $query->whereIn('status', self::PENDING_STATUSES);
            if ($start) {
                $query->where(function ($q) use ($start, $end) {
                    $q->whereBetween('submitted_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('submitted_at')->whereBetween('created_at', [$start, $end]);
                        });
                });
            }

            $pending = $query->get();

            $byEmployee = $pending
                ->groupBy(fn ($r) => $r->employee_id)
                ->map(fn ($group) => [
                    'employee_id' => (int) $group->first()->employee_id,
                    'employee_name' => $group->first()->employee
                        ? trim($group->first()->employee->first_name.' '.$group->first()->employee->last_name)
                        : 'N/A',
                    'department' => $group->first()->employee?->department?->name,
                    'pending_reports' => $group->count(),
                ])
                ->values();

            $byDepartment = $pending
                ->groupBy(fn ($r) => $r->employee?->department_id)
                ->map(fn ($group) => [
                    'department_id' => $group->first()?->employee?->department_id,
                    'department_name' => $group->first()->employee?->department?->name ?? 'Unassigned',
                    'pending_reports' => $group->count(),
                ])
                ->sortByDesc('pending_reports')
                ->values();

            $byStatus = $pending
                ->countBy(fn ($r) => $r->status instanceof ReportStatus ? $r->status->value : (string) $r->status)
                ->map(fn ($count, $status) => ['status' => $status, 'count' => $count])
                ->values();

            $byMonth = $pending
                ->groupBy(fn ($r) => ($r->submitted_at ?? $r->created_at)?->format('M Y') ?? 'Unknown')
                ->map(fn ($group, $label) => ['period' => $label, 'pending_reports' => $group->count()])
                ->sortBy('period')
                ->values();

            return [
                'report_type' => 'pending_reports',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'summary' => [
                    'pending_reports' => $pending->count(),
                    'pending_employees' => $byEmployee->count(),
                    'pending_departments' => $byDepartment->filter(fn ($r) => $r['department_id'] !== null)->count(),
                ],
                'by_employee' => $byEmployee->all(),
                'by_department' => $byDepartment->all(),
                'by_status' => $byStatus->all(),
                'by_month' => $byMonth->all(),
                'reports' => $pending->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'employee' => $r->employee
                        ? trim($r->employee->first_name.' '.$r->employee->last_name)
                        : 'N/A',
                    'department' => $r->employee?->department?->name,
                    'status' => $r->status instanceof ReportStatus ? $r->status->value : (string) $r->status,
                    'submitted_at' => $r->submitted_at?->toDateTimeString(),
                ])->values()->all(),
                'data_sources' => ['reports'],
                'has_data' => $pending->isNotEmpty(),
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /**
     * Data Quality Report (spec item 3 section) — reuses the Phase 8
     * statistics service (already role-scoped).
     */
    public function generateDataQualityReport(array $filters, User $user): array
    {
        $period = $this->periodBounds($filters);
        $cacheKey = sprintf('dq_report:%d:%s', $user->id, hash('sha256', json_encode($period['key'])));

        return $this->cache->remember($cacheKey, function () use ($user, $period) {
            $stats = $this->dataQualityStatistics->getDashboardStatistics($user);
            $trends = $this->dataQualityStatistics->getMonthlyTrends($user);

            return [
                'report_type' => 'data_quality',
                'period' => $period['label'],
                'period_key' => $period['key'],
                'summary' => [
                    'data_quality_score' => $this->round($stats['score'] ?? null),
                    'total_issues' => (int) ($stats['total_issues'] ?? 0),
                    'open_issues' => (int) ($stats['open_issues'] ?? 0),
                    'critical' => (int) ($stats['critical'] ?? 0),
                    'high' => (int) ($stats['high'] ?? 0),
                    'medium' => (int) ($stats['medium'] ?? 0),
                    'low' => (int) ($stats['low'] ?? 0),
                    'resolved' => (int) ($stats['resolved'] ?? 0),
                    'dismissed' => (int) ($stats['dismissed'] ?? 0),
                ],
                'by_category' => $stats['by_category'] ?? [],
                'by_department' => $stats['by_department'] ?? [],
                'trends' => $trends,
                'recent_scans' => collect($stats['recent_scans'] ?? [])->map(fn ($scan) => [
                    'id' => $scan->id,
                    'status' => (string) $scan->status,
                    'created_at' => $scan->created_at?->toIso8601String(),
                ])->values()->all(),
                'data_sources' => ['data_quality_issues', 'data_quality_scans'],
                'has_data' => (int) ($stats['total_issues'] ?? 0) > 0,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }, $this->ttl());
    }

    /* ---------------------------------------------------------------------
     | Shared helpers
     * ------------------------------------------------------------------- */

    /**
     * Server-side period resolution (spec item 4): custom range → quarter →
     * month → year → current month. Returns inclusive Carbon bounds, a
     * display label and a stable cache key fragment.
     *
     * @return array{start: Carbon|null, end: Carbon|null, label: string, key: array}
     */
    public function periodBounds(array $filters): array
    {
        $from = $filters['start_date'] ?? $filters['from_date'] ?? null;
        $to = $filters['end_date'] ?? $filters['to_date'] ?? null;

        if ($from && $to) {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();

            return [
                'start' => $start,
                'end' => $end,
                'label' => $start->format('M j, Y').' – '.$end->format('M j, Y'),
                'key' => ['type' => 'custom', 'from' => $start->toDateString(), 'to' => $end->toDateString()],
            ];
        }

        $year = $this->nullableInt($filters['year'] ?? null) ?? (int) Carbon::now()->year;
        $quarter = $this->nullableInt($filters['quarter'] ?? null);
        $month = $this->nullableInt($filters['month'] ?? null);

        if ($quarter !== null) {
            $startMonth = (($quarter - 1) * 3) + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = $start->copy()->addMonthsNoOverflow(3)->subDay()->endOfDay();

            return [
                'start' => $start,
                'end' => $end,
                'label' => sprintf('Q%d %d', $quarter, $year),
                'key' => ['type' => 'quarterly', 'year' => $year, 'quarter' => $quarter],
            ];
        }

        $periodType = strtolower((string) ($filters['period_type'] ?? 'monthly'));

        if ($periodType === 'yearly') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();

            return [
                'start' => $start,
                'end' => $end,
                'label' => (string) $year.' Annual',
                'key' => ['type' => 'yearly', 'year' => $year],
            ];
        }

        if ($month !== null) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth()->endOfDay();

            return [
                'start' => $start,
                'end' => $end,
                'label' => $start->format('F Y'),
                'key' => ['type' => 'monthly', 'year' => $year, 'month' => $month],
            ];
        }

        // Default: current month.
        $defaultMonth = now()->month;
        $start = Carbon::create($year, $defaultMonth, 1)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => $start->format('F Y'),
            'key' => ['type' => 'monthly', 'year' => $year, 'month' => (int) $start->month],
        ];
    }

    /**
     * Safe rate: null (N/A) instead of a misleading 0% when the denominator
     * is zero (spec item 51).
     */
    private function rate(?int $numerator, ?int $denominator): ?float
    {
        if ($denominator === null || $denominator <= 0) {
            return null;
        }

        return round((($numerator ?? 0) / $denominator) * 100, 1);
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * Weighted productivity fallback used only when no Phase 6 snapshot
     * exists — weights mirror config('performance.weights'). Null rates are
     * dropped and the remaining weights renormalised so missing data never
     * drags the score to zero.
     *
     * @param  array<string, float|null>  $rates
     */
    private function weightedProductivity(array $rates): ?float
    {
        $weights = [
            'task_completion_rate' => (float) config('performance.weights.task_completion', 0.30),
            'report_completion_rate' => (float) config('performance.weights.report_completion', 0.20),
            'on_time_submission_rate' => (float) config('performance.weights.on_time_submission', 0.20),
            'approval_rate' => (float) config('performance.weights.report_approval', 0.15),
            'target_achievement_rate' => (float) config('performance.weights.target_achievement', 0.15),
        ];

        $total = 0.0;
        $weightSum = 0.0;

        foreach ($weights as $key => $weight) {
            if (! isset($rates[$key]) || $rates[$key] === null) {
                continue;
            }

            $total += ((float) $rates[$key]) * $weight;
            $weightSum += $weight;
        }

        if ($weightSum <= 0) {
            return null;
        }

        return round(($total / $weightSum), 1);
    }

    private function ttl(): int
    {
        return (int) config('reports.cache_ttl', 60);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
