<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\EmployeePerformanceRecord;

class PerformanceConsistencyRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::INVALID_KPI_VALUE->value;
    }

    public function getName(): string
    {
        return 'Out-of-Bounds or Contradictory Performance Data';
    }

    public function getDescription(): string
    {
        return 'Validates that stored employee KPI percentages and overall productivity scores fall within the legitimate 0.00% – 100.00% range.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::CRITICAL;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'performance';
    }

    public function check(mixed $entity): ?array
    {
        if (!($entity instanceof EmployeePerformanceRecord)) {
            return null;
        }

        $invalidMetrics = [];
        $metrics = [
            'completion_rate' => $entity->completion_rate,
            'on_time_rate' => $entity->on_time_rate,
            'approval_rate' => $entity->approval_rate,
            'target_achievement_rate' => $entity->target_achievement_rate,
            'overall_score' => $entity->overall_score,
        ];

        foreach ($metrics as $name => $val) {
            if ($val !== null) {
                $f = (float)$val;
                if ($f < 0.0 || $f > 100.0) {
                    $invalidMetrics[$name] = $f;
                }
            }
        }

        if (!empty($invalidMetrics)) {
            return [
                'issue_type' => DataQualityIssueType::INVALID_KPI_VALUE,
                'severity' => $this->getSeverity(),
                'title' => "Performance record ID {$entity->id} has out-of-bounds KPI values",
                'description' => "KPI rates must mathematically range between 0.00% and 100.00%. Invalid values detected: " . json_encode($invalidMetrics),
                'detected_value' => $invalidMetrics,
                'expected_value' => ['valid_range' => '0.00 - 100.00'],
                'department_id' => null,
                'employee_id' => $entity->employee_id,
                'sub_key' => 'out_of_bounds_kpi',
            ];
        }

        return null;
    }
}
