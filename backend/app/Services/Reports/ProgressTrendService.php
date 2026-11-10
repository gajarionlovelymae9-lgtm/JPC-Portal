<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Progress Trend Report: month-over-month assignment completions and a
 * top-performer leaderboard (daily / weekly / monthly scopes).
 *
 * Trend arrays are computed over the assignment table so the numbers track
 * actual employee progress rather than task counts.
 */
class ProgressTrendService
{
    use ComputesReportMetrics;

    /**
     * @return array<string, mixed>
     */
    public function trend(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $months = min((int) config('reports.trend_months', 6), (int) config('reports.trend_months_max', 12));
        $anchor = $bag->year !== null && $bag->month !== null
            ? Carbon::create($bag->year, $bag->month, 1, 0, 0, 0)
            : now();

        $labels = [];
        $total = [];
        $completed = [];
        $pending = [];
        $overdue = [];
        $rates = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $start = Carbon::create($anchor->year, $anchor->month, 1, 0, 0, 0)->subMonthsNoOverflow($offset);
            $labels[] = $start->format('Y-m');

            $baseForMonth = $this->assignmentQuery($bag, $departmentId);
            $createdNow = $baseForMonth->clone()
                ->whereYear('assigned_at', $start->year)
                ->whereMonth('assigned_at', $start->month)
                ->count();

            $completedNow = $baseForMonth->clone()
                ->completed()
                ->whereYear('completed_at', $start->year)
                ->whereMonth('completed_at', $start->month)
                ->count();

            $overdueNow = $baseForMonth->clone()
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->whereNull('completed_at')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereYear('due_date', $start->year)
                ->whereMonth('due_date', $start->month)
                ->count();

            $total[] = $createdNow;
            $completed[] = $completedNow;
            $pending[] = max($createdNow - $completedNow, 0);
            $overdue[] = $overdueNow;
            $rates[] = $this->rate($completedNow, $createdNow);
        }

        $delta = count($rates) >= 2 ? round($rates[count($rates) - 1] - $rates[count($rates) - 2], 2) : 0.0;

        return [
            'period' => $anchor->format('Y-m'),
            'labels' => $labels,
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'completion_rates' => $rates,
            'month_over_month_pp' => $delta,
        ];
    }

    /**
     * Top performers by completed assignments in the selected scope.
     *
     * @return array<string, mixed>
     */
    public function topPerformers(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $scope = $bag->scope ?? 'monthly';
        $limit = min($bag->limit ?? (int) config('reports.top_performers_limit', 5), (int) config('reports.top_performers_limit_max', 10));

        [$from, $to, $label] = $this->scopeWindow($scope, $bag);

        $employees = Employee::query()
            ->active()
            ->with('department')
            ->when($departmentId !== null, fn (Builder $q) => $q->byDepartment($departmentId), fn (Builder $q) => $bag->departmentId !== null ? $q->byDepartment($bag->departmentId) : $q)
            ->get()
            ->map(function (Employee $employee) use ($from, $to) {
                $completed = TaskAssignment::query()
                    ->forEmployee($employee->id)
                    ->completed()
                    ->whereDate('completed_at', '>=', $from->toDateString())
                    ->whereDate('completed_at', '<=', $to->toDateString())
                    ->count();

                $total = TaskAssignment::query()->forEmployee($employee->id)->count();

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->fullName,
                    'department_id' => $employee->department_id,
                    'department_name' => $employee->department?->name,
                    'completed_tasks' => $completed,
                    'completion_rate' => $this->rate($completed, $total),
                ];
            })
            ->sortBy([
                ['completed_tasks', 'desc'],
                ['completion_rate', 'desc'],
                ['employee_name', 'asc'],
            ])
            ->slice(0, $limit)
            ->values()
            ->map(fn (array $item, int $index) => array_merge(['rank' => $index + 1], $item))
            ->all();

        return [
            'scope' => $scope,
            'period' => $label,
            'limit' => $limit,
            'performers' => $employees,
        ];
    }

    private function assignmentQuery(ReportFilterBag $bag, ?int $departmentId): Builder
    {
        $query = TaskAssignment::query();

        // Department scoping lives on the task, not the assignment.
        if ($departmentId !== null || $bag->departmentId !== null) {
            $dept = $departmentId ?? $bag->departmentId;
            $query->whereHas('task', fn (Builder $t) => $t->byDepartment((int) $dept));
        }

        if ($bag->academicYearId !== null) {
            $query->whereHas('task.annualPlan', fn (Builder $t) => $t->where('academic_year_id', $bag->academicYearId));
        }

        if ($bag->employeeId !== null) {
            $query->where('employee_id', $bag->employeeId);
        }

        if ($bag->status !== null) {
            $query->where('status', $bag->status);
        }

        if ($bag->priority !== null) {
            $query->whereHas('task', fn (Builder $t) => $t->where('priority', $bag->priority));
        }

        return $query;
    }

    /**
     * @return array{Carbon, Carbon, string}
     */
    private function scopeWindow(string $scope, ReportFilterBag $bag): array
    {
        if ($scope === 'daily') {
            $today = now()->startOfDay();

            return [$today, $today, $today->toDateString()];
        }

        if ($scope === 'weekly') {
            $from = now()->subDays(6)->startOfDay();
            $to = now()->endOfDay();

            return [$from, $to, $from->toDateString().' to '.$to->toDateString()];
        }

        $year = $bag->year ?? now()->year;
        $month = $bag->month ?? now()->month;
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        return [$from, $to, $from->format('Y-m')];
    }
}
