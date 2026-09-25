<?php

namespace App\Services\Reporting;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\Report;
use App\Models\User;
use App\Services\Reporting\Concerns\ResolvesReportingScope;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * Phase 9 — Analytics Service (spec items 14, 16-18).
 *
 * Trends, comparisons, rankings and rating distributions. Every query is role
 * scoped through ResolvesReportingScope (admin/hr = institution,
 * department_head = own department, employee = own records only) and cached
 * per (scope, filter-set) so heavy roll-ups stay cold for the UI and AI tools.
 *
 * Period filters follow the same conventions as ReportingService::periodBounds:
 * year + optional month, plus optional start_date/end_date custom windows.
 */
class AnalyticsService
{
    use ResolvesReportingScope;

    public function __construct(private readonly ReportCacheService $cache) {}

    public function getPerformanceTrend(User $user, array $filters = [], int $months = 6): array
    {
        $months = $this->clampMonths($months);
        $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));
        $cacheKey = sprintf('analytics:performance_trend:%d:%d:%s', $user->id, $months, $departmentId ?? 'all');

        return $this->cache->remember($cacheKey, function () use ($user, $months, $departmentId) {
            $trends = [];
            $now = Carbon::now();

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = (clone $now)->subMonths($i);

                $query = EmployeePerformanceRecord::query()
                    ->whereYear('period_start', $date->year)
                    ->whereMonth('period_start', $date->month);
                $this->scopeByEmployeeRelation($query, $user, $departmentId);

                $records = $query->get(['productivity_score', 'overall_score', 'completion_rate']);

                $trends[] = [
                    'period' => $date->format('M Y'),
                    'year' => $date->year,
                    'month' => $date->month,
                    'productivity_score' => $this->round($records->avg(fn ($r) => $r->productivity_score ?? $r->overall_score)),
                    'completion_rate' => $this->round($records->avg('completion_rate')),
                    'records_count' => $records->count(),
                ];
            }

            return $trends;
        }, $this->ttl());
    }

    /**
     * Average completion-rate trend from performance snapshots (Phase 6).
     */
    public function getCompletionTrend(User $user, array $filters = [], int $months = 6): array
    {
        $months = $this->clampMonths($months);
        $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));
        $cacheKey = sprintf('analytics:completion_trend:%d:%d:%s', $user->id, $months, $departmentId ?? 'all');

        return $this->cache->remember($cacheKey, function () use ($user, $months, $departmentId) {
            $trends = [];
            $now = Carbon::now();

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = (clone $now)->subMonths($i);

                $query = EmployeePerformanceRecord::query()
                    ->whereYear('period_start', $date->year)
                    ->whereMonth('period_start', $date->month);
                $this->scopeByEmployeeRelation($query, $user, $departmentId);

                $records = $query->get(['completion_rate']);

                $trends[] = [
                    'period' => $date->format('M Y'),
                    'year' => $date->year,
                    'month' => $date->month,
                    'completion_rate' => $this->round($records->avg('completion_rate')),
                    'records_count' => $records->count(),
                ];
            }

            return $trends;
        }, $this->ttl());
    }

    /**
     * Report submission volume + late-rate trend (fixed: scoped via the
     * employee relation — `reports` has no department_id column).
     */
    public function getSubmissionTrend(User $user, array $filters = [], int $months = 6): array
    {
        $months = $this->clampMonths($months);
        $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));
        $cacheKey = sprintf('analytics:submission_trend:%d:%d:%s', $user->id, $months, $departmentId ?? 'all');

        return $this->cache->remember($cacheKey, function () use ($user, $months, $departmentId) {
            $trends = [];
            $now = Carbon::now();

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = (clone $now)->subMonths($i);

                $query = Report::query()->whereHas('employee');
                $this->scopeByEmployeeRelation($query, $user, $departmentId);
                $query->whereYear('created_at', $date->year)->whereMonth('created_at', $date->month);

                $total = (clone $query)->count();
                $late = (clone $query)->where('is_late', true)->count();

                $trends[] = [
                    'period' => $date->format('M Y'),
                    'year' => $date->year,
                    'month' => $date->month,
                    'total_reports' => $total,
                    'late_reports' => $late,
                    'late_rate' => $total > 0 ? round(($late / $total) * 100, 1) : null,
                ];
            }

            return $trends;
        }, $this->ttl());
    }

    /**
     * Backwards-compatible alias kept for the legacy reporting stack.
     */
    public function getLateSubmissionTrend(User $user, int $months = 6): array
    {
        return $this->getSubmissionTrend($user, [], $months);
    }

    /**
     * Department-vs-department comparison for a single period. Department
     * heads are locked to their own department (requesting another → 403).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDepartmentComparison(array $departmentIds, array $filters, User $user): array
    {
        $allowedIds = $this->resolveComparisonScope($departmentIds, $user);
        $year = (int) ($filters['year'] ?? Carbon::now()->year);
        $month = (int) ($filters['month'] ?? Carbon::now()->month);
        $cacheKey = sprintf(
            'analytics:dept_comparison:%d:%04d-%02d:%s',
            $user->id,
            $year,
            $month,
            hash('sha256', implode(',', $allowedIds)),
        );

        return $this->cache->remember($cacheKey, function () use ($allowedIds, $year, $month) {
            $departments = Department::whereIn('id', $allowedIds)->get();

            return $departments->map(function ($dept) use ($year, $month) {
                $records = EmployeePerformanceRecord::whereHas('employee', fn ($q) => $q->where('department_id', $dept->id))
                    ->whereYear('period_start', $year)
                    ->whereMonth('period_start', $month)
                    ->get(['completion_rate', 'on_time_rate', 'approval_rate', 'target_achievement_rate', 'productivity_score', 'overall_score']);

                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'productivity' => $this->round($records->avg(fn ($r) => $r->productivity_score ?? $r->overall_score)),
                    'completion_rate' => $this->round($records->avg('completion_rate')),
                    'on_time_rate' => $this->round($records->avg('on_time_rate')),
                    'approval_rate' => $this->round($records->avg('approval_rate')),
                    'target_achievement' => $this->round($records->avg('target_achievement_rate')),
                    'employees_evaluated' => $records->count(),
                ];
            })->values()->all();
        }, $this->ttl());
    }

    /**
     * Employee-vs-employee comparison for a single period. Employees may only
     * compare themselves; department heads only their own staff.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEmployeeComparison(array $employeeIds, array $filters, User $user): array
    {
        foreach ($employeeIds as $employeeId) {
            $this->assertEmployeeScope($user, (int) $employeeId);
        }

        $year = (int) ($filters['year'] ?? Carbon::now()->year);
        $month = (int) ($filters['month'] ?? Carbon::now()->month);
        $cacheKey = sprintf(
            'analytics:employee_comparison:%d:%04d-%02d:%s',
            $user->id,
            $year,
            $month,
            hash('sha256', implode(',', $employeeIds)),
        );

        return $this->cache->remember($cacheKey, function () use ($employeeIds, $year, $month) {
            $employees = Employee::with('department:id,name')->whereIn('id', $employeeIds)->get();

            return $employees->map(function ($emp) use ($year, $month) {
                $record = EmployeePerformanceRecord::where('employee_id', $emp->id)
                    ->whereYear('period_start', $year)
                    ->whereMonth('period_start', $month)
                    ->first();

                return [
                    'id' => $emp->id,
                    'name' => trim($emp->first_name.' '.$emp->last_name),
                    'department' => $emp->department?->name,
                    'productivity' => $this->round($record?->productivity_score ?? $record?->overall_score),
                    'completion_rate' => $this->round($record?->completion_rate),
                    'on_time_rate' => $this->round($record?->on_time_rate),
                    'approval_rate' => $this->round($record?->approval_rate),
                    'target_achievement' => $this->round($record?->target_achievement_rate),
                ];
            })->values()->all();
        }, $this->ttl());
    }

    /**
     * Month-vs-month institution comparison (admin/hr) with department-head
     * dept scoping when a department filter is supplied.
     */
    public function getPeriodComparison(string $periodA, string $periodB, User $user, array $filters = []): array
    {
        if (! in_array($user->roleName(), ['admin', 'hr', 'department_head'], true)) {
            throw new AuthorizationException('Forbidden');
        }

        $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));
        $dateA = Carbon::parse($periodA.'-01');
        $dateB = Carbon::parse($periodB.'-01');
        $cacheKey = sprintf(
            'analytics:period_comparison:%d:%s-%s:%s',
            $user->id,
            $dateA->format('Y-m'),
            $dateB->format('Y-m'),
            $departmentId ?? 'all',
        );

        return $this->cache->remember($cacheKey, function () use ($user, $dateA, $dateB, $departmentId) {
            $recordsA = $this->recordsForMonth($user, $dateA, $departmentId);
            $recordsB = $this->recordsForMonth($user, $dateB, $departmentId);

            $prodA = $this->round($recordsA->avg(fn ($r) => $r->productivity_score ?? $r->overall_score));
            $prodB = $this->round($recordsB->avg(fn ($r) => $r->productivity_score ?? $r->overall_score));

            return [
                'period_a' => [
                    'period' => $dateA->format('M Y'),
                    '_key' => $dateA->format('Y-m'),
                    'productivity' => $prodA,
                    'completion_rate' => $this->round($recordsA->avg('completion_rate')),
                    'on_time_rate' => $this->round($recordsA->avg('on_time_rate')),
                    'employees_evaluated' => $recordsA->count(),
                ],
                'period_b' => [
                    'period' => $dateB->format('M Y'),
                    '_key' => $dateB->format('Y-m'),
                    'productivity' => $prodB,
                    'completion_rate' => $this->round($recordsB->avg('completion_rate')),
                    'on_time_rate' => $this->round($recordsB->avg('on_time_rate')),
                    'employees_evaluated' => $recordsB->count(),
                ],
                'delta' => [
                    'productivity_diff' => $prodA !== null && $prodB !== null ? round($prodB - $prodA, 1) : null,
                    'completion_rate_diff' => $this->delta($recordsA->avg('completion_rate'), $recordsB->avg('completion_rate')),
                    'on_time_rate_diff' => $this->delta($recordsA->avg('on_time_rate'), $recordsB->avg('on_time_rate')),
                ],
            ];
        }, $this->ttl());
    }

    /**
     * Top-N leaderboard for a period and metric. Collection-scoped per role.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRankings(User $user, array $filters = [], string $metric = 'productivity'): array
    {
        $year = (int) ($filters['year'] ?? Carbon::now()->year);
        $month = (int) ($filters['month'] ?? Carbon::now()->month);
        $limit = min(max(1, (int) ($filters['limit'] ?? config('reports.top_performers_limit', 5))), config('reports.top_performers_limit_max', 10));
        $cacheKey = sprintf('analytics:rankings:%d:%04d-%02d:%s:%d', $user->id, $year, $month, $metric, $limit);

        return $this->cache->remember($cacheKey, function () use ($user, $year, $month, $metric, $limit) {
            $query = EmployeePerformanceRecord::with(['employee.department:id,name'])
                ->whereYear('period_start', $year)
                ->whereMonth('period_start', $month);
            $this->scopeByEmployeeRelation($query, $user);

            $records = $query->get();
            $records = $records->filter(fn ($r) => $r->employee !== null);

            $sorted = $records->sortByDesc(function ($r) use ($metric) {
                return (float) match ($metric) {
                    'completion_rate' => $r->completion_rate ?? 0,
                    'on_time_rate' => $r->on_time_rate ?? 0,
                    'approval_rate' => $r->approval_rate ?? 0,
                    'target_achievement' => $r->target_achievement_rate ?? 0,
                    default => $r->productivity_score ?? $r->overall_score ?? 0,
                };
            })->values()->take($limit);

            return $sorted->map(function ($r, $idx) use ($metric) {
                return [
                    'rank' => $idx + 1,
                    'employee_id' => $r->employee_id,
                    'name' => trim(($r->employee->first_name ?? '').' '.($r->employee->last_name ?? '')),
                    'department' => $r->employee?->department?->name,
                    'metric' => $metric,
                    'score' => $this->round($r->productivity_score ?? $r->overall_score),
                    'completion_rate' => $this->round($r->completion_rate),
                    'on_time_rate' => $this->round($r->on_time_rate),
                    'target_achievement' => $this->round($r->target_achievement_rate),
                    'rating' => $r->rating ?? 'N/A',
                ];
            })->values()->all();
        }, $this->ttl());
    }

    /**
     * Rating-band distribution (PerformanceRating bands) for a period.
     */
    public function getPerformanceDistribution(User $user, array $filters = []): array
    {
        $year = (int) ($filters['year'] ?? Carbon::now()->year);
        $month = (int) ($filters['month'] ?? Carbon::now()->month);
        $departmentId = $this->effectiveDepartmentId($user, $this->nullableInt($filters['department_id'] ?? null));
        $cacheKey = sprintf('analytics:distribution:%d:%04d-%02d:%s', $user->id, $year, $month, $departmentId ?? 'all');

        return $this->cache->remember($cacheKey, function () use ($user, $year, $month, $departmentId) {
            $query = EmployeePerformanceRecord::query()
                ->whereYear('period_start', $year)
                ->whereMonth('period_start', $month);
            $this->scopeByEmployeeRelation($query, $user, $departmentId);

            $records = $query->get(['productivity_score', 'overall_score']);

            $bands = [
                'Excellent' => 0,
                'Very Good' => 0,
                'Good' => 0,
                'Needs Improvement' => 0,
                'Unsatisfactory' => 0,
            ];

            foreach ($records as $record) {
                $score = (float) ($record->productivity_score ?? $record->overall_score ?? 0);
                $bands[\App\Enums\PerformanceRating::fromScore($score)->value] = $bands[\App\Enums\PerformanceRating::fromScore($score)->value] + 1;
            }

            return [
                'total_evaluated' => $records->count(),
                'distribution' => $bands,
            ];
        }, $this->ttl());
    }

    /* ---------------------------------------------------------------------
     | Helpers
     * ------------------------------------------------------------------- */

    /**
     * Resolve which departments this caller may compare. Department heads are
     * locked to their own; requesting any other department is Forbidden.
     *
     * @param  array<int, mixed>  $departmentIds
     * @return array<int, int>
     */
    private function resolveComparisonScope(array $departmentIds, User $user): array
    {
        $ids = array_values(array_unique(array_map('intval', $departmentIds)));

        if ($user->roleName() === 'department_head') {
            $owned = (int) ($user->employee?->department_id ?? 0);

            if ($owned === 0) {
                throw new AuthorizationException('Forbidden');
            }

            if ($ids !== [] && $ids !== [$owned]) {
                throw new AuthorizationException('Forbidden');
            }

            return [$owned];
        }

        return $ids;
    }

    private function recordsForMonth(User $user, Carbon $date, ?int $departmentId): Collection
    {
        $query = EmployeePerformanceRecord::query()
            ->whereYear('period_start', $date->year)
            ->whereMonth('period_start', $date->month);
        $this->scopeByEmployeeRelation($query, $user, $departmentId);

        return $query->get(['completion_rate', 'on_time_rate', 'productivity_score', 'overall_score']);
    }

    private function clampMonths(int $months): int
    {
        return min(max(1, $months), (int) config('reports.trend_months_max', 12));
    }

    private function delta(?float $a, ?float $b): ?float
    {
        return $a === null || $b === null ? null : round($b - $a, 1);
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function ttl(): int
    {
        return (int) config('reports.cache_ttl', 60);
    }
}