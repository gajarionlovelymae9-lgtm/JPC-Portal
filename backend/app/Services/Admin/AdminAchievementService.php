<?php

namespace App\Services\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Admin section 12 - Achievements.
 *
 * Two real sources are exposed, never merged into a synthetic number:
 *   1. monthly_achievements  -> per-employee monthly rollup rows
 *   2. employee_badges       -> actual badge award grants
 *
 * `employee_of_the_month` is the `is_employee_of_month` flag on the rollup row.
 * `employee_badges` awards are reported separately so an empty badge table
 * renders as "no awards issued yet" instead of a fake leaderboard.
 */
class AdminAchievementService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 12), 100);

        return DB::table('monthly_achievements as ma')
            ->join('employees', 'employees.id', '=', 'ma.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->when(! empty($filters['year']), fn ($q) => $q->where('ma.year', (int) $filters['year']))
            ->when(! empty($filters['month']), fn ($q) => $q->where('ma.month', (int) $filters['month']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('employees.department_id', (int) $filters['department_id']))
            ->when(($filters['employee_of_month'] ?? null) !== null && $filters['employee_of_month'] !== '', function ($q) use ($filters) {
                $q->where('ma.is_employee_of_month', $this->toBool($filters['employee_of_month']));
            })
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('employees.first_name', 'like', $term)
                        ->orWhere('employees.last_name', 'like', $term)
                        ->orWhere('employees.employee_number', 'like', $term);
                });
            })
            ->orderByDesc('ma.year')
            ->orderByDesc('ma.month')
            ->orderByDesc('ma.overall_score')
            ->orderBy('employees.last_name')
            ->select([
                'ma.id',
                'ma.employee_id',
                'ma.month',
                'ma.year',
                'ma.completion_rate',
                'ma.on_time_rate',
                'ma.approved_reports',
                'ma.completed_tasks',
                'ma.overall_score',
                'ma.rank',
                'ma.is_employee_of_month',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'departments.name as department_name',
            ])
            ->paginate($perPage)
            ->through(fn ($row) => [
                'id' => $row->id,
                'employee_id' => $row->employee_id,
                'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                'employee_number' => $row->employee_number,
                'department' => $row->department_name,
                'period' => sprintf('%d-%02d', $row->year, $row->month),
                'month' => $row->month,
                'year' => $row->year,
                'completion_rate' => round((float) $row->completion_rate, 2),
                'on_time_rate' => round((float) $row->on_time_rate, 2),
                'approved_reports' => (int) $row->approved_reports,
                'completed_tasks' => (int) $row->completed_tasks,
                'overall_score' => round((float) $row->overall_score, 2),
                'rank' => $row->rank === null ? null : (int) $row->rank,
                'employee_of_the_month' => (bool) $row->is_employee_of_month,
            ]);
    }

    /**
     * Badge awards actually present in employee_badges.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    public function badges(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 12), 100);

        return DB::table('employee_badges')
            ->join('employees', 'employees.id', '=', 'employee_badges.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->join('badges', 'badges.id', '=', 'employee_badges.badge_id')
            ->leftJoin('users', 'users.id', '=', 'employee_badges.awarded_by')
            ->leftJoin('users as verifiers', 'verifiers.id', '=', 'employee_badges.verified_by')
            ->leftJoin('academic_years', 'academic_years.id', '=', 'employee_badges.academic_year_id')
            ->when(! empty($filters['badge_id']), fn ($q) => $q->where('employee_badges.badge_id', (int) $filters['badge_id']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('employees.department_id', (int) $filters['department_id']))
            ->when(! empty($filters['academic_year_id']), fn ($q) => $q->where('employee_badges.academic_year_id', (int) $filters['academic_year_id']))
            ->when(! empty($filters['category']), fn ($q) => $q->where('badges.category', $filters['category']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('employee_badges.verification_status', $filters['status']))
            ->when(! empty($filters['year']), fn ($q) => $q->whereYear('employee_badges.awarded_at', (int) $filters['year']))
            ->when(! empty($filters['month']), fn ($q) => $q->whereMonth('employee_badges.awarded_at', (int) $filters['month']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('employees.first_name', 'like', $term)
                        ->orWhere('employees.last_name', 'like', $term)
                        ->orWhere('badges.name', 'like', $term);
                });
            })
            ->orderByDesc('employee_badges.awarded_at')
            ->orderByDesc('employee_badges.id')
            ->select([
                'employee_badges.id',
                'employee_badges.employee_id',
                'employee_badges.badge_id',
                'employee_badges.period_start',
                'employee_badges.period_end',
                'employee_badges.awarded_at',
                'employee_badges.verification_status',
                'employee_badges.verified_at',
                'employee_badges.rejection_reason',
                'employee_badges.criteria_snapshot',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'departments.name as department_name',
                'badges.name as badge_name',
                'badges.code as badge_code',
                'badges.icon as badge_icon',
                'badges.description as badge_description',
                'badges.category as badge_category',
                'badges.tier as badge_tier',
                'users.name as awarded_by_name',
                'verifiers.name as verified_by_name',
                'academic_years.name as academic_year_name',
            ])
            ->paginate($perPage)
            ->through(fn ($row) => [
                'id' => $row->id,
                'employee_id' => $row->employee_id,
                'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                'employee_number' => $row->employee_number,
                'department' => $row->department_name,
                'badge' => $row->badge_name,
                'badge_code' => $row->badge_code,
                'badge_icon' => $row->badge_icon,
                'badge_description' => $row->badge_description,
                'badge_category' => $row->badge_category,
                'badge_tier' => $row->badge_tier,
                'academic_year' => $row->academic_year_name,
                'period_start' => $row->period_start,
                'period_end' => $row->period_end,
                'awarded_at' => $row->awarded_at,
                'awarded_by' => $row->awarded_by_name,
                'verification_status' => $row->verification_status,
                'verified_by' => $row->verified_by_name,
                'verified_at' => $row->verified_at,
                'rejection_reason' => $row->rejection_reason,
                'evidence' => $row->criteria_snapshot === null ? null : json_decode($row->criteria_snapshot, true),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $rollups = DB::table('monthly_achievements')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(distinct employee_id) as employees')
            ->selectRaw('sum(case when is_employee_of_month = 1 then 1 else 0 end) as of_the_month')
            ->selectRaw('avg(overall_score) as avg_score')
            ->selectRaw('max(overall_score) as top_score')
            ->first();

        $awards = DB::table('employee_badges')->count();
        $distinctBadges = DB::table('employee_badges')->distinct()->count('badge_id');

        return [
            'rollup_rows' => (int) ($rollups->total ?? 0),
            'employees_recognised' => (int) ($rollups->employees ?? 0),
            'employee_of_the_month_count' => (int) ($rollups->of_the_month ?? 0),
            'badge_awards' => $awards,
            'distinct_badges_awarded' => $distinctBadges,
            'avg_overall_score' => $rollups->avg_score === null ? null : round((float) $rollups->avg_score, 2),
            'top_overall_score' => $rollups->top_score === null ? null : round((float) $rollups->top_score, 2),
        ];
    }

    /**
     * Top performers by real overall_score for the requested period.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function leaders(array $filters = [], int $limit = 5): array
    {
        return $this->paginate([
            ...$filters,
            'per_page' => $limit,
        ])->items();
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $years = DB::table('monthly_achievements')->distinct()->orderByDesc('year')->pluck('year');

        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        return [
            'years' => $years->map(fn ($y) => ['value' => (int) $y, 'label' => (string) $y])->all(),
            'months' => collect($months)->map(fn ($label, $value) => [
                'value' => (int) $value,
                'label' => $label,
            ])->values()->all(),
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'academic_years' => DB::table('academic_years')->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'categories' => DB::table('badges')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
                ->map(fn ($c) => ['value' => (string) $c, 'label' => (string) $c])->values()->all(),
            'tiers' => DB::table('badges')->whereNotNull('tier')->distinct()->orderBy('tier')->pluck('tier')
                ->map(fn ($t) => ['value' => (string) $t, 'label' => (string) $t])->values()->all(),
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pending review'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'badges' => DB::table('badges')->orderBy('name')->get(['id', 'name', 'code', 'icon', 'category', 'tier', 'is_active'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'code' => $b->code,
                    'icon' => $b->icon,
                    'category' => $b->category,
                    'tier' => $b->tier,
                    'is_active' => (bool) $b->is_active,
                ])->all(),
        ];
    }

    private function toBool(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
