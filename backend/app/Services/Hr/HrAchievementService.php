<?php

namespace App\Services\Hr;

use App\Models\EmployeeBadge;
use App\Models\User;
use App\Services\Admin\AdminBadgeManagementService;
use App\Services\Audit\ActivityLogger;
use App\Services\Reports\ReportExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrAchievementService
{
    public function __construct(
        private readonly AdminBadgeManagementService $badges,
        private readonly ActivityLogger $activity,
        private readonly ReportExportService $exporter,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    public function paginateRecords(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 12), 100);

        return EmployeeBadge::query()
            ->with(['employee.department', 'employee.position', 'employee.user', 'badge', 'academicYear', 'awarder', 'verifier', 'recommender'])
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_badges.employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', (int) $filters['department_id'])))
            ->when(! empty($filters['badge_id']), fn ($q) => $q->where('employee_badges.badge_id', (int) $filters['badge_id']))
            ->when(! empty($filters['academic_year_id']), fn ($q) => $q->where('employee_badges.academic_year_id', (int) $filters['academic_year_id']))
            ->when(! empty($filters['category']), fn ($q) => $q->whereHas('badge', fn ($b) => $b->where('category', $filters['category'])))
            ->when(! empty($filters['tier']), fn ($q) => $q->whereHas('badge', fn ($b) => $b->where('tier', $filters['tier'])))
            ->when(! empty($filters['status']) || ! empty($filters['verification_status']), function ($q) use ($filters) {
                $status = $filters['verification_status'] ?? $filters['status'];

                return $q->where('employee_badges.verification_status', $status);
            })
            ->when(! empty($filters['year']), function ($q) use ($filters) {
                $q->whereYear('employee_badges.awarded_at', (int) $filters['year']);
            })
            ->when(! empty($filters['month']), function ($q) use ($filters) {
                $q->whereMonth('employee_badges.awarded_at', (int) $filters['month']);
            })
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->whereHas('employee', function ($e) use ($term) {
                    $e->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('employee_number', 'like', $term);
                })->orWhereHas('badge', function ($b) use ($term) {
                    $b->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->orderByDesc('employee_badges.awarded_at')
            ->orderByDesc('employee_badges.id')
            ->paginate($perPage)
            ->through(fn (EmployeeBadge $award) => $this->recordRow($award));
    }

    public function show(int $id): array
    {
        $award = EmployeeBadge::with(['employee.department', 'employee.position', 'employee.user', 'badge', 'academicYear', 'awarder', 'verifier'])->findOrFail($id);

        return $this->badges->awardDetail($award);
    }

    public function verify(int $id, User $actor): array
    {
        $award = EmployeeBadge::findOrFail($id);

        return $this->badges->verifyAward($award, $actor);
    }

    public function reject(int $id, string $reason, User $actor): array
    {
        $award = EmployeeBadge::findOrFail($id);

        return $this->badges->rejectAward($award, trim($reason), $actor);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function kpiCards(array $filters = []): array
    {
        $query = $this->scopedAwards($filters);

        $total = (clone $query)->count();
        $verified = (clone $query)->where('employee_badges.verification_status', 'verified')->count();
        $pending = (clone $query)->where('employee_badges.verification_status', 'pending')->count();

        [$year, $month] = $this->period($filters);
        $thisMonth = $this->scopedAwards($filters)
            ->whereYear('employee_badges.awarded_at', $year)
            ->whereMonth('employee_badges.awarded_at', $month)
            ->count();

        return [
            'total_recognitions' => $total,
            'verified' => $verified,
            'pending_verification' => $pending,
            'this_month' => $thisMonth,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $departments = DB::table('departments')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all();

        $academicYears = DB::table('academic_years')->orderByDesc('start_date')->get(['id', 'name'])
            ->map(fn ($y) => ['value' => $y->id, 'label' => $y->name])->all();

        $badges = DB::table('badges')->orderBy('name')->get(['id', 'name', 'code', 'category', 'tier', 'is_active'])
            ->map(fn ($b) => [
                'value' => $b->id,
                'label' => $b->name,
                'code' => $b->code,
                'category' => $b->category,
                'tier' => $b->tier,
                'is_active' => (bool) $b->is_active,
            ])->all();

        $categories = DB::table('badges')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
            ->map(fn ($c) => ['value' => (string) $c, 'label' => (string) $c])->values()->all();

        $tiers = DB::table('badges')->whereNotNull('tier')->distinct()->orderBy('tier')->pluck('tier')
            ->map(fn ($t) => ['value' => (string) $t, 'label' => (string) $t])->values()->all();

        $years = DB::table('employee_badges')
            ->whereNotNull('awarded_at')
            ->selectRaw('distinct year(awarded_at) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => ['value' => (int) $y, 'label' => (string) $y])->all();

        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        return [
            'departments' => $departments,
            'academic_years' => $academicYears,
            'badges' => $badges,
            'categories' => $categories,
            'tiers' => $tiers,
            'years' => $years,
            'months' => collect($months)->map(fn ($label, $value) => ['value' => (int) $value, 'label' => $label])->values()->all(),
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pending verification'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function reports(array $filters = []): array
    {
        $base = $this->scopedAwards($filters);
        [$year, $month] = $this->period($filters);
        $isMonthly = ! empty($filters['month']);
        $periodLabel = $isMonthly ? sprintf('%s %d', Carbon::create($year, $month, 1)->format('F'), $year) : (string) $year;

        $totals = [
            'total' => (clone $base)->count(),
            'verified' => (clone $base)->where('employee_badges.verification_status', 'verified')->count(),
            'pending' => (clone $base)->where('employee_badges.verification_status', 'pending')->count(),
            'rejected' => (clone $base)->where('employee_badges.verification_status', 'rejected')->count(),
        ];

        // `Collection::map()` preserves the source keys, so `->all()` can
        // serialize as a JSON *object* instead of a list — which the React
        // achievement page then tries to `.map()` and crashes with
        // "data.by_category.map is not a function". `->values()` re-indexes
        // into a plain list so the wire contract is always an array.
        $byCategory = (clone $base)
            ->whereHas('badge')
            ->groupBy('badges.category')
            ->selectRaw('coalesce(badges.category, "Uncategorized") as category, count(*) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total])
            ->values()
            ->all();

        $byTier = (clone $base)
            ->whereHas('badge')
            ->groupBy('badges.tier')
            ->selectRaw('coalesce(badges.tier, "Unspecified") as tier, count(*) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['tier' => $row->tier, 'total' => (int) $row->total])
            ->values()
            ->all();

        $employees = (clone $base)
            ->with(['employee.department'])
            ->get()
            ->groupBy('employee_id')
            ->map(function (Collection $group) {
                $first = $group->first();
                $employee = $first->employee;

                return [
                    'employee_id' => $employee?->id,
                    'employee_number' => $employee?->employee_number,
                    'name' => $employee?->full_name,
                    'department' => $employee?->department?->name,
                    'total' => $group->count(),
                    'verified' => $group->where('verification_status', 'verified')->count(),
                    'pending' => $group->where('verification_status', 'pending')->count(),
                    'rejected' => $group->where('verification_status', 'rejected')->count(),
                    'awards' => $group->map(fn (EmployeeBadge $award) => [
                        'id' => $award->id,
                        'badge' => $award->badge?->name,
                        'category' => $award->badge?->category,
                        'tier' => $award->badge?->tier,
                        'awarded_at' => $award->awarded_at?->toDateString(),
                        'verification_status' => $award->verification_status,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'period' => [
                'label' => $periodLabel,
                'year' => $year,
                'month' => $isMonthly ? $month : null,
            ],
            'totals' => $totals,
            'by_category' => $byCategory,
            'by_tier' => $byTier,
            'employees' => $employees,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters = [], string $format = 'csv', ?User $actor = null): StreamedResponse
    {
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            $format = 'csv';
        }

        $awards = $this->scopedAwards($filters)
            ->with(['employee.department', 'badge', 'academicYear', 'verifier'])
            ->orderByDesc('employee_badges.awarded_at')
            ->get();

        $columns = ['Employee ID', 'Employee Name', 'Department', 'Achievement Title', 'Category', 'Tier', 'Achievement Date', 'Verification Status', 'Verifier', 'Period', 'Academic Year', 'Reason'];
        $rows = $awards->map(function (EmployeeBadge $award) {
            $employee = $award->employee;
            $badge = $award->badge;
            $period = $award->period_start && $award->period_end
                ? $award->period_start->format('Y-m-d').' to '.$award->period_end->format('Y-m-d')
                : null;

            $reason = $award->rejection_reason ?: ($award->criteria_snapshot['reason'] ?? $award->criteria_snapshot['criteria'] ?? null);

            return [
                'Employee ID' => $employee?->employee_number,
                'Employee Name' => $employee?->full_name,
                'Department' => $employee?->department?->name,
                'Achievement Title' => $badge?->name,
                'Category' => $badge?->category,
                'Tier' => $badge?->tier,
                'Achievement Date' => $award->awarded_at?->toDateString(),
                'Verification Status' => $award->verification_status,
                'Verifier' => $award->verifier?->name,
                'Period' => $period,
                'Academic Year' => $award->academicYear?->name,
                'Reason' => is_array($reason) ? json_encode($reason) : $reason,
            ];
        })->all();

        [$year, $month] = $this->period($filters);
        $suffix = ! empty($filters['month']) ? "{$year}_{$month}" : (string) $year;
        $filename = "JPC_HR_Achievements_{$suffix}.".($format === 'xlsx' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv'));

        $this->activity->log(
            activityType: 'hr.achievements.export',
            description: sprintf('%s exported HR achievements (%s) for %s.', $actor?->name ?? 'System', $format, $suffix),
            user: $actor,
            metadata: ['format' => $format, 'year' => $year, 'month' => $month ?? null, 'filters' => $filters, 'row_count' => count($rows)],
        );

        return $this->exporter->stream($columns, $rows, $format, $filename);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<EmployeeBadge>
     */
    private function scopedAwards(array $filters): Builder
    {
        $query = EmployeeBadge::query()
            ->join('employees', 'employees.id', '=', 'employee_badges.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->join('badges', 'badges.id', '=', 'employee_badges.badge_id')
            ->whereNull('employees.deleted_at');

        if (! empty($filters['employee_id'])) {
            $query->where('employee_badges.employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['department_id'])) {
            $query->where('employees.department_id', (int) $filters['department_id']);
        }
        if (! empty($filters['badge_id'])) {
            $query->where('employee_badges.badge_id', (int) $filters['badge_id']);
        }
        if (! empty($filters['academic_year_id'])) {
            $query->where('employee_badges.academic_year_id', (int) $filters['academic_year_id']);
        }
        if (! empty($filters['category'])) {
            $query->where('badges.category', $filters['category']);
        }
        if (! empty($filters['tier'])) {
            $query->where('badges.tier', $filters['tier']);
        }
        if (! empty($filters['status']) || ! empty($filters['verification_status'])) {
            $status = $filters['verification_status'] ?? $filters['status'];
            $query->where('employee_badges.verification_status', $status);
        }
        if (! empty($filters['year'])) {
            $query->whereYear('employee_badges.awarded_at', (int) $filters['year']);
        }
        if (! empty($filters['month'])) {
            $query->whereMonth('employee_badges.awarded_at', (int) $filters['month']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('employees.first_name', 'like', $term)
                    ->orWhere('employees.last_name', 'like', $term)
                    ->orWhere('employees.employee_number', 'like', $term)
                    ->orWhere('badges.name', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{int,int}
     */
    private function period(array $filters): array
    {
        $year = ! empty($filters['year']) ? (int) $filters['year'] : now()->year;
        $month = ! empty($filters['month']) ? (int) $filters['month'] : now()->month;

        return [$year, $month];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordRow(EmployeeBadge $award): array
    {
        $employee = $award->employee;
        $badge = $award->badge;

        return [
            'id' => $award->id,
            'employee_id' => $employee?->id,
            'employee_name' => $employee?->full_name,
            'employee_number' => $employee?->employee_number,
            'department' => $employee?->department?->name,
            'position' => $employee?->position?->name,
            'badge_id' => $badge?->id,
            'achievement_title' => $badge?->name,
            'badge_name' => $badge?->name,
            'badge_code' => $badge?->code,
            'category' => $badge?->category,
            'badge_category' => $badge?->category,
            'tier' => $badge?->tier,
            'badge_tier' => $badge?->tier,
            'achievement_date' => $award->awarded_at?->toDateString(),
            'awarded_at' => $award->awarded_at?->toIso8601String(),
            'verification_status' => $award->verification_status,
            'verified_at' => $award->verified_at?->toIso8601String(),
            'verified_by' => $award->verifier?->name,
            // Department Head recommendation metadata (null for auto awards).
            'recommended_by' => $award->recommender?->name,
            'recommended_at' => $award->recommended_at?->toIso8601String(),
            'recommendation_details' => $award->recommendation_details,
            'rejection_reason' => $award->rejection_reason,
            'reason' => $award->recommendation_details ?: $award->rejection_reason ?: ($award->criteria_snapshot['reason'] ?? $award->criteria_snapshot['criteria'] ?? null),
            'period_start' => $award->period_start?->toDateString(),
            'period_end' => $award->period_end?->toDateString(),
            'academic_year' => $award->academicYear?->name,
            'evidence' => $award->evidence ?: $award->criteria_snapshot,
        ];
    }
}
