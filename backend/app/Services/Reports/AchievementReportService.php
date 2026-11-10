<?php

namespace App\Services\Reports;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\MonthlyAchievement;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Achievement Report Service.
 *
 * Computes the monthly achievement leaderboard (ranked, employee-of-the-month)
 * and each employee's personal achievement series. Ranked data is cached as a
 * per-scope safe aggregate and mirrored into `monthly_achievements` whenever
 * the period maps to a configured academic year — the official long-term
 * table already provisioned for this module.
 *
 * Awarding (badges) is NOT performed by this service; the API surfaces only
 * already-persisted badge links from `employee_badges`, so the UI can never
 * invent badge state.
 */
class AchievementReportService
{
    use ComputesReportMetrics;

    private const OVERALL_SCORE_WEIGHT = 0.6;

    private const ON_TIME_WEIGHT = 0.4;

    public function monthly(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $year = $bag->year ?? now()->year;
        $month = $bag->month ?? now()->month;

        $key = 'reports:achievements:'.($departmentId ?? 'all').':'.$bag->signature();
        $rows = Cache::remember($key, (int) config('reports.cache_ttl', 60), function () use ($bag, $departmentId, $year, $month) {
            $employees = Employee::query()
                ->active()
                ->with('department')
                ->when($departmentId !== null, fn (Builder $q) => $q->byDepartment($departmentId), fn (Builder $q) => $bag->departmentId !== null ? $q->byDepartment($bag->departmentId) : $q)
                ->get()
                ->map(fn (Employee $employee) => $this->monthlyRow($employee, $bag, (int) $year, (int) $month))
                ->sortBy([
                    ['overall_score', 'desc'],
                    ['completed_tasks', 'desc'],
                    ['employee_name', 'asc'],
                ])
                ->values()
                ->map(fn (array $item, int $index) => array_merge(
                    ['rank' => $index + 1, 'is_employee_of_month' => $index === 0],
                    $item,
                ))
                ->all();

            $this->mirrorToMonthlyAchievements($employees, (int) $year, (int) $month);

            return $employees;
        });

        return [
            'period' => Carbon::create((int) $year, (int) $month, 1)->format('Y-m'),
            'year' => (int) $year,
            'month' => (int) $month,
            'rankings' => $rows,
        ];
    }

    /**
     * Personal achievement series for one employee (badge foundation: reads
     * persisted badge links only, never awards anything).
     *
     * @return array<string, mixed>
     */
    public function employee(User $user, Employee $employee, ReportFilterBag $bag): array
    {
        $year = $bag->year ?? now()->year;
        $month = $bag->month ?? now()->month;

        $series = [];
        $anchor = Carbon::create((int) $year, (int) $month, 1);
        for ($offset = 5; $offset >= 0; $offset--) {
            $start = $anchor->copy()->subMonthsNoOverflow($offset);
            $bucket = $this->monthlyRow($employee, $bag, (int) $start->year, (int) $start->month);

            $series[] = [
                'label' => $start->format('Y-m'),
                'total_tasks' => $bucket['total_tasks'],
                'completed_tasks' => $bucket['completed_tasks'],
                'completion_rate' => $bucket['completion_rate'],
                'on_time_rate' => $bucket['on_time_rate'],
                'overall_score' => $bucket['overall_score'],
            ];
        }

        $annualLeaderboard = $this->monthly($user, $bag, $employee->department_id);
        $currentRank = null;
        foreach ($annualLeaderboard['rankings'] as $position) {
            if ((int) $position['employee_id'] === (int) $employee->id) {
                $currentRank = $position['rank'];
                break;
            }
        }

        $badges = $employee->employeeBadges()
            ->with('badge')
            ->get()
            ->map(fn ($link) => [
                'id' => $link->badge?->id,
                'code' => $link->badge?->code,
                'name' => $link->badge?->name,
                'awarded_at' => $link->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name,
            'period' => Carbon::create((int) $year, (int) $month, 1)->format('Y-m'),
            'current_rank' => $currentRank,
            'is_employee_of_month' => $currentRank === 1,
            'series' => $series,
            'badges' => $badges,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlyRow(Employee $employee, ReportFilterBag $bag, int $year, int $month): array
    {
        $base = TaskAssignment::query()->forEmployee($employee->id);
        if ($bag->academicYearId !== null) {
            $base->whereHas('task.annualPlan', fn (Builder $t) => $t->where('academic_year_id', $bag->academicYearId));
        }

        $total = $base->clone()->count();

        $completedQuery = $base->clone()->completed()
            ->whereYear('completed_at', $year)
            ->whereMonth('completed_at', $month);
        $completed = $completedQuery->clone()->count();
        $split = $this->onTimeSplit($completedQuery->get(['completed_at', 'due_date']));

        $completionRate = $this->rate($completed, $total);
        $onTimeRate = $this->rate($split['on_time'], $completed);
        $overall = round(($completionRate * self::OVERALL_SCORE_WEIGHT) + ($onTimeRate * self::ON_TIME_WEIGHT), 2);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name,
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'completion_rate' => $completionRate,
            'on_time_rate' => $onTimeRate,
            'overall_score' => $overall,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function mirrorToMonthlyAchievements(array $rows, int $year, int $month): void
    {
        $academicYear = $this->academicYearFor($year, $month);
        if ($academicYear === null) {
            return;
        }

        foreach ($rows as $row) {
            $achievement = MonthlyAchievement::firstOrNew([
                'employee_id' => $row['employee_id'],
                'academic_year_id' => $academicYear->id,
                'month' => $month,
                'year' => $year,
            ]);

            // Vote fields are $guarded by design (computed verdicts); write
            // them via forceFill so the official table holds the full picture.
            $achievement->forceFill([
                'completion_rate' => $row['completion_rate'],
                'on_time_rate' => $row['on_time_rate'],
                'approved_reports' => 0,
                'completed_tasks' => $row['completed_tasks'],
                'overall_score' => $row['overall_score'],
                'rank' => $row['rank'],
                'is_employee_of_month' => $row['is_employee_of_month'],
            ])->save();
        }
    }

    private function academicYearFor(int $year, int $month): ?AcademicYear
    {
        $day = Carbon::create($year, $month, 1);

        return AcademicYear::query()
            ->where('start_date', '<=', $day->copy()->endOfMonth())
            ->where('end_date', '>=', $day->copy()->startOfMonth())
            ->first();
    }
}
