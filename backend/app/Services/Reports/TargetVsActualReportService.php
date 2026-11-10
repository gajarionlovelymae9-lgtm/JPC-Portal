<?php

namespace App\Services\Reports;

use App\Models\AcademicYear;
use App\Models\EmployeeTarget;
use App\Models\User;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Target vs Actual Report: how many employee targets have been achieved
 * (at least one associated completed task) against the count in scope.
 */
class TargetVsActualReportService
{
    use ComputesReportMetrics;

    /**
     * @return array<string, mixed>
     */
    public function report(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $year = $bag->year ?? now()->year;
        $month = $bag->month ?? now()->month;

        $key = 'reports:target-vs-actual:'.($departmentId ?? 'all').':'.$bag->signature();
        $summary = Cache::remember($key, (int) config('reports.cache_ttl', 60), function () use ($bag, $departmentId) {
            $targets = EmployeeTarget::query()
                ->with([
                    'employee',
                    'tasks' => fn ($q) => $q->completed()
                        ->when($bag->fromDate !== null, fn ($b) => $b->whereDate('completed_at', '>=', $bag->fromDate))
                        ->when($bag->toDate !== null, fn ($b) => $b->whereDate('completed_at', '<=', $bag->toDate))
                        ->when($bag->month !== null && $bag->year !== null, fn ($b) => $b->whereYear('completed_at', $bag->year)->whereMonth('completed_at', $bag->month)),
                ])
                ->when($departmentId !== null, fn (Builder $q) => $q->whereHas('employee', fn (Builder $e) => $e->byDepartment($departmentId)))
                ->when($bag->academicYearId !== null, fn (Builder $q) => $q->whereHas(
                    'annualPlan',
                    fn (Builder $b) => $b->where('academic_year_id', $bag->academicYearId)
                ))
                ->get();

            $target = $targets->count();
            $achieved = $targets->filter(fn (EmployeeTarget $t) => $t->tasks->isNotEmpty())->count();

            return [
                'target' => $target,
                'achieved' => $achieved,
                'remaining' => max($target - $achieved, 0),
                'achievement_rate' => $this->rate($achieved, $target),
            ];
        });

        return [
            'period' => $this->periodLabel($bag),
            'year' => (int) $year,
            'month' => (int) $month,
        ] + $summary;
    }

    private function periodLabel(ReportFilterBag $bag): string
    {
        if ($bag->academicYearId !== null) {
            $year = AcademicYear::query()->find((int) $bag->academicYearId);

            return $year?->name ?? 'Academic year';
        }

        if ($bag->month !== null && $bag->year !== null) {
            return Carbon::create($bag->year, $bag->month, 1)->format('Y-m');
        }

        if ($bag->fromDate !== null && $bag->toDate !== null) {
            return $bag->fromDate.' to '.$bag->toDate;
        }

        return 'All time';
    }
}
