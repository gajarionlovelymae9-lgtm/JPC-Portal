<?php

namespace App\Services\Reports;

use App\Models\Task;
use App\Models\User;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Task Completion Report: organisation/department task snapshot plus a
 * month-over-month chart feed.
 *
 * The snapshot is computed live from the current task table — cancelled and
 * in-flight tasks are both accounted for so the numbers reconcile with the
 * management dashboard.
 */
class TaskCompletionReportService
{
    use ComputesReportMetrics;

    /**
     * @return array<string, mixed>
     */
    public function report(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $base = $this->taskQuery($bag, $departmentId);

        $total = $base->clone()->count();
        $completedQuery = $base->clone()->completed();
        $completed = $this->constrainCompleted($completedQuery, $bag)->count();

        $lateQuery = $base->clone()->completed()
            ->whereNotNull('due_date')
            ->whereColumn('completed_at', '>', 'due_date');
        $late = $this->constrainCompleted($lateQuery, $bag)->count();
        $onTime = max($completed - $late, 0);

        $hasPeriod = $bag->month !== null || $bag->fromDate !== null;

        return [
            'period' => $this->periodLabel($bag),
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $base->clone()->pending()->count(),
            'overdue_tasks' => $base->clone()->overdue()->count(),
            'submitted_tasks' => $base->clone()->where('status', 'submitted')->count(),
            'rejected_tasks' => $base->clone()->rejected()->count(),
            'cancelled_tasks' => $base->clone()->cancelled()->count(),
            'on_time' => $onTime,
            'late' => $late,
            'completion_rate' => $this->rate($completed, $total),
            // On-time rate is only meaningful for period-constrained snapshots;
            // without a period it describes the whole history.
            'on_time_rate' => $this->rate($onTime, $completed),
            'period_active' => $hasPeriod,
            'chart' => $this->chart($bag, $departmentId),
        ];
    }

    /**
     * Chart feed: last N calendar months with backend-ready datasets.
     *
     * `pending` = tasks created in the month that are still open, which keeps
     * `completed + pending` honest when the window has not fully elapsed.
     *
     * @return array<string, mixed>
     */
    public function chart(ReportFilterBag $bag, ?int $departmentId): array
    {
        $months = min((int) config('reports.trend_months', 6), (int) config('reports.trend_months_max', 12));
        $anchor = $this->anchor($bag);

        $labels = [];
        $completed = [];
        $pending = [];
        $rates = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $start = Carbon::create($anchor->year, $anchor->month, 1, 0, 0, 0)->subMonthsNoOverflow($offset);
            $label = $start->format('Y-m');

            $completedTotal = $this->taskQuery($bag, $departmentId)
                ->clone()
                ->completed()
                ->whereYear('completed_at', $start->year)
                ->whereMonth('completed_at', $start->month)
                ->count();

            $createdTotal = $this->taskQuery($bag, $departmentId)
                ->clone()
                ->whereYear('created_at', $start->year)
                ->whereMonth('created_at', $start->month)
                ->count();

            $completedInMonth = $completedTotal;
            $pendingNow = max($createdTotal - $completedInMonth, 0);

            $labels[] = $label;
            $completed[] = $completedInMonth;
            $pending[] = $pendingNow;
            $rates[] = $this->rate($completedInMonth, $createdTotal);
        }

        $delta = count($rates) >= 2 ? round($rates[count($rates) - 1] - $rates[count($rates) - 2], 2) : 0.0;

        return [
            'labels' => $labels,
            'completed' => $completed,
            'pending' => $pending,
            'completion_rates' => $rates,
            'month_over_month_pp' => $delta,
        ];
    }

    /**
     * Base task query: department scope + shared filters.
     */
    private function taskQuery(ReportFilterBag $bag, ?int $departmentId): Builder
    {
        $query = Task::query();

        if ($departmentId !== null) {
            $query->byDepartment($departmentId);
        }

        if ($bag->academicYearId !== null) {
            $query->whereHas('annualPlan', fn (Builder $q) => $q->where('academic_year_id', $bag->academicYearId));
        }

        if ($bag->employeeId !== null) {
            $query->whereHas('assignments', fn (Builder $q) => $q->where('employee_id', $bag->employeeId));
        }

        if ($bag->status !== null) {
            $query->where('status', $bag->status);
        }

        if ($bag->priority !== null) {
            $query->where('priority', $bag->priority);
        }

        if ($bag->fromDate !== null) {
            $query->whereDate('due_date', '>=', $bag->fromDate);
        }

        if ($bag->toDate !== null) {
            $query->whereDate('due_date', '<=', $bag->toDate);
        }

        return $query;
    }

    private function constrainCompleted(Builder $query, ReportFilterBag $bag): Builder
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

    private function anchor(ReportFilterBag $bag): Carbon
    {
        if ($bag->year !== null && $bag->month !== null) {
            return Carbon::create($bag->year, $bag->month, 1, 0, 0, 0);
        }

        return now();
    }

    private function periodLabel(ReportFilterBag $bag): string
    {
        if ($bag->month !== null && $bag->year !== null) {
            return Carbon::create($bag->year, $bag->month, 1)->format('Y-m');
        }

        if ($bag->fromDate !== null && $bag->toDate !== null) {
            return $bag->fromDate.' to '.$bag->toDate;
        }

        if ($bag->fromDate !== null) {
            return 'From '.$bag->fromDate;
        }

        if ($bag->toDate !== null) {
            return 'Until '.$bag->toDate;
        }

        if ($bag->academicYearId !== null) {
            return 'All time (academic year)';
        }

        return 'All time';
    }
}
