<?php

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\Report;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chart-ready aggregates for the admin "Performance Overview" section.
 *
 * Every series is produced by a grouped SQL query — the frontend receives
 * plain arrays and renders them with the shared chart components, so no
 * number displayed on a chart is computed in the browser.
 *
 * Metric definitions (documented so the UI tooltips stay truthful):
 *  - task_completion_pct   = completed assignments / total assignments
 *  - report_coverage_pct   = distinct tasks carrying at least one report
 *                            / total tasks in the department
 *  - kpi_achievement_pct   = avg(employee_performance_records
 *                            .target_achievement_rate) for the department
 *  - ai_validation_score   = avg(ai_validation_results.overall_score) * 100
 *                            (stored as a 0..1 fraction, clamped to 0..100)
 */
class AdminPerformanceOverviewService
{
    /**
     * Sections A + B + C + D in a single payload so the dashboard renders in
     * one pass.
     *
     * @return array<string, mixed>
     */
    public function overview(?int $year = null, ?int $departmentId = null): array
    {
        $year ??= (int) now()->year;

        return [
            'year' => $year,
            'employee_progress' => $this->employeeProgress($departmentId),
            'department_performance' => $this->departmentPerformance($departmentId),
            'monthly_progress' => $this->monthlyProgress($year, $departmentId),
            'report_status' => $this->reportStatus(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Section A — Overall Employee Progress (completed / in progress /
     * pending / late) as counts plus a percentage split.
     *
     * @return array<string, mixed>
     */
    public function employeeProgress(?int $departmentId = null): array
    {
        $base = TaskAssignment::query()
            ->when($departmentId !== null, fn (Builder $q) => $q->whereHas(
                'employee',
                fn (Builder $e) => $e->where('department_id', $departmentId),
            ));

        $row = (clone $base)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status in ("completed","approved") then 1 else 0 end) as completed')
            ->selectRaw('sum(case when status in ("in_progress","submitted") then 1 else 0 end) as in_progress')
            ->selectRaw('sum(case when status in ("assigned","accepted","not_started") then 1 else 0 end) as pending')
            ->selectRaw('sum(case when status in ("completed","approved") and due_date is not null and completed_at is not null and completed_at > due_date then 1 else 0 end) as late')
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'total' => $total,
            'items' => [
                ['key' => 'completed', 'label' => 'Completed', 'value' => (int) ($row->completed ?? 0), 'percent' => $this->percent((int) ($row->completed ?? 0), $total)],
                ['key' => 'in_progress', 'label' => 'In Progress', 'value' => (int) ($row->in_progress ?? 0), 'percent' => $this->percent((int) ($row->in_progress ?? 0), $total)],
                ['key' => 'pending', 'label' => 'Pending', 'value' => (int) ($row->pending ?? 0), 'percent' => $this->percent((int) ($row->pending ?? 0), $total)],
                ['key' => 'late', 'label' => 'Late', 'value' => (int) ($row->late ?? 0), 'percent' => $this->percent((int) ($row->late ?? 0), $total)],
            ],
        ];
    }

    /**
     * Section B — Department comparison across three independent measures.
     *
     * Deliberately NOT ranked: rows stay in alphabetical order so the UI can
     * show a neutral side-by-side comparison.
     *
     * @return list<array<string, mixed>>
     */
    public function departmentPerformance(?int $departmentId = null): array
    {
        $taskRows = DB::table('departments')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.department_id', '=', 'departments.id')
                    ->whereNull('employees.deleted_at');
            })
            ->leftJoin('task_assignments', function ($join) {
                $join->on('task_assignments.employee_id', '=', 'employees.id')
                    ->whereNull('task_assignments.deleted_at');
            })
            ->whereNull('departments.deleted_at')
            ->when($departmentId !== null, fn ($q) => $q->where('departments.id', $departmentId))
            ->groupBy('departments.id', 'departments.name')
            ->select('departments.id', 'departments.name')
            ->selectRaw('count(task_assignments.id) as tasks_total')
            ->selectRaw('sum(case when task_assignments.status in ("completed","approved") then 1 else 0 end) as tasks_completed')
            ->selectRaw('sum(case when task_assignments.status not in ("completed","approved") and coalesce(task_assignments.due_date, (select tasks.due_date from tasks where tasks.id = task_assignments.task_id)) is not null and coalesce(task_assignments.due_date, (select tasks.due_date from tasks where tasks.id = task_assignments.task_id)) < curdate() then 1 else 0 end) as tasks_overdue')
            ->get()
            ->keyBy('id');

