<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\EmployeeBadge;
use App\Models\EmployeePerformanceRecord;
use App\Models\MonthlyAchievement;
use App\Models\PerformancePeriod;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Performance\AchievementEvaluationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin Achievement & Badges management.
 *
 * Everything here reads real tables (badges, employee_badges,
 * monthly_achievements); nothing is estimated or synthesised. Award
 * verification is the only path by which a pending award becomes official,
 * and every mutation writes an activity-log entry for the audit trail.
 */
class AdminBadgeManagementService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly AchievementEvaluationService $evaluation,
    ) {}

    /* ------------------------------------------------------------------ */
    /* Badge definitions */
    /* ------------------------------------------------------------------ */

    /**
     * Paginated badge definitions with real earned-award counts.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    public function paginateBadges(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 12), 100);

        return Badge::query()
            ->withCount(['employeeBadges as earned_count'])
            ->when(($filters['is_active'] ?? null) !== null && $filters['is_active'] !== '', function ($q) use ($filters) {
                $q->where('is_active', in_array(strtolower((string) $filters['is_active']), ['1', 'true', 'yes', 'on'], true));
            })
            ->when(! empty($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Badge $badge) => $this->badgeRow($badge));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createBadge(array $data, ?User $actor = null): array
    {
        $badge = Badge::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'category' => $data['category'] ?? null,
            'tier' => $data['tier'] ?? null,
            'criteria_type' => $data['criteria_type'] ?? null,
            'criteria_value' => $data['criteria_value'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'allow_repeats' => $data['allow_repeats'] ?? false,
        ]);

        $this->activity->log(
            activityType: 'admin.badge.create',
            description: sprintf('Created badge %s (%s).', $badge->name, $badge->code),
            user: $actor,
            subject: $badge,
            metadata: ['code' => $badge->code, 'category' => $badge->category],
        );

        return $this->badgeRow($badge);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateBadge(Badge $badge, array $data, ?User $actor = null): array
    {
        $changes = [];
        foreach (['name', 'code', 'description', 'icon', 'category', 'tier', 'criteria_type', 'is_active', 'allow_repeats'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === $badge->{$field}) {
                continue;
            }
            $changes[$field] = ['from' => $badge->{$field}, 'to' => $data[$field]];
            $badge->{$field} = $data[$field];
        }
        if (array_key_exists('code', $data)) {
            $badge->code = strtoupper($data['code']);
        }
        if (array_key_exists('criteria_value', $data) && $data['criteria_value'] !== $badge->criteria_value) {
            $changes['criteria_value'] = ['from' => $badge->criteria_value, 'to' => $data['criteria_value']];
            $badge->criteria_value = $data['criteria_value'];
        }

        if ($changes !== []) {
            $badge->save();

            $this->activity->log(
                activityType: 'admin.badge.update',
                description: sprintf('Updated badge %s (%s).', $badge->name, $badge->code),
                user: $actor,
                subject: $badge,
                metadata: ['changes' => $changes],
            );
        }

        return $this->badgeRow($badge->refresh());
    }

    /**
     * Deactivate (preferred) or delete a badge definition. A badge with any
     * award history is only ever deactivated so past awards keep their
     * meaning; a never-awarded badge may be soft-deleted outright.
     *
     * @return array{action: string, badge: array<string, mixed>}
     */
    public function archiveBadge(Badge $badge, ?User $actor = null): array
    {
        $earned = $badge->employeeBadges()->count();

        if ($earned > 0) {
            $badge->forceFill(['is_active' => false])->save();

            $this->activity->log(
                activityType: 'admin.badge.deactivate',
                description: sprintf('Deactivated badge %s (%s); %d award(s) on record keep their history.', $badge->name, $badge->code, $earned),
                user: $actor,
                subject: $badge,
                metadata: ['earned_count' => $earned],
            );

            return ['action' => 'deactivated', 'badge' => $this->badgeRow($badge->refresh())];
        }

        $name = $badge->name;
        $code = $badge->code;
        $badge->delete();

        $this->activity->log(
            activityType: 'admin.badge.delete',
            description: sprintf('Deleted badge %s (%s); it had never been awarded.', $name, $code),
            user: $actor,
            metadata: ['code' => $code],
        );

        return ['action' => 'deleted', 'badge' => ['id' => $badge->id, 'code' => $code, 'deleted' => true]];
    }

    /* ------------------------------------------------------------------ */
    /* Award verification */
    /* ------------------------------------------------------------------ */

    /**
     * Verify a pending award. Only pending awards move; anything already
     * reviewed is rejected with a validation error so the audit history of a
     * decision is never silently rewritten.
     *
     * @return array<string, mixed>
     */
    public function verifyAward(EmployeeBadge $award, ?User $actor = null): array
    {
        if ($award->verification_status !== 'pending') {
            throw ValidationException::withMessages([
                'award' => ['This award has already been reviewed.'],
            ]);
        }

        $this->guardAgainstSelfApproval($award, $actor);

        DB::transaction(function () use ($award, $actor): void {
            $award->forceFill([
                'verification_status' => 'verified',
                'verified_by' => $actor?->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->activity->log(
                activityType: 'admin.achievement.verify',
                description: sprintf('Verified award #%d (%s) for %s.', $award->id, $award->badge?->name, $award->employee?->full_name),
                user: $actor,
                subject: $award,
            );
        });

        return $this->awardDetail($award->refresh());
    }

    /**
     * Reject a pending award. A non-empty reason is mandatory.
     *
     * @return array<string, mixed>
     */
    public function rejectAward(EmployeeBadge $award, string $reason, ?User $actor = null): array
    {
        if ($award->verification_status !== 'pending') {
            throw ValidationException::withMessages([
                'award' => ['This award has already been reviewed.'],
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required.'],
            ]);
        }

        $this->guardAgainstSelfApproval($award, $actor);

        DB::transaction(function () use ($award, $actor, $reason): void {
            $award->forceFill([
                'verification_status' => 'rejected',
                'verified_by' => $actor?->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->activity->log(
                activityType: 'admin.achievement.reject',
                description: sprintf('Rejected award #%d (%s) for %s.', $award->id, $award->badge?->name, $award->employee?->full_name),
                user: $actor,
                subject: $award,
                metadata: ['reason' => $reason],
            );
        });

        return $this->awardDetail($award->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function awardDetail(EmployeeBadge $award): array
    {
        $award->load(['employee.department', 'employee.position', 'employee.user', 'badge', 'academicYear', 'awarder', 'verifier', 'recommender']);

        return [
            'id' => $award->id,
            'employee_id' => $award->employee_id,
            'employee_name' => $award->employee?->full_name,
            'employee_number' => $award->employee?->employee_number,
            'employee_email' => $award->employee?->user?->email,
            'position' => $award->employee?->position?->name,
            'department' => $award->employee?->department?->name,
            'badge_id' => $award->badge_id,
            'badge_name' => $award->badge?->name,
            'badge_code' => $award->badge?->code,
            'badge_icon' => $award->badge?->icon,
            'badge_description' => $award->badge?->description,
            'badge_category' => $award->badge?->category,
            'badge_tier' => $award->badge?->tier,
            'academic_year' => $award->academicYear?->name,
            'period_start' => $award->period_start?->toDateString(),
            'period_end' => $award->period_end?->toDateString(),
            'awarded_at' => $award->awarded_at?->toIso8601String(),
            'awarded_by' => $award->awarder?->name,
            'verification_status' => $award->verification_status,
            'verified_by' => $award->verifier?->name,
            'verified_at' => $award->verified_at?->toIso8601String(),
            // Department Head recommendation metadata (null for auto awards).
            'recommended_by' => $award->recommender?->name,
            'recommended_at' => $award->recommended_at?->toIso8601String(),
            'recommendation_details' => $award->recommendation_details,
            'rejection_reason' => $award->rejection_reason,
            'evidence' => $this->evidencePayload($award),
            'criteria_snapshot' => $award->criteria_snapshot,
            'history' => $this->awardHistory($award),
            'created_at' => $award->created_at?->toIso8601String(),
            'updated_at' => $award->updated_at?->toIso8601String(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Summary cards (filtered) */
    /* ------------------------------------------------------------------ */

    /**
     * The four headline cards, all scoped by the active filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summaryCards(array $filters = []): array
    {
        $activeBadges = Badge::query()->where('is_active', true)
            ->when(! empty($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->count();

        $awards = $this->scopedAwards($filters);

        return [
            'total_active_badges' => $activeBadges,
            'total_achievements_awarded' => (clone $awards)->count(),
            'pending_reviews' => (clone $awards)->where('employee_badges.verification_status', 'pending')->count(),
            // "Recognised" means an award that passed review — pending and
            // rejected rows are not recognition yet.
            'employees_recognized' => (clone $awards)
                ->where('employee_badges.verification_status', 'verified')
                ->distinct()
                ->count('employee_badges.employee_id'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Monthly / yearly summaries */
    /* ------------------------------------------------------------------ */

    /**
     * Institution-wide achievement summaries built only from verified awards
     * and real monthly rollups. Ties share the same rank.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summaries(array $filters = []): array
    {
        $awards = $this->scopedAwards($filters)->where('employee_badges.verification_status', 'verified');

        // Employees with the most verified achievements (ties share a rank).
        // Ranking happens in PHP on the aggregated rows so the dense-rank
        // logic is testable and never leaks state between requests.
        $leaderRows = (clone $awards)
            ->groupBy('employee_badges.employee_id', 'employees.first_name', 'employees.last_name', 'employees.employee_number', 'departments.name')
            ->selectRaw('employee_badges.employee_id, employees.first_name, employees.last_name, employees.employee_number, departments.name as department_name, count(*) as total')
            ->orderByDesc('total')
            ->orderBy('employees.last_name')
            ->limit(25)
            ->get();

        $ranks = [];
        $leaders = $leaderRows->map(function ($row) use (&$ranks) {
            // Dense rank: equal totals share a rank; the next distinct
            // total steps down by exactly one so ties are never broken
            // by name order alone.
            $total = (int) $row->total;
            $rank = $ranks[$total] ??= count($ranks) + 1;

            return [
                'rank' => $rank,
                'employee_id' => $row->employee_id,
                'name' => trim($row->first_name.' '.$row->last_name),
                'employee_number' => $row->employee_number,
                'department' => $row->department_name,
                'total' => $total,
            ];
        })->values()->all();

        // Verified achievements awarded per month.
        $monthly = $this->monthlyAwardCounts($filters);

        // Verified award distribution by department.
        $byDepartment = (clone $awards)
            ->groupBy(DB::raw('coalesce(departments.name, "Unassigned")'))
            ->selectRaw('coalesce(departments.name, "Unassigned") as department_name, count(*) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['department' => $row->department_name, 'total' => (int) $row->total])
            ->values()->all();

        // Most frequently earned badges (verified only).
        $badgeFrequency = (clone $awards)
            ->groupBy('badges.id', 'badges.name', 'badges.icon', 'badges.category')
            ->selectRaw('badges.id, badges.name, badges.icon, badges.category, count(*) as total')
            ->orderByDesc('total')
            ->orderBy('badges.name')
            ->get()
            ->map(fn ($row) => [
                'badge_id' => $row->id,
                'name' => $row->name,
                'icon' => $row->icon,
                'category' => $row->category,
                'total' => (int) $row->total,
            ])->values()->all();

        // Academic-year achievement summaries.
        $academicYears = $this->academicYearSummaries();

        return [
            'top_employees' => $leaders,
            'monthly_counts' => $monthly,
            'department_distribution' => $byDepartment,
            'badge_frequency' => $badgeFrequency,
            'academic_year_summaries' => $academicYears,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Automatic evaluation trigger */
    /* ------------------------------------------------------------------ */

    /**
     * Run the idempotent evaluation engine for a calendar month against real
     * performance records. Re-running the same month never duplicates awards
     * (firstOrCreate + the unique index). Months with no performance records
     * report zero evaluated instead of inventing anything.
     *
     * @return array<string, mixed>
     */
    public function evaluateMonth(int $year, int $month, ?User $actor = null): array
    {
        $period = PerformancePeriod::findOrCreateMonthly($year, $month);
        $result = $this->evaluation->evaluateMonthlyPeriod($period);

        $this->activity->log(
            activityType: 'admin.achievement.evaluate',
            description: sprintf('Ran achievement evaluation for %s: %d employee(s) evaluated.', $period->name, $result['total_evaluated']),
            user: $actor,
            subject: $period,
            metadata: ['year' => $year, 'month' => $month],
        );

        return [
            'period' => $period->name,
            'year' => $year,
            'month' => $month,
            'total_evaluated' => $result['total_evaluated'],
            'employee_of_the_month' => $result['employee_of_the_month'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Internals */
    /* ------------------------------------------------------------------ */

    /**
     * employee_badges scoped by the shared filter set.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<EmployeeBadge>
     */
    private function scopedAwards(array $filters)
    {
        return EmployeeBadge::query()
            ->join('employees', 'employees.id', '=', 'employee_badges.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->join('badges', 'badges.id', '=', 'employee_badges.badge_id')
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('employees.department_id', (int) $filters['department_id']))
            ->when(! empty($filters['academic_year_id']), fn ($q) => $q->where('employee_badges.academic_year_id', (int) $filters['academic_year_id']))
            ->when(! empty($filters['category']), fn ($q) => $q->where('badges.category', $filters['category']))
            ->when(! empty($filters['year']), function ($q) use ($filters) {
                $q->whereYear('employee_badges.awarded_at', (int) $filters['year']);
            })
            ->when(! empty($filters['month']), function ($q) use ($filters) {
                $q->whereMonth('employee_badges.awarded_at', (int) $filters['month']);
            });
    }

    /**
     * Verified awards per (year, month) derived from awarded_at, ordered
     * chronologically so the chart shows a real time series.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, int>>
     */
    private function monthlyAwardCounts(array $filters): array
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $yearExpr = $isSqlite ? "CAST(strftime('%Y', employee_badges.awarded_at) AS INTEGER)" : 'year(employee_badges.awarded_at)';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', employee_badges.awarded_at) AS INTEGER)" : 'month(employee_badges.awarded_at)';

        return (clone $this->scopedAwards($filters))
            ->where('employee_badges.verification_status', 'verified')
            ->whereNotNull('employee_badges.awarded_at')
            ->groupBy(DB::raw($yearExpr), DB::raw($monthExpr))
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, count(*) as total")
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => ['year' => (int) $row->year, 'month' => (int) $row->month, 'total' => (int) $row->total])
            ->values()->all();
    }

    /**
     * All-time verified award totals per academic year.
     *
     * @return array<int, array<string, mixed>>
     */
    private function academicYearSummaries(): array
    {
        return EmployeeBadge::query()
            ->where('verification_status', 'verified')
            ->join('academic_years', 'academic_years.id', '=', 'employee_badges.academic_year_id')
            ->groupBy('academic_years.id', 'academic_years.name')
            ->selectRaw('academic_years.id, academic_years.name, count(*) as total, count(distinct employee_badges.employee_id) as employees')
            ->orderBy('academic_years.name')
            ->get()
            ->map(fn ($row) => [
                'academic_year_id' => $row->id,
                'name' => $row->name,
                'total' => (int) $row->total,
                'employees' => (int) $row->employees,
            ])->values()->all();
    }

    private function guardAgainstSelfApproval(EmployeeBadge $award, ?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        $actorEmployeeId = $actor->employee?->id;

        if ($actorEmployeeId !== null && (int) $actorEmployeeId === (int) $award->employee_id) {
            throw new AuthorizationException('You cannot verify or reject your own award.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evidencePayload(EmployeeBadge $award): ?array
    {
        // Stored snapshot (as recorded at award time).
        $stored = null;
        if (filled($award->evidence)) {
            $decoded = json_decode((string) $award->evidence, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $stored = $decoded;
            } else {
                $stored = ['evidence' => (string) $award->evidence];
            }
        } else {
            $stored = $award->criteria_snapshot;
        }

        // The award row alone rarely carries evaluable evidence: the real
        // measured values live in employee_performance_records (per period)
        // and monthly_achievements, and the awarding rule lives on the badge
        // itself. Both are read-only extras here — nothing is synthesised;
        // fields the database does not have are simply omitted.
        $payload = [];
        if ($stored !== null && $stored !== []) {
            $payload['snapshot'] = $stored;
        }

        $measured = $this->measuredEvidence($award);
        if ($measured !== null) {
            $payload['measured'] = $measured;
        }

        $criteria = $this->badgeCriteria($award->badge);
        if ($criteria !== null) {
            $payload['badge_criteria'] = $criteria;
        }

        return $payload === [] ? null : $payload;
    }

    /**
     * The badge's own awarding rule (criteria_type + criteria_value JSON),
     * presented exactly as stored — no evaluation verdicts are computed here.
     */
    private function badgeCriteria(?Badge $badge): ?array
    {
        if ($badge === null || blank($badge->criteria_type)) {
            return null;
        }

        $raw = $badge->criteria_value;
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $value = is_array($decoded) ? $decoded : [];

        $criteria = array_filter([
            'metric' => $badge->criteria_type,
            'threshold' => $value['threshold'] ?? null,
            'min_score' => $value['min_score'] ?? null,
            'period' => $value['period'] ?? null,
        ], fn ($v) => $v !== null);

        return $criteria === [] ? null : $criteria;
    }

    /**
     * The employee's real measured performance for the awarded period, from
     * existing tables only. Preference order: the monthly record covering the
     * awarded_at date → the record overlapping the award window → the latest
     * record for the employee. When none exists the badge simply has no
     * measured evidence.
     */
    private function measuredEvidence(EmployeeBadge $award): ?array
    {
        $record = EmployeePerformanceRecord::query()
            ->where('employee_id', $award->employee_id)
            ->orderByDesc('period_start');

        if ($award->awarded_at !== null) {
            $covering = (clone $record)
                ->whereDate('period_start', '<=', $award->awarded_at)
                ->whereDate('period_end', '>=', $award->awarded_at)
                ->first();
        } else {
            $covering = null;
        }

        $record = $covering
            ?? (clone $record)
                ->when(
                    $award->period_start !== null && $award->period_end !== null,
                    fn ($q) => $q
                        ->whereDate('period_start', '<=', $award->period_end)
                        ->whereDate('period_end', '>=', $award->period_start),
                )
                ->orderByDesc('period_start')
                ->first();

        if ($record === null) {
            return null;
        }

        $values = array_filter([
            'overall_score' => $record->productivity_score ?? $record->overall_score,
            'completion_rate' => $record->completion_rate,
            'on_time_rate' => $record->on_time_rate,
            'approval_rate' => $record->approval_rate,
            'quality_score' => $record->quality_score,
            'target_achievement_rate' => $record->target_achievement_rate,
            'completed_tasks' => $record->metadata['completed_tasks'] ?? null,
            'approved_reports' => $record->metadata['approved_reports'] ?? null,
            'measured_period' => $record->period_start !== null && $record->period_end !== null
                ? $record->period_start->toDateString().' – '.$record->period_end->toDateString()
                : null,
        ], fn ($v) => $v !== null);

        // Monthly rollup verdicts (rank / employee-of-the-month) for the same
        // month, when present.
        $month = $record->period_start?->month;
        $year = $record->period_start?->year;
        if ($month !== null && $year !== null) {
            $rollup = MonthlyAchievement::query()
                ->where('employee_id', $award->employee_id)
                ->where('year', $year)
                ->where('month', $month)
                ->first(['rank', 'is_employee_of_month']);

            if ($rollup !== null) {
                $values['monthly_rank'] = $rollup->rank;
            }
        }

        return $values === [] ? null : $values;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function awardHistory(EmployeeBadge $award): array
    {
        return ActivityLog::query()
            ->with('user:id,name')
            ->where('subject_type', EmployeeBadge::class)
            ->where('subject_id', $award->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'activity_type' => $log->activity_type,
                'description' => $log->description,
                'actor' => $log->user?->name,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function badgeRow(Badge $badge): array
    {
        $badge->loadCount('employeeBadges as earned_count');

        return [
            'id' => $badge->id,
            'name' => $badge->name,
            'code' => $badge->code,
            'description' => $badge->description,
            'icon' => $badge->icon,
            'category' => $badge->category,
            'tier' => $badge->tier,
            'criteria_type' => $badge->criteria_type,
            'criteria_value' => $badge->criteria_value,
            'is_active' => $badge->is_active,
            'allow_repeats' => $badge->allow_repeats,
            'earned_count' => (int) ($badge->earned_count ?? 0),
            'created_at' => $badge->created_at?->toIso8601String(),
            'updated_at' => $badge->updated_at?->toIso8601String(),
        ];
    }
}
