<?php

namespace App\Services\Admin;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\DepartmentEscalation;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\OcrProcessing;
use App\Models\Report;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Institution-wide executive dashboard for the Admin role.
 *
 * Design rules enforced here:
 *  - Every number is derived from a real query against the database. No
 *    hardcoded institution name, academic year or statistic.
 *  - Aggregation happens in SQL (COUNT/SUM/AVG + conditional sums), not by
 *    loading whole tables into memory and looping in PHP.
 *  - Nothing is labelled "AI" unless an AI pipeline actually produced it.
 *    `ai_executive_summary` delegates to AdminInsightService and reports
 *    `available: false` with the message "No AI insight available" when the
 *    ai_insights table holds no generated rows. In this installation it is
 *    empty and the stored validation scores are development sample data, so
 *    they are returned with `validation_is_sample_data: true`.
 *  - The rule-based digest is exposed as `operational_digest` — a separate
 *    key from the AI summary — so the frontend can never present computed
 *    arithmetic as a machine-generated finding.
 *
 * NOTE: the response is a strict SUPERSET of the previous payload — every key
 * this service returned before is still present, so existing consumers
 * (AdminExecutiveDashboardPage, executive report pages) keep working.
 */
class AdminExecutiveDashboardService
{
    /**
     * Task-assignment statuses that count as finished work.
     */
    private const DONE_STATUSES = ['completed', 'approved'];

    /**
     * Task-assignment statuses that count as open work.
     */
    private const OPEN_STATUSES = ['assigned', 'accepted', 'in_progress', 'submitted'];

    public function getDashboard(): array
    {
        $taskStats = $this->taskStats();
        $reportStats = $this->reportStats();
        $employeeStats = $this->employeeStats();
        $overdueAssignments = $this->overdueAssignments();

        $institution = $this->institution();
        $completionRate = $this->rate($taskStats['completed'], $taskStats['total']);

        $onTimeRate = $taskStats['completed'] > 0
            ? $this->rate($taskStats['on_time'], $taskStats['completed'])
            : 0.0;

        $reviewedReports = $reportStats['approved'] + $reportStats['revision_required'] + $reportStats['rejected'];
        $approvalRate = $this->rate($reportStats['approved'], $reviewedReports);

        $performance = $this->performanceStats();

        return [
            'institution' => $institution,

            // ── Institution KPI block (12 cards) ────────────────────────────
            'kpis' => [
                // Legacy keys — retained verbatim for existing consumers.
                'total_employees' => $employeeStats['total'],
                'active_employees' => $employeeStats['active'],
                'total_departments' => $this->departmentCount(),
                'total_tasks' => $taskStats['total'],
                'completed_tasks' => $taskStats['completed'],
                'pending_tasks' => $taskStats['pending'],
                'overdue_tasks' => $overdueAssignments->count(),
                'submitted_reports' => $reportStats['submitted'],
                'pending_reviews' => $reportStats['under_review'],
                'approved_reports' => $reportStats['approved'],
                'revision_required' => $reportStats['revision_required'],
                'completion_rate' => $completionRate,
                'on_time_rate' => $onTimeRate,
                'approval_rate' => $approvalRate,
                'institution_progress' => $completionRate,

                // Added for the executive card grid.
                'in_progress_tasks' => $taskStats['in_progress'],
                'not_started_tasks' => $taskStats['not_started'],
                'late_tasks' => $taskStats['late'],
                'reports_pending' => $reportStats['pending'],
                'reports_approved' => $reportStats['approved'],
                'reports_needs_revision' => $reportStats['revision_required'],
                'reports_rejected' => $reportStats['rejected'],
                'reports_under_review' => $reportStats['under_review'],
                'reports_late' => $reportStats['late'],
                'avg_kpi_achievement' => $performance['avg_kpi_achievement'],
                'ai_validation_score' => $performance['ai_validation_score'],
                'department_performance' => $performance['department_performance'],
            ],

            'status_distribution' => [
                'tasks' => [
                    'completed' => $taskStats['completed'],
                    'in_progress' => $taskStats['in_progress'],
                    'pending' => $taskStats['not_started'],
                    'overdue' => $overdueAssignments->count(),
                ],
                'reports' => [
                    'approved' => $reportStats['approved'],
                    'under_review' => $reportStats['under_review'],
                    'revision_required' => $reportStats['revision_required'],
                    'rejected' => $reportStats['rejected'],
                    'submitted' => $reportStats['submitted'],
                ],
            ],

            'department_performance' => $this->departmentPerformance(),

            'monthly_achievements' => $this->monthlyAchievements(),

            'critical_alerts' => [
                'overdue_tasks' => $this->topOverdueTasks($overdueAssignments),
                'pending_reviews_count' => $reportStats['under_review'],
                'revision_count' => $reportStats['revision_required'],
                'open_escalations_count' => $this->openEscalationCount(),
                'ocr_failed_count' => $this->failedOcrCount(),
            ],

            'ai_executive_summary' => $this->aiExecutiveSummary(),

            // The rule-based digest is deliberately NOT called an AI summary.
            // It is plain arithmetic over the figures above, so it is exposed
            // under its own key and the UI must not label it "AI".
            'operational_digest' => $this->operationalDigest(
                $institution,
                $taskStats,
                $reportStats,
                $employeeStats,
                $overdueAssignments->count(),
                $performance,
            ),
        ];
    }

