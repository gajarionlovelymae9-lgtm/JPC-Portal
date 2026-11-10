<?php

namespace App\Services\Reporting;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Phase 9 — Management Summary Service (spec item 20).
 *
 * Grounded, deterministic narrative compiled from ReportingService and
 * AnalyticsService — never AI-generated. Numbers are read from the same
 * authoritative aggregates the reports expose, reusing the services instead of
 * duplicating the calculations.
 *
 * Institution-wide summary → admin/hr only (the institution report is the
 * source and is already restricted).
 */
class ManagementSummaryService
{
    public function __construct(
        private readonly ReportingService $reportingService,
        private readonly AnalyticsService $analyticsService,
    ) {}

    /**
     * Backwards-compatible monthly entry point used by existing callers.
     *
     * @throws AuthorizationException
     */
    public function generateSummary(int $year, int $month, User $user): array
    {
        return $this->buildSummary(['year' => $year, 'month' => $month], $user);
    }

    /**
     * Period-aware entry point: accepts the same period filters as the report
     * endpoints (month/year, quarter, academic year, custom range).
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws AuthorizationException
     */
    public function generateSummaryForPeriod(array $filters, User $user): array
    {
        return $this->buildSummary($filters, $user);
    }

    /**
     * @param  array<string, mixed>  $filters
     *
     * @throws AuthorizationException
     */
    private function buildSummary(array $filters, User $user): array
    {
        $period = $this->reportingService->periodBounds($filters);
        $institutionReport = $this->reportingService->generateInstitutionReport($filters, $user);

        $anchor = $period['end'] ?? Carbon::now();
        $departmentId = isset($filters['department_id']) && $filters['department_id'] !== ''
            ? (int) $filters['department_id']
            : null;
        $monthlyReport = $this->reportingService->generateMonthlyReport(
            (int) $anchor->year,
            (int) $anchor->month,
            $user,
            $departmentId,
        );
        $distribution = $this->analyticsService->getPerformanceDistribution($user, $filters);

        if (! ($institutionReport['has_data'] ?? false) && ! ($monthlyReport['has_data'] ?? false)) {
            return [
                'period' => $this->periodKey($period),
                'period_label' => $period['label'],
                'period_type' => $period['key']['type'] ?? 'monthly',
                'narrative' => "Executive Management Summary for {$period['label']}: Not enough data available to generate a summary for this period yet.",
                'key_takeaways' => ['Not enough data available for this period.'],
                'change' => null,
                'has_data' => false,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }

        $summary = $institutionReport['summary'];
        $change = $this->previousPeriodChange($period, $user, $summary);

        $topPerformer = $monthlyReport['top_performer']['name'] ?? 'N/A';
        $topDept = $monthlyReport['top_department']['department_name']
            ?? $monthlyReport['department_rankings'][0]['department_name']
            ?? 'N/A';
        $avgProd = $summary['average_productivity'] ?? null;
        $dqScore = $summary['data_quality_score'] ?? null;
        $lateCount = (int) ($summary['late_reports'] ?? 0);
        $tasksCompleted = (int) ($summary['tasks_completed'] ?? 0);
        $tasksAssigned = (int) ($summary['tasks_assigned'] ?? 0);
        $reportsSubmitted = (int) ($summary['reports_submitted'] ?? 0);
        $taskRate = $summary['task_completion_rate'] ?? null;

        $changeWord = ($period['key']['type'] ?? 'monthly') === 'monthly'
            ? 'Month-over-month'
            : 'Period-over-period';

        $narrative = "Executive Management Summary for {$period['label']}: ".
            ($avgProd !== null
                ? "The institution recorded an average productivity score of {$this->fmt($avgProd)}%. "
                : 'No performance snapshots were recorded for this period yet. ').
            ($topDept !== 'N/A'
                ? "The top-performing department was {$topDept}, led by top individual performer {$topPerformer}. "
                : 'No rankings are available yet for this period. ').
            "A total of {$tasksCompleted} tasks were completed out of {$tasksAssigned} assigned".
            ($taskRate !== null ? " ({$this->fmt($taskRate)}%)." : '.').
            ($reportsSubmitted > 0 ? " There were {$lateCount} late report submissions identified." : ' No report submissions were recorded in this window.').
            ($dqScore !== null
                ? " The institutional Data Quality Health Score stands at {$this->fmt($dqScore)}%."
                : ' The data quality score is N/A because no completed scan has evaluated the institution yet.').
            // Never invent a baseline: state the absence of one explicitly.
            ($change !== null && $change['average_productivity_change'] !== null
                ? " {$changeWord} average productivity changed by {$this->fmt($change['average_productivity_change'], true)} percentage points."
                : ' No comparable prior-period baseline is available, so period-over-period change is N/A.');

        $takeaways = [];
        if ($avgProd !== null) {
            $takeaways[] = "Institutional Average Productivity: {$this->fmt($avgProd)}%";
        }
        if ($topDept !== 'N/A') {
            $takeaways[] = "Top Performing Department: {$topDept}";
        }
        if ($topPerformer !== 'N/A') {
            $takeaways[] = "Top Performer: {$topPerformer}";
        }
        $takeaways[] = "Tasks Completed: {$tasksCompleted} / {$tasksAssigned}".($taskRate !== null ? " ({$this->fmt($taskRate)}%)" : '');
        $takeaways[] = "Reports Submitted: {$reportsSubmitted}";
        $takeaways[] = "Late Submissions Identified: {$lateCount}";
        $takeaways[] = $dqScore !== null
            ? "Data Quality Health Index: {$this->fmt($dqScore)}%"
            : 'Data Quality Health Index: N/A (not yet evaluated)';
        $takeaways[] = $change !== null && $change['average_productivity_change'] !== null
            ? "{$changeWord} Productivity Change: {$this->fmt($change['average_productivity_change'], true)} ppt"
            : "{$changeWord} Productivity Change: N/A";

        return [
            'period' => $this->periodKey($period),
            'period_label' => $period['label'],
            'period_type' => $period['key']['type'] ?? 'monthly',
            'narrative' => $narrative,
            'key_takeaways' => $takeaways,
            'metrics' => [
                'average_productivity' => $avgProd !== null ? (float) $avgProd : null,
                'tasks_completed' => $tasksCompleted,
                'tasks_assigned' => $tasksAssigned,
                'task_completion_rate' => $taskRate !== null ? (float) $taskRate : null,
                'reports_submitted' => $reportsSubmitted,
                'pending_reports' => (int) ($summary['pending_reports'] ?? 0),
                'late_reports' => $lateCount,
                'on_time_rate' => isset($summary['on_time_rate']) ? (float) $summary['on_time_rate'] : null,
                'approval_rate' => isset($summary['approval_rate']) ? (float) $summary['approval_rate'] : null,
                'data_quality_score' => $dqScore !== null ? (float) $dqScore : null,
            ],
            'change' => $change,
            'distribution' => $distribution['distribution'],
            'top_performer' => $monthlyReport['top_performer'],
            'top_department' => $monthlyReport['top_department'],
            'has_data' => true,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Change between the selected period and the equivalent previous period.
     *
     * Returns null when there is no comparable baseline at all, so the UI can
     * render "N/A" instead of a fabricated 0.0 change.
     *
     * @param  array{start: Carbon|null, end: Carbon|null, label: string, key: array<string, mixed>}  $period
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>|null
     */
    private function previousPeriodChange(array $period, User $user, array $current): ?array
    {
        $previousFilters = $this->previousPeriodFilters($period);

        if ($previousFilters === null) {
            return null;
        }

        try {
            $previousReport = $this->reportingService->generateInstitutionReport($previousFilters, $user);
        } catch (AuthorizationException) {
            return null;
        }

        $hasBaseline = ($previousReport['summary']['average_productivity'] ?? null) !== null
            || (int) ($previousReport['summary']['reports_submitted'] ?? 0) > 0
            || (int) ($previousReport['summary']['tasks_assigned'] ?? 0) > 0;

        if (! $hasBaseline) {
            return null;
        }

        $previousSummary = $previousReport['summary'];
        $previousLabel = $previousReport['period'];

        return [
            'period' => $previousReport['period_key'] ?? null,
            'period_label' => $previousLabel,
            'average_productivity_change' => $this->relDelta(
                $current['average_productivity'] ?? null,
                $previousSummary['average_productivity'] ?? null,
            ),
            'task_completion_change' => $this->relDelta(
                $current['task_completion_rate'] ?? null,
                $previousSummary['task_completion_rate'] ?? null,
            ),
            'reports_change' => $this->absDelta(
                (int) ($current['reports_submitted'] ?? 0),
                (int) ($previousSummary['reports_submitted'] ?? 0),
            ),
            'late_change' => $this->absDelta(
                (int) ($current['late_reports'] ?? 0),
                (int) ($previousSummary['late_reports'] ?? 0),
            ),
            'previous_period' => $previousLabel,
        ];
    }

    /**
     * Filters that select the period immediately before the selected one.
     *
     * @param  array{start: Carbon|null, end: Carbon|null, label: string, key: array<string, mixed>}  $period
     * @return array<string, mixed>|null
     */
    private function previousPeriodFilters(array $period): ?array
    {
        $type = $period['key']['type'] ?? 'monthly';

        if ($type === 'monthly') {
            $month = Carbon::create((int) $period['key']['year'], (int) $period['key']['month'], 1)->subMonthNoOverflow();

            return ['year' => $month->year, 'month' => $month->month];
        }

        if ($type === 'quarterly') {
            $start = Carbon::create((int) $period['key']['year'], (($period['key']['quarter'] - 1) * 3) + 1, 1)
                ->subMonthsNoOverflow(3);

            return ['year' => $start->year, 'quarter' => (int) ceil($start->month / 3)];
        }

        if ($type === 'yearly') {
            return ['year' => (int) $period['key']['year'] - 1, 'period_type' => 'yearly'];
        }

        if ($period['start'] === null || $period['end'] === null) {
            return null;
        }

        // Custom ranges and academic years: shift the whole window back by its
        // own length, which is the only comparable baseline available.
        $days = (int) $period['start']->diffInDays($period['end']) + 1;
        $previousEnd = $period['start']->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays(max(0, $days - 1))->startOfDay();

        return ['start_date' => $previousStart->toDateString(), 'end_date' => $previousEnd->toDateString()];
    }

    /**
     * @param  array{key: array<string, mixed>}  $period
     */
    private function periodKey(array $period): string
    {
        $key = $period['key'];

        return match ($key['type'] ?? null) {
            'monthly' => sprintf('%04d-%02d', $key['year'], $key['month']),
            'quarterly' => sprintf('%04d-Q%d', $key['year'], $key['quarter']),
            'yearly' => (string) $key['year'],
            'academic_year' => 'AY-'.$key['academic_year_id'],
            'custom' => $key['from'].'..'.$key['to'],
            default => (string) ($key['type'] ?? 'period'),
        };
    }

    private function relDelta(mixed $current, mixed $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return round((float) $current - (float) $previous, 1);
    }

    private function absDelta(int $current, int $previous): int
    {
        return $current - $previous;
    }

    private function fmt(mixed $value, bool $sign = false, string $empty = 'N/A', string $prefix = ''): string
    {
        if ($value === null) {
            return $empty;
        }

        $formatted = number_format((float) $value, 1);

        return $prefix.($sign && (float) $value > 0 ? '+'.$formatted : $formatted);
    }
}
