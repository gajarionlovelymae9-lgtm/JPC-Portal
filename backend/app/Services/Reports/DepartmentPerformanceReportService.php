<?php

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
            ->get()
            ->map(fn (Department $department) => $this->row($department, $bag))
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
        $row = $this->row($department, $bag);

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
     * @return array<string, mixed>
     */
    private function row(Department $department, ReportFilterBag $bag): array
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
            'next_due_date' => $nextDue?->toDateString(),
        ];
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