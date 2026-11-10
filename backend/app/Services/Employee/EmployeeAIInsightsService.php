<?php

namespace App\Services\Employee;

use App\Models\Employee;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Performance\KpiCalculationService;
use App\Services\Performance\ProductivityScoreService;
use Illuminate\Support\Carbon;

/**
 * Employee AI Insights — advisory analytics derived entirely from recorded data.
 *
 * SECURITY: no employee is ever taken from request input. `insights()` resolves
 * the employee exclusively from the authenticated user's linked profile, so a
 * caller can never read another employee's insights (IDOR-safe).
 *
 * HONESTY CONTRACT: this service never invents a number. Every metric is either
 * measured from real records or reported as `null` with the reason it is
 * unavailable. The AI Performance Score reuses the approved JPC formula in
 * `config/performance.php` via ProductivityScoreService instead of defining a
 * second, conflicting set of weights, and metrics with no underlying records
 * are dropped from the weighted average and disclosed in `score_transparency`.
 *
 * `PerformanceInsightService` is deliberately NOT reused here: when no
 * EmployeePerformanceRecord snapshot exists it reports a "consistent
 * performance with a score of 0%" summary, which would present the absence of
 * data as a measured result.
 */
class EmployeeAIInsightsService
{
    /** Number of trailing calendar months evaluated for the score and trends. */
    private const TREND_MONTHS = 6;

    /** Minimum measured months before a directional projection is offered. */
    private const FORECAST_MIN_MONTHS = 3;

    /** Metric keys in approved-weight order, mapped to their KPI field. */
    private const METRIC_ORDER = [
        'task_completion' => 'task_completion_rate',
        'report_completion' => 'report_completion_rate',
        'on_time_submission' => 'on_time_submission_rate',
        'report_approval' => 'approval_rate',
        'target_achievement' => 'target_achievement_rate',
    ];

