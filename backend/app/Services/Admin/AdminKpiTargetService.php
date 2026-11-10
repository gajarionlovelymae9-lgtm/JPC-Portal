<?php

namespace App\Services\Admin;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\EmployeeTarget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Admin "KPI & Targets" data source.
 *
 * IMPORTANT — how "actual" and "achievement" are derived:
 * `employee_targets` has NO `actual_value` column (only `target_value`), so
 * this service never fabricates one. The real, countable actual for a target
 * is the number of its linked tasks that reached a completed state, and
 * achievement is that completed count over the total linked task count.
 * When a target has no linked tasks the achievement is reported as null
 * rather than 0, so the UI can distinguish "no data" from "zero achieved".
 *
 * Derived status is reported alongside the stored status because the stored
 * value is only ever draft/active/completed and cannot express "missed".
 */
class AdminKpiTargetService
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_MISSED = 'missed';

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DRAFT = 'draft';

    /**
     * Aggregate counters shown above the table.
     *
     * The status filter is intentionally dropped here: the cards must always
     * show the full distribution for the current scope, otherwise selecting
     * "Missed" would hide every other bucket and make the panel misleading.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        unset($filters['status'], $filters['per_page'], $filters['page']);

        $rows = $this->buildQuery($filters)
            ->with(['employee.department:id,name', 'tasks:id,employee_target_id,status'])
            ->get()
            ->map(fn (EmployeeTarget $target) => $this->decorate($target));

        $completed = $rows->where('derived_status', self::STATUS_COMPLETED)->count();
        $inProgress = $rows->whereIn('derived_status', [self::STATUS_IN_PROGRESS, self::STATUS_ACTIVE])->count();
        $missed = $rows->where('derived_status', self::STATUS_MISSED)->count();

        return [
            'total_kpis' => $rows->count(),
            'completed_targets' => $completed,
            'in_progress_targets' => $inProgress,
            'missed_targets' => $missed,
            'not_started_targets' => $rows->where('derived_status', self::STATUS_NOT_STARTED)->count(),
            'avg_kpi_achievement' => $this->average($rows->pluck('achievement_pct')->filter(fn ($v) => $v !== null)),
        ];
    }

    /**
     * A single target in the decorated row shape, for show/edit endpoints.
     * Returns null when the id is unknown so the caller can answer 404.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        $target = EmployeeTarget::query()
            ->with([
                'employee:id,first_name,last_name,employee_number,department_id',
                'employee.department:id,name',
                'tasks:id,employee_target_id,status',
                'annualPlan:id,academic_year_id,department_id,title',
                'annualPlan.academicYear:id,name',
                'annualPlan.department:id,name',
            ])
            ->find($id);

        return $target ? $this->decorate($target) : null;
    }

    /**
     * Paginated KPI/target rows.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $this->buildQuery($filters)
            ->with([
                'employee:id,first_name,last_name,employee_number,department_id',
                'employee.department:id,name',
                'tasks:id,employee_target_id,status',
                'annualPlan:id,academic_year_id',
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (EmployeeTarget $target) => $this->decorate($target));
    }

    /**
     * Filter options for the toolbar.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name])->all(),
            'academic_years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status'])
                ->map(fn (AcademicYear $year) => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status])->all(),
            'statuses' => [
                ['value' => self::STATUS_COMPLETED, 'label' => 'Completed'],
                ['value' => self::STATUS_IN_PROGRESS, 'label' => 'In Progress'],
                ['value' => self::STATUS_MISSED, 'label' => 'Missed'],
                ['value' => self::STATUS_NOT_STARTED, 'label' => 'Not Started'],
            ],
        ];
    }

    /**
     * Base query with every toolbar filter applied.
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildQuery(array $filters): Builder
    {
        $query = EmployeeTarget::query();

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->whereHas('employee', fn (Builder $e) => $e->where('department_id', (int) $filters['department_id']));
        }

        if (! empty($filters['academic_year_id'])) {
            $query->whereHas('annualPlan', fn (Builder $p) => $p->where('academic_year_id', (int) $filters['academic_year_id']));
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('end_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('end_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        // The stored status can only be draft/active/completed. The richer
        // derived statuses depend on linked task completion, so they are
        // expressed with whereHas/whereDoesntHave to stay correct under
        // pagination (filtering after hydration would break page counts).
        $status = $filters['status'] ?? null;

        if ($status === self::STATUS_DRAFT || $status === self::STATUS_ACTIVE) {
            $query->where('status', $status);
        } elseif ($status === self::STATUS_COMPLETED) {
            $query->where(function (Builder $inner) {
                $inner->where('status', self::STATUS_COMPLETED)
                    ->orWhere(function (Builder $all) {
                        $all->whereHas('tasks')
                            ->whereDoesntHave('tasks', fn (Builder $t) => $this->notFinished($t));
                    });
            });
        } elseif ($status === self::STATUS_IN_PROGRESS) {
            $query->where('status', '!=', self::STATUS_COMPLETED)
                ->whereHas('tasks')
                ->whereHas('tasks', fn (Builder $t) => $this->notFinished($t));
        } elseif ($status === self::STATUS_MISSED) {
            $query->where('status', '!=', self::STATUS_COMPLETED)
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', now()->toDateString())
                ->whereHas('tasks')
                ->whereHas('tasks', fn (Builder $t) => $this->notFinished($t));
        } elseif ($status === self::STATUS_NOT_STARTED) {
            $query->where('status', '!=', self::STATUS_COMPLETED)
                ->whereDoesntHave('tasks');
        }

        return $query;
    }

    /**
     * Constrain a task relation to work that is not yet finished.
     *
     * `tasks` has no `approved` state — a task is finished only once its status
     * is `completed` (see App\Models\Task::scopeCompleted).
     */
    private function notFinished(Builder $tasks): void
    {
        $tasks->where('status', '!=', self::STATUS_COMPLETED);
    }

    /**
     * Flatten a target into the dashboard row shape.
     *
     * @return array<string, mixed>
     */
    private function decorate(EmployeeTarget $target): array
    {
        $totalTasks = $target->tasks?->count() ?? 0;
        $completedTasks = $target->tasks?->where('status', '=', self::STATUS_COMPLETED)->count() ?? 0;

        $achievement = $totalTasks > 0
            ? round(($completedTasks / $totalTasks) * 100, 2)
            : null;

        $employee = $target->employee;

        return [
            'id' => $target->id,
            'annual_plan_id' => $target->annual_plan_id,
            'employee_id' => $target->employee_id,
            'employee_name' => $employee?->full_name,
            'employee_number' => $employee?->employee_number,
            'department_id' => $employee?->department_id,
            'department_name' => $employee?->department?->name,
            'kpi' => $target->title,
            'description' => $target->description,
            'target' => $target->target_value === null ? null : (float) $target->target_value,
            'target_unit' => $target->target_unit,
            'actual' => $totalTasks > 0 ? $completedTasks : null,
            'tasks_total' => $totalTasks,
            'tasks_completed' => $completedTasks,
            'achievement_pct' => $achievement,
            'stored_status' => $target->status,
            'derived_status' => $this->deriveStatus($target, $totalTasks, $achievement),
            'period' => $this->period($target),
            'start_date' => $target->start_date?->toDateString(),
            'end_date' => $target->end_date?->toDateString(),
        ];
    }

    /**
     * Derive a meaningful status from stored state + real task evidence.
     */
    private function deriveStatus(EmployeeTarget $target, int $totalTasks, ?float $achievement): string
    {
        if ($totalTasks === 0) {
            return $target->status === self::STATUS_COMPLETED
                ? self::STATUS_COMPLETED
                : self::STATUS_NOT_STARTED;
        }

        if ($achievement !== null && $achievement >= 100) {
            return self::STATUS_COMPLETED;
        }

        $expired = $target->end_date !== null && $target->end_date->isPast();

        if ($expired) {
            return self::STATUS_MISSED;
        }

        return self::STATUS_IN_PROGRESS;
    }

    private function period(EmployeeTarget $target): string
    {
        if ($target->start_date === null && $target->end_date === null) {
            return 'No period set';
        }

        $start = $target->start_date?->format('M Y') ?? '—';
        $end = $target->end_date?->format('M Y') ?? '—';

        return $start.' – '.$end;
    }

    /**
     * @param  Collection<int, float|null>  $values
     */
    private function average($values): float
    {
        $clean = $values->filter(fn ($value) => $value !== null);

        return $clean->isEmpty() ? 0.0 : round((float) $clean->avg(), 2);
    }
}
