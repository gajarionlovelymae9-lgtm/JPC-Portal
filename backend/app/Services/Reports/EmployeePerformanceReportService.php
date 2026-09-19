<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
            ->get()
            ->map(fn (Employee $employee) => $this->row($employee, $bag))
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
        $row = $this->row($employee, $bag);

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
     * @return array<string, mixed>
     */
    private function row(Employee $employee, ReportFilterBag $bag): array
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

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name,
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $base->clone()->pending()->count(),
            'overdue_tasks' => $base->clone()->overdue()->count(),
            'on_time' => $split['on_time'],
            'late' => $split['late'],
            'completion_rate' => $this->rate($completed, $total),
            'on_time_rate' => $this->rate($split['on_time'], $completed),
            'next_due_date' => $nextDue?->toDateString(),
        ];
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