    public function __construct(
        private readonly KpiCalculationService $kpiService,
        private readonly ProductivityScoreService $scoreService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function insights(User $user, ?int $year = null, ?int $month = null): array
    {
        $employee = $this->requireEmployee($user);

        $period = $this->resolvePeriod($year, $month);
        $current = $this->measure($employee, $period['start'], $period['end']);
        $history = $this->history($employee, $period['end']);

        $score = $this->score($current);

        return [
            'employee' => [
                'name' => trim(implode(' ', array_filter([
                    $employee->first_name,
                    $employee->middle_name,
                    $employee->last_name,
                ]))) ?: null,
                'department' => $employee->department?->name,
                'position' => $employee->position,
            ],
            'period' => [
                'year' => $period['start']->year,
                'month' => $period['start']->month,
                'label' => $period['start']->format('F Y'),
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
            'available' => $current['has_data'],
            'no_data_reason' => $current['has_data']
                ? null
                : 'No task assignments, report submissions, reviewed reports, or targets are recorded for this employee in the selected period, so no metric can be measured yet.',
            'score' => $score,
            'metrics' => $this->metrics($current, $score),
            'performance_trend' => $history['performance'],
            'productivity_trend' => $history['productivity'],
            'alerts' => $current['has_data'] ? $this->alerts($employee, $current, $history) : [],
            'summary' => $current['has_data'] ? $this->summary($current, $score, $history) : null,
            'recommendations' => $current['has_data'] ? $this->recommendations($current, $score) : [],
            'forecast' => $this->forecast($history['performance']),
            'score_transparency' => $this->transparency($current, $score),
            'disclaimer' => 'AI insights are advisory and are calculated only from recorded system data. '
                .'They never replace official evaluation, which remains under authorized departmental leadership.',
        ];
    }

    private function requireEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if ($employee === null) {
            abort(403, 'No employee profile linked to this account.');
        }

        return $employee;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolvePeriod(?int $year, ?int $month): array
    {
        $start = $year !== null && $month !== null
            ? Carbon::create($year, $month, 1)->startOfMonth()
            : Carbon::now()->startOfMonth();

        return [
            'start' => $start->copy()->startOfDay(),
            'end' => $start->copy()->endOfMonth()->endOfDay(),
        ];
    }

    /**
     * Measure every approved KPI for one window and record which of them are
     * backed by real records.
     *
     * @return array<string, mixed>
     */
    private function measure(Employee $employee, Carbon $start, Carbon $end): array
    {
        $task = $this->kpiService->calculateTaskCompletionRate($employee, $start, $end);
        $report = $this->kpiService->calculateReportCompletionRate($employee, $start, $end, $task['total']);
        $onTime = $this->kpiService->calculateOnTimeRate($employee, $start, $end);
        $approval = $this->kpiService->calculateApprovalRate($employee, $start, $end);
        $target = $this->kpiService->calculateTargetAchievementRate($employee, $start, $end);

        $kpis = [
            'task_completion_rate' => $task['rate'],
            'report_completion_rate' => $report['rate'],
            'on_time_submission_rate' => $onTime['rate'],
            'approval_rate' => $approval['rate'],
            'target_achievement_rate' => $target['rate'],
        ];

        $available = [
            'task_completion' => $task['total'] > 0,
            'report_completion' => $report['expected'] > 0,
            'on_time_submission' => $onTime['submitted'] > 0,
            'report_approval' => $approval['reviewed'] > 0,
            'target_achievement' => $target['target'] > 0.0,
        ];

        return [
            'kpis' => $kpis,
            'available' => $available,
            'has_data' => in_array(true, $available, true),
            'available_count' => count(array_filter($available)),
            'counts' => [
                'tasks_total' => (int) $task['total'],
                'tasks_completed' => (int) $task['completed'],
                'reports_expected' => (int) $report['expected'],
                'reports_submitted' => (int) $report['completed'],
                'reports_on_time' => (int) $onTime['on_time'],
                'reports_late' => (int) $onTime['late'],
                'reports_reviewed' => (int) $approval['reviewed'],
                'reports_approved' => (int) $approval['approved'],
                'reports_rejected' => (int) $approval['rejected'],
                'target_value' => (float) $target['target'],
                'target_achieved' => (float) $target['achieved'],
            ],
        ];
    }

    /**
     * Trailing-window history. Only months that contain measurable records are
     * emitted so charts never plot a fabricated zero.
     *
     * @return array{performance: list<array<string, mixed>>, productivity: list<array<string, mixed>>}
     */
    private function history(Employee $employee, Carbon $periodEnd): array
    {
        $performance = [];
        $productivity = [];

        for ($offset = self::TREND_MONTHS - 1; $offset >= 0; $offset--) {
            $cursor = $periodEnd->copy()->subMonthsNoOverflow($offset);
            $window = $this->measure($employee, $cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth());

            if (! $window['has_data']) {
                continue;
            }

            $label = $cursor->format('M Y');
            $score = $this->score($window);

            $performance[] = [
                'period_label' => $label,
                'year' => $cursor->year,
                'month' => $cursor->month,
                'score' => $score['value'],
                'rating' => $score['label'],
            ];

            $completion = $window['available']['task_completion']
                ? (float) $window['kpis']['task_completion_rate']
                : (float) $window['kpis']['report_completion_rate'];

            $productivity[] = [
                'period_label' => $label,
                'year' => $cursor->year,
                'month' => $cursor->month,
                'rate' => round($completion, 2),
            ];
        }

        return ['performance' => $performance, 'productivity' => $productivity];
    }

    /**
     * Weighted score over the measured metrics only, renormalised so the applied
     * weights still sum to 100%.
     *
     * @param  array<string, mixed>  $window
     * @return array<string, mixed>
     */
    private function score(array $window): array
    {
        if (! $window['has_data']) {
            return [
                'value' => null,
                'label' => null,
                'color' => null,
                'basis' => null,
                'metrics_used' => 0,
                'metrics_excluded' => count(self::METRIC_ORDER),
                'used_keys' => [],
                'applied_weights' => [],
            ];
        }

        $configured = config('performance.weights', []);
        $usedKeys = array_keys(array_filter(
            self::METRIC_ORDER,
            fn (string $field, string $key) => (bool) $window['available'][$key],
            ARRAY_FILTER_USE_BOTH,
        ));
        $usedWeight = array_sum(array_map(fn (string $key) => (float) ($configured[$key] ?? 0.0), $usedKeys));

        $normalised = [];
        foreach ($usedKeys as $key) {
            $normalised[$key] = $usedWeight > 0.0
                ? round((float) ($configured[$key] ?? 0.0) / $usedWeight, 6)
                : round(1.0 / max(1, count($usedKeys)), 6);
        }

        $kpis = [];
        foreach (self::METRIC_ORDER as $key => $field) {
            $kpis[$field] = $window['available'][$key] ? (float) $window['kpis'][$field] : 0.0;
        }

        $result = $this->scoreService->calculate($kpis, $normalised);

        return [
            'value' => $result['score'],
            'label' => $result['rating'],
            'color' => $result['rating_color'],
            'basis' => $result['calculation_version'],
            'metrics_used' => count($usedKeys),
            'metrics_excluded' => count(self::METRIC_ORDER) - count($usedKeys),
            'used_keys' => $usedKeys,
            'applied_weights' => array_map(fn (float $w) => round($w * 100, 2), $normalised),
        ];
    }

    /**
     * @param  array<string, mixed>  $window
     * @param  array<string, mixed>  $score
     * @return list<array<string, mixed>>
     */
    private function metrics(array $window, array $score): array
    {
        $configured = config('performance.weights', []);
        $labels = [
            'task_completion' => 'Task Completion',
            'report_completion' => 'Report Completion',
            'on_time_submission' => 'On-Time Submission',
            'report_approval' => 'Report Approval',
            'target_achievement' => 'Target Achievement',
        ];
        $units = [
            'task_completion' => 'of assigned tasks completed',
            'report_completion' => 'of expected reports submitted',
            'on_time_submission' => 'of submitted reports on time',
            'report_approval' => 'of reviewed reports approved',
            'target_achievement' => 'of assigned target value achieved',
        ];

        $metrics = [];

        foreach (self::METRIC_ORDER as $key => $field) {
            $isAvailable = (bool) $window['available'][$key];

            $metrics[] = [
                'key' => $key,
                'label' => $labels[$key],
                'value' => $isAvailable ? round((float) $window['kpis'][$field], 2) : null,
                'unit' => '%',
                'weight' => round((float) ($configured[$key] ?? 0.0) * 100, 2),
                'available' => $isAvailable,
                'basis' => $units[$key],
                'unavailable_reason' => $isAvailable ? null : 'No records exist to measure this metric for the selected period.',
            ];
        }

        $metrics[] = [
            'key' => 'productivity_score',
            'label' => 'AI Performance Score',
            'value' => $score['value'],
            'unit' => '/100',
            'weight' => 100.0,
            'available' => $window['has_data'],
            'basis' => 'Weighted average of the measured metrics above',
            'unavailable_reason' => $window['has_data'] ? null : 'No measurable records exist for the selected period.',
        ];

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $window
     * @param  array{performance: list<array<string, mixed>>, productivity: list<array<string, mixed>>}  $history
     * @return list<array<string, mixed>>
     */
    private function alerts(Employee $employee, array $window, array $history): array
    {
        $alerts = [];
        $counts = $window['counts'];

        $overdue = $this->overdueAssignments($employee);
        if ($overdue['count'] > 0) {
            $alerts[] = [
                'key' => 'overdue_tasks',
                'severity' => $overdue['count'] >= 3 ? 'high' : 'medium',
                'title' => 'Overdue tasks require attention',
                'detail' => "{$overdue['count']} assigned task(s) are past their effective due date and not yet complete.",
                'evidence' => ['overdue_tasks' => $overdue['count']],
            ];
        }

        if ($window['available']['on_time_submission'] && $counts['reports_late'] > 0) {
            $submitted = $counts['reports_on_time'] + $counts['reports_late'];

            $alerts[] = [
                'key' => 'late_reports',
                'severity' => $counts['reports_late'] >= 3 ? 'high' : 'medium',
                'title' => 'Late report submissions detected',
                'detail' => "{$counts['reports_late']} of {$submitted} submitted report(s) were recorded after the deadline.",
                'evidence' => [
                    'late_reports' => $counts['reports_late'],
                    'submitted' => $submitted,
                    'on_time_rate' => round((float) $window['kpis']['on_time_submission_rate'], 2),
                ],
            ];
        }

        if ($window['available']['report_approval']
            && (float) $window['kpis']['approval_rate'] < 70.0
            && $counts['reports_rejected'] > 0) {
            $alerts[] = [
                'key' => 'report_rejections',
                'severity' => 'high',
                'title' => 'Reports are being sent back for revision',
                'detail' => "{$counts['reports_rejected']} of {$counts['reports_reviewed']} reviewed report(s) were rejected, giving a "
                    .round((float) $window['kpis']['approval_rate'], 2).'% approval rate.',
                'evidence' => [
                    'rejected' => $counts['reports_rejected'],
                    'reviewed' => $counts['reports_reviewed'],
                    'approval_rate' => round((float) $window['kpis']['approval_rate'], 2),
                ],
            ];
        }

        if ($window['available']['task_completion']
            && (float) $window['kpis']['task_completion_rate'] < 50.0
            && $counts['tasks_total'] > 0) {
            $alerts[] = [
                'key' => 'low_task_completion',
                'severity' => 'medium',
                'title' => 'Task completion is below half',
                'detail' => "{$counts['tasks_completed']} of {$counts['tasks_total']} assigned task(s) are complete.",
                'evidence' => [
                    'completed_tasks' => $counts['tasks_completed'],
                    'total_tasks' => $counts['tasks_total'],
                    'completion_rate' => round((float) $window['kpis']['task_completion_rate'], 2),
                ],
            ];
        }

        $drop = $this->scoreDrop($history);
        if ($drop !== null) {
            $alerts[] = [
                'key' => 'score_decline',
                'severity' => 'high',
                'title' => 'Performance score declined versus last month',
                'detail' => "Score fell {$drop['delta']} points from {$drop['from_period']} to {$drop['to_period']}.",
                'evidence' => $drop,
            ];
        }

        return $alerts;
    }

    /**
     * @return array{count: int, titles: list<string>}
     */
    private function overdueAssignments(Employee $employee): array
    {
        $now = Carbon::now()->endOfDay();

        $assignments = TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function ($query) use ($now) {
                $query->whereNotNull('due_date')->where('due_date', '<', $now)
                    ->orWhereHas('task', fn ($task) => $task->whereNotNull('due_date')->where('due_date', '<', $now));
            })
            ->with('task:id,title')
            ->get();

        return [
            'count' => $assignments->count(),
            'titles' => $assignments->map(fn ($a) => $a->task?->title)->filter()->values()->all(),
        ];
    }

    /**
     * @param  array{performance: list<array<string, mixed>>, productivity: list<array<string, mixed>>}  $history
     * @return array{delta: float, from_period: string, to_period: string, from_score: float, to_score: float}|null
     */
    private function scoreDrop(array $history): ?array
    {
        $points = $history['performance'];

        if (count($points) < 2) {
            return null;
        }

        $previous = $points[count($points) - 2];
        $latest = $points[count($points) - 1];
        $delta = round((float) $previous['score'] - (float) $latest['score'], 2);

        if ($delta < 15.0) {
            return null;
        }

        return [
            'delta' => $delta,
            'from_period' => (string) $previous['period_label'],
            'to_period' => (string) $latest['period_label'],
            'from_score' => (float) $previous['score'],
            'to_score' => (float) $latest['score'],
        ];
    }

    /**
     * @param  array<string, mixed>  $window
     * @param  array<string, mixed>  $score
     * @param  array{performance: list<array<string, mixed>>, productivity: list<array<string, mixed>>}  $history
     */
    private function summary(array $window, array $score, array $history): string
    {
        $parts = [];

        if ($score['value'] !== null) {
            $parts[] = "Your weighted AI Performance Score for this period is {$score['value']} out of 100 "
                ."({$score['label']}), calculated from {$score['metrics_used']} measured metric(s).";
        }

        if ($window['available']['task_completion']) {
            $parts[] = "You completed {$window['counts']['tasks_completed']} of {$window['counts']['tasks_total']} "
                .'assigned task(s) ('.round((float) $window['kpis']['task_completion_rate'], 2).'%).';
        }

        if ($window['available']['on_time_submission']) {
            $parts[] = "{$window['counts']['reports_on_time']} of "
                .($window['counts']['reports_on_time'] + $window['counts']['reports_late'])
                .' submitted report(s) were on time ('.round((float) $window['kpis']['on_time_submission_rate'], 2).'%).';
        }

        if ($score['metrics_excluded'] > 0) {
            $parts[] = "{$score['metrics_excluded']} metric(s) had no recorded data this period and were excluded from the score.";
        }

        if (count($history['performance']) < 2) {
            $parts[] = 'A month-over-month trend cannot be computed yet because only '
                .count($history['performance']).' period(s) contain measurable records.';
        }

        return implode(' ', $parts);
    }

    /**
     * Recommendations are always tied to the weakest measured metric and quote
     * the underlying counts, so the advice stays auditable.
     *
     * @param  array<string, mixed>  $window
     * @param  array<string, mixed>  $score
     * @return list<array<string, mixed>>
     */
    private function recommendations(array $window, array $score): array
    {
        $advice = [
            'task_completion' => [
                'title' => 'Close the gap on outstanding tasks',
                'body' => 'Prioritise your oldest incomplete assignments and update their progress so your completion rate reflects real progress.',
            ],
            'report_completion' => [
                'title' => 'Submit outstanding reports',
                'body' => 'Reports are still expected for work already assigned in this period. Submitting them lifts your report completion metric.',
            ],
            'on_time_submission' => [
                'title' => 'Submit ahead of deadlines',
                'body' => 'Build in buffer time before report deadlines so submissions are recorded on time.',
            ],
            'report_approval' => [
                'title' => 'Align reports with review criteria',
                'body' => 'Review the feedback on returned reports and address recurring documentation gaps before resubmitting.',
            ],
            'target_achievement' => [
                'title' => 'Progress your assigned targets',
                'body' => 'Break your current target into smaller milestones and record progress against each one.',
            ],
        ];

        $measured = [];
        foreach (self::METRIC_ORDER as $key => $field) {
            if ($window['available'][$key]) {
                $measured[$key] = (float) $window['kpis'][$field];
            }
        }

        if ($measured === []) {
            return [];
        }

        asort($measured);
        $weakest = array_slice($measured, 0, min(2, count($measured)), true);

        $recommendations = [];
        foreach ($weakest as $key => $value) {
            $recommendations[] = [
                'key' => $key,
                'title' => $advice[$key]['title'],
                'detail' => $advice[$key]['body'],
                'evidence' => [
                    'metric' => $key,
                    'value' => round($value, 2),
                ],
            ];
        }

        if (($score['value'] ?? 0) >= (float) config('performance.achievements.top_performer_min_score', 95.0)) {
            $recommendations[] = [
                'key' => 'recognition',
                'title' => 'Performance is in the top band',
                'detail' => 'Your score is at or above the institutional top-performer threshold. Consider volunteering for mentoring or committee work.',
                'evidence' => [
                    'metric' => 'productivity_score',
                    'value' => $score['value'],
                    'threshold' => (float) config('performance.achievements.top_performer_min_score', 95.0),
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * A projection is only offered once enough measured months exist, and it
     * always reports its method and confidence alongside the number.
     *
     * @param  list<array<string, mixed>>  $performance
     * @return array<string, mixed>
     */
    private function forecast(array $performance): array
    {
        $measured = array_values(array_filter($performance, fn ($p) => $p['score'] !== null));

        if (count($measured) < self::FORECAST_MIN_MONTHS) {
            return [
                'value' => null,
                'confidence' => 'insufficient_data',
                'method' => null,
                'based_on_months' => count($measured),
                'required_months' => self::FORECAST_MIN_MONTHS,
                'reason' => 'A projection needs at least '.self::FORECAST_MIN_MONTHS
                    .' months of measured performance; '.count($measured)
                    .(count($measured) === 1 ? ' month is' : ' months are').' available.',
                'needed' => 'Keep completing tasks and submitting reports so additional monthly periods become measurable.',
            ];
        }

        $points = array_map(fn ($p) => (float) $p['score'], $measured);
        $n = count($points);
        $meanX = ($n - 1) / 2;
        $meanY = array_sum($points) / $n;

        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($points as $index => $value) {
            $numerator += ($index - $meanX) * ($value - $meanY);
            $denominator += ($index - $meanX) ** 2;
        }

        $slope = $denominator > 0.0 ? $numerator / $denominator : 0.0;
        $projected = round(max(0.0, min(100.0, $meanY + $slope)), 2);
        $direction = $slope > 0.5 ? 'improving' : ($slope < -0.5 ? 'declining' : 'flat');

        return [
            'value' => $projected,
            'confidence' => 'low',
            'method' => 'linear_trend',
            'based_on_months' => $n,
            'required_months' => self::FORECAST_MIN_MONTHS,
            'direction' => $direction,
            'average_score' => round($meanY, 2),
            'reason' => 'Least-squares projection across '.$n
                .' measured months. Short histories are volatile, so treat this as directional only.',
            'needed' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $window
     * @param  array<string, mixed>  $score
     * @return array<string, mixed>
     */
    private function transparency(array $window, array $score): array
    {
        $configured = config('performance.weights', []);
        $labels = [
            'task_completion' => 'Task Completion',
            'report_completion' => 'Report Completion',
            'on_time_submission' => 'On-Time Submission',
            'report_approval' => 'Report Approval',
            'target_achievement' => 'Target Achievement',
        ];

        $metrics = [];
        foreach (self::METRIC_ORDER as $key => $field) {
            $isAvailable = (bool) $window['available'][$key];
            $raw = $isAvailable ? round((float) $window['kpis'][$field], 2) : null;
            $applied = $isAvailable ? ($score['applied_weights'][$key] ?? null) : null;

            $metrics[] = [
                'key' => $key,
                'label' => $labels[$key],
                'available' => $isAvailable,
                'configured_weight' => round((float) ($configured[$key] ?? 0.0) * 100, 2),
                'raw_value' => $raw,
                'applied_weight' => $applied,
                'contribution' => $raw !== null && $applied !== null
                    ? round($raw * ($applied / 100), 2)
                    : null,
                'excluded_reason' => $isAvailable ? null : 'No records exist for this metric, so it was excluded and the remaining weights were renormalised.',
            ];
        }

        return [
            'formula' => 'Weighted average of the measured metrics: Σ (metric rate × renormalised weight).',
            'weights_source' => 'config/performance.php',
            'calculation_version' => config('performance.calculation_version'),
            'basis' => $score['basis'],
            'metrics' => $metrics,
            'metrics_used' => $score['metrics_used'],
            'metrics_excluded' => $score['metrics_excluded'],
            'available' => $window['has_data'],
        ];
    }
}
