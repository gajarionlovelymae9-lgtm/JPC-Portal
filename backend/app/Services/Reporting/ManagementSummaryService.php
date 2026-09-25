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
 * authoritative snapshots/aggregates the reports expose, reusing the services
 * instead of duplicating the calculations.
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
     * @throws AuthorizationException
     */
    public function generateSummary(int $year, int $month, User $user): array
    {
        $institutionReport = $this->reportingService->generateInstitutionReport(['year' => $year, 'month' => $month], $user);
        $monthlyReport = $this->reportingService->generateMonthlyReport($year, $month, $user);
        $distribution = $this->analyticsService->getPerformanceDistribution($user, ['year' => $year, 'month' => $month]);

        if (! ($institutionReport['has_data'] ?? false) && ! ($monthlyReport['has_data'] ?? false)) {
            $label = Carbon::createFromDate($year, $month, 1)->format('F Y');

            return [
                'period' => sprintf('%04d-%02d', $year, $month),
                'period_label' => $label,
                'narrative' => "Executive Management Summary for {$label}: Not enough data available to generate a summary for this period yet.",
                'key_takeaways' => ['Not enough data available for this period.'],
                'has_data' => false,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }

        $summary = $institutionReport['summary'];
        $change = $this->previousMonthChange($year, $month, $user, $summary);

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

        $monthLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $narrative = "Executive Management Summary for {$monthLabel}: ".
            ($avgProd !== null
                ? "The institution recorded an average productivity score of {$this->fmt($avgProd)}%. "
                : 'No performance snapshots were recorded for this period yet. ').
            ($topDept !== 'N/A'
                ? "The top-performing department was {$topDept}, led by top individual performer {$topPerformer}. "
                : 'No rankings are available yet for this period. ').
            "A total of {$tasksCompleted} tasks were completed out of {$tasksAssigned} assigned".
            ($taskRate !== null ? " ({$this->fmt($taskRate)}%)." : '.').
            ($reportsSubmitted > 0 ? " There were {$lateCount} late report submissions identified." : ' No report submissions were recorded in this window.').
            ($dqScore !== null ? " The institutional Data Quality Health Score stands at {$this->fmt($dqScore)}%." : '').
            ($change !== null ? " Month-over-month average productivity changed by {$this->fmt($change['average_productivity_change'], true, 'N/A')} percentage points." : '');

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
        $takeaways[] = "Late Submissions Identified: {$lateCount}";
        if ($dqScore !== null) {
            $takeaways[] = "Data Quality Health Index: {$this->fmt($dqScore)}%";
        }
        if ($change !== null) {
            $takeaways[] = "Month-over-Month Productivity Change: {$this->fmt($change['average_productivity_change'], true, 'N/A')} ppt";
        }

        return [
            'period' => sprintf('%04d-%02d', $year, $month),
            'period_label' => $monthLabel,
            'narrative' => $narrative,
            'key_takeaways' => $takeaways,
            'metrics' => [
                'average_productivity' => $avgProd !== null ? (float) $avgProd : null,
                'tasks_completed' => $tasksCompleted,
                'tasks_assigned' => $tasksAssigned,
                'task_completion_rate' => $taskRate !== null ? (float) $taskRate : null,
                'reports_submitted' => $reportsSubmitted,
                'late_reports' => $lateCount,
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
     * Month-over-month deltas between the current institution summary and the
     * previous month's institution report (both snapshot-based). Returns null
     * when there is no prior baseline.
     *
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>|null
     */
    private function previousMonthChange(int $year, int $month, User $user, array $current): ?array
    {
        $previous = Carbon::createFromDate($year, $month, 1)->subMonthNoOverflow();

        try {
            $previousReport = $this->reportingService->generateInstitutionReport(
                ['year' => $previous->year, 'month' => $previous->month],
                $user,
            );
        } catch (AuthorizationException) {
            return null;
        }

        if (! ($previousReport['has_data'] ?? false)) {
            return null;
        }

        $previousSummary = $previousReport['summary'];

        return [
            'period' => sprintf('%04d-%02d', $previous->year, $previous->month),
            'average_productivity_change' => $this->relDelta(
                $current['average_productivity'] ?? null,
                $previousSummary['average_productivity'] ?? null,
            ),
            'reports_change' => $this->absDelta(
                (int) ($current['reports_submitted'] ?? 0),
                (int) ($previousSummary['reports_submitted'] ?? 0),
            ),
            'late_change' => $this->absDelta(
                (int) ($current['late_reports'] ?? 0),
                (int) ($previousSummary['late_reports'] ?? 0),
            ),
            'previous_period' => sprintf('%04d-%02d', $previous->year, $previous->month),
        ];
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