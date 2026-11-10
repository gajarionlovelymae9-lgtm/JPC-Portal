<?php

namespace App\Services\Performance;

use App\Models\Badge;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeeTarget;
use App\Models\MonthlyAchievement;
use Illuminate\Support\Carbon;

/**
 * Progress toward not-yet-earned badges.
 *
 * The thresholds here are not new criteria: badges stays the single source of
 * truth. When an admin badge defines criteria_value with a numeric threshold
 * it is used directly; otherwise the rule mirrors the same constants the
 * evaluation engine applies, so the shown progress always matches what the
 * engine actually awards.
 */
class AchievementProgressService
{
    /**
     * Mirror of the engine's metric per badge code.
     *
     * @return array<string, array{metric: string, target_key: string, default: float, label: string}>
     */
    private const ENGINE_RULES = [
        'TOP_PERFORMER' => ['metric' => 'overall_score', 'target_key' => 'score', 'default' => 95.0, 'label' => 'Reach %s productivity score in a month'],
        'OUTSTANDING' => ['metric' => 'overall_score', 'target_key' => 'score', 'default' => 90.0, 'label' => 'Reach %s productivity score in a month'],
        'ON_TIME_HERO' => ['metric' => 'on_time_rate', 'target_key' => 'rate', 'default' => 100.0, 'label' => 'Achieve %s on-time delivery in a month'],
        'PERFECT_SUBMISSION' => ['metric' => 'combined_rate', 'target_key' => 'rate', 'default' => 100.0, 'label' => 'Reach %s completion and on-time rates in a month'],
        'TASK_CHAMPION' => ['metric' => 'completed_tasks', 'target_key' => 'tasks', 'default' => 10.0, 'label' => 'Complete %s tasks in a month'],
        'REPORT_EXCELLENCE' => ['metric' => 'approved_reports', 'target_key' => 'reports', 'default' => 5.0, 'label' => 'Earn %s approved reports in a month'],
        'EMPLOYEE_OF_MONTH' => ['metric' => 'employee_of_month', 'target_key' => 'none', 'default' => 1.0, 'label' => 'Be ranked first for a month'],
        'CONSISTENT_PROGRESS' => ['metric' => 'consistent_months', 'target_key' => 'none', 'default' => 3.0, 'label' => 'Hold 3 consecutive scored months at 85 or more'],
        'MOST_IMPROVED' => ['metric' => 'improvement', 'target_key' => 'score', 'default' => 10.0, 'label' => 'Improve the productivity score by %s points month over month'],
        'GOAL_ACHIEVER' => ['metric' => 'goal_completion', 'target_key' => 'none', 'default' => 100.0, 'label' => 'Complete every KPI target for the period'],
    ];

    /**
     * The employee's earned badges plus tracked progress on every other
     * active badge in the catalogue.
     *
     * @return array{stats: array<string, float>, badges: list<array<string, mixed>>}
     */
    public function forEmployee(Employee $employee): array
    {
        $metrics = $this->metrics($employee);
        $year = (int) Carbon::now()->year;

        // Awards are tagged with the academic year they were granted in, so
        // the same badge can return next academic year without manual work.
        $earnedByBadgeId = EmployeeBadge::query()
            ->where('employee_id', $employee->id)
            ->whereYear('awarded_at', '>=', $year - 1)
            ->with('badge')
            ->get()
            ->groupBy('badge_id');

        $badgeList = [];
        foreach (Badge::query()->active()->orderBy('name')->get() as $badge) {
            $earnedIds = $earnedByBadgeId->get($badge->id);
            $row = [
                'badge_id' => $badge->id,
                'code' => $badge->code,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'category' => $badge->category,
                'tier' => $badge->tier,
                'criteria_type' => $badge->criteria_type,
                'earned' => $earnedIds !== null && $earnedIds->isNotEmpty(),
                'award_dates' => $earnedIds !== null
                    ? $earnedIds->pluck('awarded_at')->filter()->map->toDateString()->values()->all()
                    : [],
            ];

            if ($row['earned']) {
                $row['progress'] = 100.0;
            } else {
                $rule = self::ENGINE_RULES[$badge->code] ?? null;
                if ($rule === null) {
                    // Badges outside the engine's fixed rule set (fully
                    // admin-recommended ones) have no measurable progress.
                    $row['progress'] = null;
                    $row['criteria'] = $badge->description;
                } else {
                    // Admin-defined criteria_value overrides the engine's
                    // default threshold when it carries the same key.
                    $value = $badge->criteria_value;
                    $target = (float) (is_array($value) ? ($value[$rule['target_key']] ?? $rule['default']) : $rule['default']);
                    $current = $metrics[$rule['metric']] ?? 0.0;
                    $progressPct = $target > 0.0 ? min(100.0, $current / $target * 100.0) : 0.0;

                    $row['progress'] = round($progressPct, 1);
                    $row['criteria'] = sprintf($rule['label'], rtrim(rtrim((string) $target, '0'), '.'));
                    $row['current_value'] = round($current, 1);
                    $row['target_value'] = $target;
                }
            }

            $badgeList[] = $row;
        }

        return ['stats' => $metrics, 'badges' => $badgeList];
    }

    /**
     * Real recorded statistics for the employee — the same monthly rollups
     * the evaluation engine awards from.
     *
     * @return array<string, float>
     */
    private function metrics(Employee $employee): array
    {
        $rows = MonthlyAchievement::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $latest = $rows->first();
        $prev = $rows->skip(1)->first();

        // Streak of consecutive scored months at 85+, mirroring the engine.
        $streak = 0;
        foreach ($rows as $row) {
            if ((float) $row->overall_score < 85.0) {
                break;
            }
            $streak++;
        }

        $targets = EmployeeTarget::query()->where('employee_id', $employee->id)->get(['status']);
        $totalTargets = $targets->count();
        $completedTargets = $targets->filter(static fn ($t) => $t->status === 'completed')->count();

        return [
            'overall_score' => (float) ($latest?->overall_score ?? 0),
            'completion_rate' => (float) ($latest?->completion_rate ?? 0),
            'on_time_rate' => (float) ($latest?->on_time_rate ?? 0),
            'combined_rate' => (float) min($latest?->completion_rate ?? 0, $latest?->on_time_rate ?? 0),
            'completed_tasks' => (float) ($rows->max('completed_tasks') ?? 0),
            'approved_reports' => (float) ($rows->max('approved_reports') ?? 0),
            'employee_of_month' => (float) ($rows->contains(static fn ($r) => $r->is_employee_of_month) ? 1 : 0),
            'consistent_months' => (float) $streak,
            'improvement' => (float) ($latest && $prev ? $latest->overall_score - $prev->overall_score : 0),
            'goal_completion' => (float) ($totalTargets > 0 ? round($completedTargets / $totalTargets * 100.0, 1) : 0),
        ];
    }
}