        $reportRows = DB::table('departments')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.department_id', '=', 'departments.id')
                    ->whereNull('employees.deleted_at');
            })
            ->leftJoin('task_assignments', function ($join) {
                $join->on('task_assignments.employee_id', '=', 'employees.id')
                    ->whereNull('task_assignments.deleted_at');
            })
            ->leftJoin('reports', function ($join) {
                $join->on('reports.task_id', '=', 'task_assignments.task_id')
                    ->whereNull('reports.deleted_at');
            })
            ->whereNull('departments.deleted_at')
            ->when($departmentId !== null, fn ($q) => $q->where('departments.id', $departmentId))
            ->groupBy('departments.id')
            ->select('departments.id')
            ->selectRaw('count(distinct case when reports.id is not null then task_assignments.task_id end) as tasks_with_reports')
            ->pluck('tasks_with_reports', 'id');

        $kpiRows = DB::table('employees')
            ->join('employee_performance_records', 'employee_performance_records.employee_id', '=', 'employees.id')
            ->whereNull('employees.deleted_at')
            ->when($departmentId !== null, fn ($q) => $q->where('employees.department_id', $departmentId))
            ->whereNotNull('employee_performance_records.target_achievement_rate')
            ->groupBy('employees.department_id')
            ->select('employees.department_id')
            ->selectRaw('avg(employee_performance_records.target_achievement_rate) as avg_achievement')
            ->pluck('avg_achievement', 'department_id');

        $aiRows = DB::table('ai_validation_results')
            ->join('reports', 'reports.id', '=', 'ai_validation_results.report_id')
            ->join('employees', 'employees.id', '=', 'reports.employee_id')
            ->whereNull('reports.deleted_at')
            ->whereNull('employees.deleted_at')
            ->when($departmentId !== null, fn ($q) => $q->where('employees.department_id', $departmentId))
            ->whereNotNull('ai_validation_results.overall_score')
            ->groupBy('employees.department_id')
            ->select('employees.department_id')
            ->selectRaw('avg(ai_validation_results.overall_score) as avg_score')
            ->pluck('avg_score', 'department_id');

        return Department::query()
            ->when($departmentId !== null, fn (Builder $q) => $q->where('id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Department $department) use ($taskRows, $reportRows, $kpiRows, $aiRows) {
                $id = $department->id;
                $taskRow = $taskRows->get($id);
                $tasksTotal = (int) ($taskRow->tasks_total ?? 0);
                $tasksCompleted = (int) ($taskRow->tasks_completed ?? 0);
                $withReports = (int) ($reportRows->get($id) ?? 0);
                $kpi = $kpiRows->get($id);
                $ai = $aiRows->get($id);

                return [
                    'id' => $id,
                    'name' => $department->name,
                    'task_completion_pct' => $this->percent($tasksCompleted, $tasksTotal),
                    'report_coverage_pct' => $this->percent($withReports, $tasksTotal),
                    'kpi_achievement_pct' => $kpi === null ? null : round((float) $kpi, 2),
                    'ai_validation_score' => $ai === null
                        ? null
                        : round(max(0.0, min(1.0, (float) $ai)) * 100, 2),
                    'late_tasks' => (int) ($taskRow->tasks_overdue ?? 0),
                    'tasks_total' => $tasksTotal,
                ];
            })
            ->all();
    }

    /**
     * Section C — Monthly progress for January..December of the given year.
     *
     * All twelve months are always returned (zero-filled) so the chart keeps a
     * stable x-axis instead of collapsing the months that have no data.
     *
     * @return list<array<string, mixed>>
     */
    public function monthlyProgress(int $year, ?int $departmentId = null): array
    {
        $assignmentRows = TaskAssignment::query()
            ->when($departmentId !== null, fn (Builder $q) => $q->whereHas(
                'employee',
                fn (Builder $e) => $e->where('department_id', $departmentId),
            ))
            ->whereYear('assigned_at', $year)
            ->selectRaw('month(assigned_at) as month')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status in ("completed","approved") then 1 else 0 end) as completed')
            ->selectRaw('sum(case when status in ("completed","approved") and due_date is not null and completed_at is not null and completed_at > due_date then 1 else 0 end) as late')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $reportRows = Report::query()
            ->when($departmentId !== null, fn (Builder $q) => $q->whereHas(
                'employee',
                fn (Builder $e) => $e->where('department_id', $departmentId),
            ))
            ->whereYear('submitted_at', $year)
            ->selectRaw('month(submitted_at) as month')
            ->selectRaw('count(*) as reports')
            ->groupBy('month')
            ->pluck('reports', 'month');

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $row = $assignmentRows->get($month);
            $total = (int) ($row->total ?? 0);
            $completed = (int) ($row->completed ?? 0);

            $months[] = [
                'month' => $month,
                'label' => Carbon::create($year, $month, 1)->format('M'),
                'key' => sprintf('%d-%02d', $year, $month),
                'total' => $total,
                'completed' => $completed,
                'late' => (int) ($row->late ?? 0),
                'reports' => (int) ($reportRows->get($month) ?? 0),
                'completion_rate' => $this->percent($completed, $total),
            ];
        }

        return $months;
    }

    /**
     * Section D — Report status split.
     *
     * @return array<string, mixed>
     */
    public function reportStatus(): array
    {
        $row = Report::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status in ("submitted","resubmitted","processing","under_review","approved") then 1 else 0 end) as submitted')
            ->selectRaw('sum(case when status in ("draft","cancelled") then 1 else 0 end) as pending')
            ->selectRaw('sum(case when status = "approved" then 1 else 0 end) as approved')
            ->selectRaw('sum(case when status = "revision_required" then 1 else 0 end) as needs_revision')
            ->selectRaw('sum(case when is_late = 1 then 1 else 0 end) as late')
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'total' => $total,
            'items' => [
                ['key' => 'submitted', 'label' => 'Submitted', 'value' => (int) ($row->submitted ?? 0), 'percent' => $this->percent((int) ($row->submitted ?? 0), $total)],
                ['key' => 'pending', 'label' => 'Pending', 'value' => (int) ($row->pending ?? 0), 'percent' => $this->percent((int) ($row->pending ?? 0), $total)],
                ['key' => 'approved', 'label' => 'Approved', 'value' => (int) ($row->approved ?? 0), 'percent' => $this->percent((int) ($row->approved ?? 0), $total)],
                ['key' => 'needs_revision', 'label' => 'Needs Revision', 'value' => (int) ($row->needs_revision ?? 0), 'percent' => $this->percent((int) ($row->needs_revision ?? 0), $total)],
                ['key' => 'late', 'label' => 'Late', 'value' => (int) ($row->late ?? 0), 'percent' => $this->percent((int) ($row->late ?? 0), $total)],
            ],
        ];
    }

    private function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
    }
}
