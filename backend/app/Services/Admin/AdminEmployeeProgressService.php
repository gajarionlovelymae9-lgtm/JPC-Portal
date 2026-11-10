<?php

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Institution-wide employee progress table (admin section 8).
 *
 * Every column is a real aggregate:
 *  assigned / completed / pending / late  -> task_assignments
 *  reports                               -> reports
 *  kpi_pct                               -> employee_performance_records
 *                                           .target_achievement_rate
 *  ai_score                              -> ai_validation_results.overall_score
 *                                           for that employee's reports (0..100)
 *
 * `ai_score` is null when the employee has no scored report — it is never
 * replaced with 0, so the UI can distinguish "no data" from "scored zero".
 */
class AdminEmployeeProgressService
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ON_TRACK = 'on_track';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    public const STATUS_AT_RISK = 'at_risk';

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        $employees = Employee::query()
            ->with('department:id,name')
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->where('department_id', (int) $filters['department_id']))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_number', 'like', $term));
            })
            ->orderBy('last_name')
            ->paginate($perPage)
            ->through(fn (Employee $employee) => $this->row($employee, $filters));

        return $employees;
    }

    /**
     * Full detail for a single employee, used by the progress detail panel.
     *
     * @return array<string, mixed>
     */
    public function detail(int $employeeId): array
    {
        $employee = Employee::query()
            ->with(['department:id,name', 'position'])
            ->findOrFail($employeeId);

        $row = $this->row($employee, []);

        $row['position'] = $employee->position?->title;
        $row['email'] = $employee->email;
        $row['employment_status'] = $employee->employment_status;

        $row['tasks'] = DB::table('task_assignments')
            ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->whereNull('task_assignments.deleted_at')
            ->where('task_assignments.employee_id', $employeeId)
            ->select([
                'task_assignments.id as assignment_id',
                'tasks.title as task_name',
                'tasks.task_code',
                'tasks.priority',
                'task_assignments.status',
                'task_assignments.progress_percentage as progress',
                'task_assignments.due_date as assignment_due_date',
                'tasks.due_date as task_due_date',
            ])
            ->orderByDesc('task_assignments.id')
            ->limit(50)
            ->get()
            ->map(fn ($task) => [
                'assignment_id' => $task->assignment_id,
                'task_name' => $task->task_name,
                'task_code' => $task->task_code,
                'priority' => $task->priority,
                'status' => $task->status,
                'progress' => (float) $task->progress,
                'due_date' => $task->assignment_due_date ?? $task->task_due_date,
            ])
            ->all();

        $row['reports'] = DB::table('reports')
            ->leftJoin('ai_validation_results', 'ai_validation_results.report_id', '=', 'reports.id')
            ->whereNull('reports.deleted_at')
            ->where('reports.employee_id', $employeeId)
            ->select([
                'reports.id',
                'reports.title',
                'reports.status',
                'reports.deadline',
                'reports.submitted_at',
                'reports.is_late',
                DB::raw('max(ai_validation_results.overall_score) as best_score'),
            ])
            ->groupBy(
                'reports.id',
                'reports.title',
                'reports.status',
                'reports.deadline',
                'reports.submitted_at',
                'reports.is_late',
            )
            ->orderByDesc('reports.id')
            ->limit(50)
            ->get()
            ->map(fn ($report) => [
                'id' => $report->id,
                'title' => $report->title,
                'status' => $report->status,
                'deadline' => $report->deadline,
                'submitted_at' => $report->submitted_at,
                'is_late' => (bool) $report->is_late,
                'ai_score' => $report->best_score === null
                    ? null
                    : round((float) $report->best_score * 100, 2),
            ])
            ->all();

        $row['monthly_achievements'] = DB::table('monthly_achievements')
            ->where('employee_id', $employeeId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get([
                'id', 'month', 'year', 'completion_rate', 'on_time_rate',
                'approved_reports', 'completed_tasks', 'overall_score',
                'rank', 'is_employee_of_month',
            ])
            ->map(fn ($achievement) => [
                'id' => $achievement->id,
                'month' => $achievement->month,
                'year' => $achievement->year,
                'completion_rate' => (float) $achievement->completion_rate,
                'on_time_rate' => (float) $achievement->on_time_rate,
                'approved_reports' => (int) $achievement->approved_reports,
                'completed_tasks' => (int) $achievement->completed_tasks,
                'overall_score' => (float) $achievement->overall_score,
                'is_employee_of_month' => (bool) $achievement->is_employee_of_month,
            ])
            ->all();

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'statuses' => [
                ['value' => self::STATUS_COMPLETED, 'label' => 'Completed'],
                ['value' => self::STATUS_ON_TRACK, 'label' => 'On Track'],
                ['value' => self::STATUS_NEEDS_ATTENTION, 'label' => 'Needs Attention'],
                ['value' => self::STATUS_AT_RISK, 'label' => 'At Risk'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $stats = DB::table('employees')
            ->leftJoin('task_assignments', function ($join) {
                $join->on('task_assignments.employee_id', '=', 'employees.id')
                    ->whereNull('task_assignments.deleted_at');
            })
            ->whereNull('employees.deleted_at')
            ->selectRaw('count(distinct employees.id) as employees')
            ->selectRaw('count(task_assignments.id) as assigned')
            ->selectRaw('sum(case when task_assignments.status in ("completed","approved") then 1 else 0 end) as completed')
            ->selectRaw('sum(case when task_assignments.status in ("assigned","accepted") then 1 else 0 end) as pending')
            ->selectRaw('sum(case when task_assignments.status not in ("completed","approved","cancelled") and coalesce(task_assignments.due_date, (select tasks.due_date from tasks where tasks.id = task_assignments.task_id)) is not null and coalesce(task_assignments.due_date, (select tasks.due_date from tasks where tasks.id = task_assignments.task_id)) < curdate() then 1 else 0 end) as late')
            ->first();

        $totalEmployees = (int) ($stats->employees ?? 0);
        $assigned = (int) ($stats->assigned ?? 0);
        $completed = (int) ($stats->completed ?? 0);
        $late = (int) ($stats->late ?? 0);

        return [
            'total_employees' => $totalEmployees,
            'assigned_tasks' => $assigned,
            'completed_tasks' => $completed,
            'pending_tasks' => (int) ($stats->pending ?? 0),
            'late_tasks' => $late,
            'completion_rate' => $assigned > 0 ? round(($completed / $assigned) * 100, 2) : 0.0,
            'late_rate' => $assigned > 0 ? round(($late / $assigned) * 100, 2) : 0.0,
        ];
    }

    /**
     * Build one progress row from pre-aggregated scalars.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function row(Employee $employee, array $filters): array
    {
        $id = $employee->id;

        $task = DB::table('task_assignments')
            ->whereNull('task_assignments.deleted_at')
            ->where('employee_id', $id)
            ->selectRaw('count(*) as assigned')
            ->selectRaw('sum(case when status in ("completed","approved") then 1 else 0 end) as completed')
            ->selectRaw('sum(case when status in ("assigned","accepted","not_started") then 1 else 0 end) as pending')
            ->selectRaw('sum(case when status in ("in_progress","submitted") then 1 else 0 end) as in_progress')
            ->selectRaw('sum(case when status in ("completed","approved") and due_date is not null and completed_at is not null and completed_at > due_date then 1 else 0 end) as completed_late')
            ->selectRaw('sum(case when status not in ("completed","approved","cancelled") and coalesce(due_date, (select tasks.due_date from tasks where tasks.id = task_assignments.task_id)) is not null and coalesce(due_date, (select tasks.due_date from tasks where tasks.id = task_assignments.task_id)) < curdate() then 1 else 0 end) as overdue')
            ->first();

        $assigned = (int) ($task->assigned ?? 0);
        $completed = (int) ($task->completed ?? 0);
        $pending = (int) ($task->pending ?? 0);
        $late = (int) ($task->overdue ?? 0) + (int) ($task->completed_late ?? 0);

        $reports = DB::table('reports')
            ->whereNull('reports.deleted_at')
            ->where('employee_id', $id)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = "approved" then 1 else 0 end) as approved')
            ->selectRaw('sum(case when is_late = 1 then 1 else 0 end) as late')
            ->selectRaw('sum(case when status in ("draft") then 1 else 0 end) as not_submitted')
            ->first();

        $kpi = DB::table('employee_performance_records')
            ->where('employee_id', $id)
            ->whereNotNull('target_achievement_rate')
            ->selectRaw('avg(target_achievement_rate) as avg_achievement')
            ->first();

        $ai = DB::table('ai_validation_results')
            ->join('reports', 'reports.id', '=', 'ai_validation_results.report_id')
            ->whereNull('reports.deleted_at')
            ->where('reports.employee_id', $id)
            ->whereNotNull('ai_validation_results.overall_score')
            ->selectRaw('avg(ai_validation_results.overall_score) as avg_score')
            ->first();

        $completionRate = $assigned > 0 ? round(($completed / $assigned) * 100, 2) : null;
        $kpiPct = $kpi->avg_achievement === null ? null : round((float) $kpi->avg_achievement, 2);
        $aiScore = $ai->avg_score === null ? null : round((float) $ai->avg_score * 100, 2);

        // Overall progress blends the two real measures that exist for the
        // employee. A missing KPI record simply contributes nothing rather
        // than dragging the score down with a fake zero.
        $components = array_values(array_filter([
            $completionRate,
            $kpiPct,
        ], fn ($value) => $value !== null));
        $overall = $components === [] ? null : round(array_sum($components) / count($components), 2);

        return [
            'employee_id' => $id,
            'name' => $employee->full_name,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'employee_number' => $employee->employee_number,
            'department_id' => $employee->department_id,
            'department' => $employee->department?->name,
            'employment_status' => $employee->employment_status,
            'assigned_tasks' => $assigned,
            'completed_tasks' => $completed,
            'pending_tasks' => $pending,
            'in_progress_tasks' => (int) ($task->in_progress ?? 0),
            'late_tasks' => $late,
            'reports_total' => (int) ($reports->total ?? 0),
            'reports_approved' => (int) ($reports->approved ?? 0),
            'reports_late' => (int) ($reports->late ?? 0),
            'reports_not_submitted' => (int) ($reports->not_submitted ?? 0),
            'kpi_pct' => $kpiPct,
            'ai_score' => $aiScore,
            'completion_rate' => $completionRate,
            'overall_progress' => $overall,
            'status' => $this->status($overall, $late, $assigned),
            'status_label' => $this->statusLabel($overall, $late, $assigned),
        ];
    }

    /**
     * Derive the health status from real evidence.
     */
    private function status(?float $overall, int $late, int $assigned): string
    {
        if ($assigned > 0 && $late / $assigned >= 0.3) {
            return self::STATUS_AT_RISK;
        }

        if ($overall === null) {
            return self::STATUS_NEEDS_ATTENTION;
        }

        return match (true) {
            $overall >= 100 => self::STATUS_COMPLETED,
            $overall >= 75 => self::STATUS_ON_TRACK,
            default => self::STATUS_NEEDS_ATTENTION,
        };
    }

    private function statusLabel(?float $overall, int $late, int $assigned): string
    {
        return match ($this->status($overall, $late, $assigned)) {
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_ON_TRACK => 'On Track',
            self::STATUS_AT_RISK => 'At Risk',
            default => 'Needs Attention',
        };
    }
}
