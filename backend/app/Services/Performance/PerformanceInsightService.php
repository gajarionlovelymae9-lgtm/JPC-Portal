<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\PerformancePeriod;

class PerformanceInsightService
{
    /**
     * Generate advisory performance insights and anomaly indicators for an employee.
     *
     * @return array{
     *   is_advisory: true,
     *   disclaimer: string,
     *   summary: string,
     *   trend_direction: string,
     *   score_delta: float,
     *   anomalies: list<string>,
     *   recommendations: list<string>
     * }
     */
    public function generateEmployeeInsights(Employee $employee, PerformancePeriod $currentPeriod): array
    {
        $currentRecord = EmployeePerformanceRecord::where('employee_id', $employee->id)
            ->where('performance_period_id', $currentPeriod->id)
            ->first();

        $currentScore = $currentRecord ? (float) $currentRecord->productivity_score : 0.0;

        // Find previous period
        $prevPeriod = PerformancePeriod::where('period_type', $currentPeriod->period_type)
            ->where('end_date', '<', $currentPeriod->start_date)
            ->orderByDesc('end_date')
            ->first();

        $prevRecord = $prevPeriod ? EmployeePerformanceRecord::where('employee_id', $employee->id)
            ->where('performance_period_id', $prevPeriod->id)
            ->first() : null;

        $prevScore = $prevRecord ? (float) $prevRecord->productivity_score : $currentScore;
        $delta = round($currentScore - $prevScore, 2);

        $anomalies = [];
        $recommendations = [];

        // Check for score drops
        if ($delta <= -15.00) {
            $anomalies[] = "Significant performance drop of {$delta}% compared to the previous period.";
            $recommendations[] = 'Conduct supportive performance check-in to identify roadblocks or resource constraints.';
        }

        // Check for late submissions
        $lateReports = (int) ($currentRecord?->metadata['late_reports'] ?? 0);
        if ($lateReports >= 2) {
            $anomalies[] = "Employee recorded {$lateReports} late report submissions during this period.";
            $recommendations[] = 'Review deadline feasibility and task workload distribution.';
        }

        // Check for report rejections / low approval rate
        $approvalRate = (float) ($currentRecord?->approval_rate ?? 100.0);
        if ($approvalRate < 70.00 && ($currentRecord?->metadata['reviewed_reports'] ?? 0) > 0) {
            $anomalies[] = "Report approval rate is below benchmark ({$approvalRate}%).";
            $recommendations[] = 'Provide guidance on documentation standards and institutional report criteria.';
        }

        // Check for high performance
        if ($currentScore >= 95.00) {
            $recommendations[] = 'Acknowledge top-tier performance and consider for departmental mentorship opportunities.';
        }

        $trendDirection = match (true) {
            $delta > 3.00 => 'improving',
            $delta < -3.00 => 'declining',
            default => 'stable',
        };

        $summary = match ($trendDirection) {
            'improving' => "Employee performance is trending positively (+{$delta}%). Key accomplishments were met consistently.",
            'declining' => 'Employee performance decreased by '.abs($delta).'% compared to prior period. Review identified warning indicators.',
            default => "Employee maintained consistent performance across the evaluated period with a score of {$currentScore}%.",
        };

        return [
            'is_advisory' => true,
            'disclaimer' => 'AI-assisted performance insights are strictly advisory to support human review and coaching. Official evaluations remain under authorized departmental leadership.',
            'summary' => $summary,
            'trend_direction' => $trendDirection,
            'score_delta' => $delta,
            'anomalies' => $anomalies,
            'recommendations' => $recommendations,
        ];
    }
}