    /**
     * AI executive summary.
     *
     * Reports ONLY what the AI subsystem actually produced. In this
     * installation `ai_insights` is empty and the stored validation rows came
     * from the development seeder, so the payload correctly reports
     * "No AI insight available" and flags the scores as sample data.
     *
     * @return array<string, mixed>
     */
    private function aiExecutiveSummary(): array
    {
        $insightService = app(AdminInsightService::class);
        $insights = $insightService->insights();
        $validation = $insightService->validationOverview();
        $provider = $insightService->providerStatus();

        $recent = collect($insights['items'])
            ->sortByDesc(fn (array $item) => $item['generated_at'] ?? '')
            ->take(3)
            ->values()
            ->all();

        return [
            'available' => $insights['available'],
            'generated' => $insights['available'],
            'message' => $insights['available'] ? null : AdminInsightService::MESSAGE_NONE,
            'items' => $recent,
            'total' => $insights['counts']['total'],
            'validation_score' => $validation['avg_score'],
            'validation_is_sample_data' => $validation['is_sample_data'],
            'provider_status' => $provider,
        ];
    }

    /**
     * Institution identity read from configuration and the academic_years table.
     *
     * @return array<string, string>
     */
    private function institution(): array
    {
        $year = AcademicYear::query()
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();

        return [
            'name' => (string) config('jpc.institution.name'),
            'system_name' => (string) config('jpc.institution.system_name'),
            'academic_year' => $year?->name ?? 'Not configured',
            'academic_year_status' => $year?->status ?? 'none',
            'current_term' => $this->currentTerm($year?->start_date, $year?->end_date),
        ];
    }

    /**
     * Derive a human term label from the academic-year window.
     */
    private function currentTerm(?Carbon $start, ?Carbon $end): string
    {
        if (! $start || ! $end) {
            return 'Not configured';
        }

        $now = now();

        if ($now->lt($start)) {
            return 'Not yet started';
        }

        if ($now->gt($end)) {
            return 'Closed';
        }

        $halfway = $start->copy()->addDays((int) $start->diffInDays($end) / 2);

        return $now->lte($halfway)
            ? 'First Semester '.$start->year
            : 'Second Semester '.$start->year;
    }

