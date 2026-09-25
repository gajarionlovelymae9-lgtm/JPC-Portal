<?php

namespace App\Services\Performance;

use App\Enums\PerformanceRating;

class ProductivityScoreService
{
    /**
     * Compute weighted productivity score and detailed metric breakdown.
     *
     * @param array{
     *   task_completion_rate: float,
     *   report_completion_rate: float,
     *   on_time_submission_rate: float,
     *   approval_rate: float,
     *   target_achievement_rate: float
     * } $kpis
     * @return array{
     *   score: float,
     *   rating: string,
     *   rating_color: string,
     *   calculation_version: string,
     *   breakdown: list<array{metric_key: string, metric_label: string, raw_value: float, weight: float, weighted_value: float}>
     * }
     */
    public function calculate(array $kpis, ?array $customWeights = null): array
    {
        $weights = $customWeights ?? config('performance.weights', [
            'task_completion' => 0.30,
            'report_completion' => 0.20,
            'on_time_submission' => 0.20,
            'report_approval' => 0.15,
            'target_achievement' => 0.15,
        ]);

        $calculationVersion = config('performance.calculation_version', 'v1.0');

        $metricsMap = [
            'task_completion' => [
                'label' => 'Task Completion',
                'raw' => (float) ($kpis['task_completion_rate'] ?? 0.0),
            ],
            'report_completion' => [
                'label' => 'Report Completion',
                'raw' => (float) ($kpis['report_completion_rate'] ?? 0.0),
            ],
            'on_time_submission' => [
                'label' => 'On-Time Submission',
                'raw' => (float) ($kpis['on_time_submission_rate'] ?? 0.0),
            ],
            'report_approval' => [
                'label' => 'Report Approval',
                'raw' => (float) ($kpis['approval_rate'] ?? 0.0),
            ],
            'target_achievement' => [
                'label' => 'Target Achievement',
                'raw' => (float) ($kpis['target_achievement_rate'] ?? 0.0),
            ],
        ];

        $totalScore = 0.0;
        $breakdown = [];

        foreach ($metricsMap as $key => $info) {
            $weight = (float) ($weights[$key] ?? 0.0);
            $weightedValue = round($info['raw'] * $weight, 2);
            $totalScore += $weightedValue;

            $breakdown[] = [
                'metric_key' => $key,
                'metric_label' => $info['label'],
                'raw_value' => round($info['raw'], 2),
                'weight' => round($weight * 100, 2), // percentage representation (e.g. 30.00%)
                'weighted_value' => $weightedValue,
            ];
        }

        $finalScore = min(100.00, max(0.00, round($totalScore, 2)));
        $ratingEnum = PerformanceRating::fromScore($finalScore);

        return [
            'score' => $finalScore,
            'rating' => $ratingEnum->value,
            'rating_color' => $ratingEnum->color(),
            'calculation_version' => $calculationVersion,
            'breakdown' => $breakdown,
        ];
    }
}