    /**
     * Task-assignment counters, aggregated in a single query.
     *
     * @return array<string, int>
     */
    private function taskStats(): array
    {
        $row = TaskAssignment::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status in ("completed","approved") then 1 else 0 end) as completed')
            ->selectRaw('sum(case when status in ("assigned","accepted") then 1 else 0 end) as not_started')
            ->selectRaw('sum(case when status = "in_progress" then 1 else 0 end) as in_progress')
            ->selectRaw('sum(case when status in ("assigned","accepted","in_progress","submitted") then 1 else 0 end) as pending')
            ->selectRaw('sum(case when status in ("completed","approved") and due_date is not null and completed_at is not null and completed_at > due_date then 1 else 0 end) as late')
            ->selectRaw('sum(case when status in ("completed","approved") and (due_date is null or completed_at is null or completed_at <= due_date) then 1 else 0 end) as on_time')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'not_started' => (int) ($row->not_started ?? 0),
            'in_progress' => (int) ($row->in_progress ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'late' => (int) ($row->late ?? 0),
            'on_time' => (int) ($row->on_time ?? 0),
        ];
    }

    /**
     * Report counters, aggregated in a single query.
     *
     * @return array<string, int>
     */
    private function reportStats(): array
    {
        $row = Report::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status in ("submitted","resubmitted","processing") then 1 else 0 end) as submitted')
            ->selectRaw('sum(case when status = "under_review" then 1 else 0 end) as under_review')
            ->selectRaw('sum(case when status = "approved" then 1 else 0 end) as approved')
            ->selectRaw('sum(case when status = "revision_required" then 1 else 0 end) as revision_required')
            ->selectRaw('sum(case when status = "rejected" then 1 else 0 end) as rejected')
            ->selectRaw('sum(case when status = "draft" then 1 else 0 end) as pending')
            ->selectRaw('sum(case when is_late = 1 then 1 else 0 end) as late')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'submitted' => (int) ($row->submitted ?? 0),
            'under_review' => (int) ($row->under_review ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'revision_required' => (int) ($row->revision_required ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'late' => (int) ($row->late ?? 0),
        ];
    }

    /**
     * @return array{total: int, active: int}
     */
    private function employeeStats(): array
    {
        $row = Employee::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when employment_status = "active" then 1 else 0 end) as active')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
        ];
    }

    private function departmentCount(): int
    {
        return Department::query()->count();
    }

    private function openEscalationCount(): int
    {
        // "Open" covers every non-terminal escalation status (pending, under
        // review, action required) — resolved and rejected are closed.
        return DepartmentEscalation::query()->open()->count();
    }

    private function failedOcrCount(): int
    {
        return OcrProcessing::query()->where('status', 'failed')->count();
    }

    /**
     * Open assignments whose effective deadline has passed.
     *
     * The effective deadline is `task_assignments.due_date`, falling back to
     * the parent task's `due_date` when the assignment has no own deadline.
     * The OR branches are nested inside a single AND group so the
     * "not finished" filter always applies.
     *
     * @return Collection<int, TaskAssignment>
     */
    private function overdueAssignments()
    {
        $today = now()->toDateString();

        return TaskAssignment::query()
            ->with(['task:id,title,due_date,department_id', 'employee:id,first_name,last_name,department_id,employee_number'])
            ->whereNotIn('status', self::DONE_STATUSES)
            ->where(function (Builder $group) use ($today) {
                $group
                    ->where(function (Builder $direct) use ($today) {
                        $direct->whereNotNull('task_assignments.due_date')
                            ->where('task_assignments.due_date', '<', $today);
                    })
                    ->orWhere(function (Builder $fallback) use ($today) {
                        $fallback->whereNull('task_assignments.due_date')
                            ->whereHas('task', fn (Builder $task) => $task
                                ->whereNotNull('due_date')
                                ->where('due_date', '<', $today));
                    });
            })
            ->get();
    }

    /**
     * Performance + AI validation aggregates used by the KPI cards.
     *
     * `avg_kpi_achievement` comes from the persisted performance snapshots
     * (target_achievement_rate). `ai_validation_score` is the mean
     * ai_validation_results.overall_score — stored as a 0..1 fraction, so it is
     * multiplied by 100 and clamped. It is null when the AI pipeline has not
     * produced any validation result yet.
     *
     * @return array{avg_kpi_achievement: float, ai_validation_score: float|null, department_performance: float}
     */
    private function performanceStats(): array
    {
        $kpi = EmployeePerformanceRecord::query()
            ->whereNotNull('target_achievement_rate')
            ->avg('target_achievement_rate');

        $ai = DB::table('ai_validation_results')
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        $departments = Department::query()->count();

        return [
            'avg_kpi_achievement' => $kpi === null ? 0.0 : round((float) $kpi, 2),
            'ai_validation_score' => $ai === null
                ? null
                : round(max(0.0, min(1.0, (float) $ai)) * 100, 2),
            'department_performance' => $departments > 0
                ? round($this->employeeStats()['active'] / $departments, 2)
                : 0.0,
        ];
    }

    /**
     * Per-department comparison.
     *
     * Rank is retained because ranking is an existing system feature, but the
     * payload is a flat comparison (counts + rates) so the new UI can render
     * neutral side-by-side visuals without a leaderboard.
     *
     * @return list<array<string, mixed>>
     */
    private function departmentPerformance(): array
    {
        $rows = DB::table('departments')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.department_id', '=', 'departments.id')
                    ->whereNull('employees.deleted_at');
            })
            ->leftJoin('task_assignments', function ($join) {
                $join->on('task_assignments.employee_id', '=', 'employees.id')
                    ->whereNull('task_assignments.deleted_at');
            })
            ->leftJoin('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->whereNull('departments.deleted_at')
            ->groupBy('departments.id', 'departments.name', 'departments.code')
            ->select('departments.id', 'departments.name', 'departments.code')
            ->selectRaw('count(distinct employees.id) as employees_count')
            ->selectRaw('count(task_assignments.id) as tasks_count')
            ->selectRaw('sum(case when task_assignments.status in ("completed","approved") then 1 else 0 end) as completed_count')
            // Mirrors overdueAssignments(): assignment deadline, else task deadline.
            ->selectRaw(
                'sum(case when task_assignments.id is not null'
                .' and task_assignments.status not in ("completed","approved")'
                .' and coalesce(task_assignments.due_date, tasks.due_date) is not null'
                .' and coalesce(task_assignments.due_date, tasks.due_date) < curdate()'
                .' then 1 else 0 end) as overdue_count'
            )
            ->orderBy('departments.name')
            ->get();

        $reportCounts = $this->departmentReportCounts();

        return $rows
            ->map(function ($row) use ($reportCounts) {
                $total = (int) $row->tasks_count;
                $completed = (int) $row->completed_count;

                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'code' => $row->code,
                    'employees_count' => (int) $row->employees_count,
                    'tasks_count' => $total,
                    'completed_count' => $completed,
                    'overdue_count' => (int) $row->overdue_count,
                    'reports_count' => $reportCounts[(int) $row->id] ?? 0,
                    'completion_rate' => $this->rate($completed, $total),
                ];
            })
            ->sortByDesc('completion_rate')
            ->values()
            ->map(fn (array $item, int $index) => $item + ['rank' => $index + 1])
            ->all();
    }

    /**
     * Report counts per department in a single query.
     *
     * @return array<int, int>
     */
    private function departmentReportCounts(): array
    {
        return DB::table('reports')
            ->join('employees', 'employees.id', '=', 'reports.employee_id')
            ->whereNull('reports.deleted_at')
            ->whereNull('employees.deleted_at')
            ->groupBy('employees.department_id')
            ->select('employees.department_id')
            ->selectRaw('count(*) as total')
            ->pluck('total', 'department_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Highest on-time completion rates, used for the "monthly achievers" card.
     *
     * @return list<array<string, mixed>>
     */
    private function monthlyAchievements(): array
    {
        return DB::table('employees')
            ->leftJoin('task_assignments', 'task_assignments.employee_id', '=', 'employees.id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_status', 'active')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'departments.name')
            ->select('employees.id')
            ->selectRaw('concat(employees.first_name, " ", employees.last_name) as name')
            ->selectRaw('departments.name as department_name')
            ->selectRaw('count(task_assignments.id) as total')
            ->selectRaw('sum(case when task_assignments.status in ("completed","approved") then 1 else 0 end) as completed')
            ->orderByRaw('case when count(task_assignments.id) = 0 then 0 else (sum(case when task_assignments.status in ("completed","approved") then 1 else 0 end) / count(task_assignments.id)) end desc')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $rate = $this->rate((int) $row->completed, (int) $row->total);

                return [
                    'employee_id' => (int) $row->id,
                    'name' => $row->name,
                    'department_name' => $row->department_name ?? 'Academic',
                    'position' => 'Faculty',
                    'completed_tasks' => (int) $row->completed,
                    'completion_rate' => $rate,
                    'badge' => $this->achievementLabel($rate),
                    'icon' => $this->achievementIcon($rate),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, TaskAssignment>  $overdue
     * @return list<array<string, mixed>>
     */
    private function topOverdueTasks($overdue, int $limit = 5): array
    {
        return $overdue
            ->sortByDesc(fn (TaskAssignment $a) => $a->due_date ?? $a->task?->due_date)
            ->take($limit)
            ->map(function (TaskAssignment $assignment) {
                $deadline = $assignment->due_date ?? $assignment->task?->due_date;

                return [
                    'assignment_id' => $assignment->id,
                    'task_title' => $assignment->task?->title ?? 'Untitled Task',
                    'employee_name' => $assignment->employee
                        ? $assignment->employee->first_name.' '.$assignment->employee->last_name
                        : 'Faculty',
                    'department_name' => 'Academic',
                    'days_overdue' => $deadline ? max(1, (int) $deadline->diffInDays(now())) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Executive narrative.
     *
     * HONESTY CONTRACT: this is a deterministic, rule-based digest of the
     * numbers on this same page — it is NOT an LLM output. It is reported
     * with `source = "rule_based"` and `generated = true`. If there is no
     * data at all, `generated` is false and the UI shows
     * "No AI insight available" rather than inventing a summary.
     *
     * @param  array<string, string>  $institution
     * @param  array<string, int>  $taskStats
     * @param  array<string, int>  $reportStats
     * @param  array{total: int, active: int}  $employeeStats
     * @param  array<string, float|null>  $performance
     * @return array<string, mixed>
     */
    private function operationalDigest(
        array $institution,
        array $taskStats,
        array $reportStats,
        array $employeeStats,
        int $overdueCount,
        array $performance,
    ): array {
        $hasData = $taskStats['total'] > 0 || $reportStats['total'] > 0 || $employeeStats['total'] > 0;

        if (! $hasData) {
            return [
                'available' => false,
                'text' => null,
                'key_accomplishments' => [],
                'areas_needing_attention' => [],
                'recommendations' => [],
                'disclaimer' => 'No operational data has been recorded yet, so no digest can be computed.',
            ];
        }

        $completionRate = $this->rate($taskStats['completed'], $taskStats['total']);
        $onTimeRate = $this->rate($taskStats['on_time'], $taskStats['completed']);
        $approvalRate = $this->rate(
            $reportStats['approved'],
            $reportStats['approved'] + $reportStats['revision_required'] + $reportStats['rejected'],
        );

        $text = sprintf(
            '%s has %d active employees across %d departments. Task completion stands at %s%% (%d of %d) with an on-time rate of %s%%. '
                .'Report approval rate is %s%% with %d report(s) awaiting review and %d overdue deliverable(s).',
            $institution['name'],
            $employeeStats['active'],
            $this->departmentCount(),
            $completionRate,
            $taskStats['completed'],
            $taskStats['total'],
            $onTimeRate,
            $approvalRate,
            $reportStats['under_review'],
            $overdueCount,
        );

        $accomplishments = [];
        if ($taskStats['completed'] > 0) {
            $accomplishments[] = $taskStats['completed'].' of '.$taskStats['total'].' assigned deliverables completed ('
                .$completionRate.'%).';
        }
        if ($reportStats['approved'] > 0) {
            $accomplishments[] = $reportStats['approved'].' faculty report(s) formally approved.';
        }
        if ($performance['avg_kpi_achievement'] > 0) {
            $accomplishments[] = 'Average KPI achievement of '.$performance['avg_kpi_achievement'].'% across recorded performance periods.';
        }
        if ($accomplishments === []) {
            $accomplishments[] = 'No completed deliverables recorded for the current period yet.';
        }

        $attention = [];
        if ($overdueCount > 0) {
            $attention[] = $overdueCount.' deliverable(s) are past their deadline and remain open.';
        }
        if ($reportStats['revision_required'] > 0) {
            $attention[] = $reportStats['revision_required'].' report(s) returned for revision require follow-up.';
        }
        if ($reportStats['under_review'] > 0) {
            $attention[] = $reportStats['under_review'].' report(s) awaiting reviewer action.';
        }
        if ($taskStats['in_progress'] > 0) {
            $attention[] = $taskStats['in_progress'].' deliverable(s) currently in progress.';
        }
        if ($attention === []) {
            $attention[] = 'No overdue deliverables or pending reviews detected.';
        }

        $recommendations = [];
        if ($overdueCount > 0) {
            $recommendations[] = 'Prioritise the overdue deliverables listed under Critical Alerts and confirm revised deadlines with the responsible Department Heads.';
        }
        if ($performance['ai_validation_score'] === null) {
            $recommendations[] = 'No AI validation results are stored yet; trigger the AI analysis pipeline so report quality scoring becomes available.';
        }
        if ($recommendations === []) {
            $recommendations[] = 'Maintain the current review cadence; no corrective action is indicated by the current data.';
        }

        return [
            'available' => true,
            'text' => $text,
            'key_accomplishments' => $accomplishments,
            'areas_needing_attention' => $attention,
            'recommendations' => $recommendations,
            'disclaimer' => 'Deterministic digest computed from the live figures on this dashboard. '
                .'It is plain arithmetic over recorded data — not an LLM generation — and the UI must not label it as AI output.',
        ];
    }

    private function achievementLabel(float $rate): string
    {
        return match (true) {
            $rate >= 90 => 'Outstanding Achievement',
            $rate >= 75 => 'High Performer',
            $rate >= 50 => 'Target Achiever',
            default => 'In Progress',
        };
    }

    private function achievementIcon(float $rate): string
    {
        return match (true) {
            $rate >= 90 => '🏆',
            $rate >= 75 => '⭐',
            $rate >= 50 => '🎯',
            default => '⏳',
        };
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }
}
