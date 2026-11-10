<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\MonthlyAchievement;

class AchievementConsistencyRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::MISSING_PERFORMANCE_DATA->value;
    }

    public function getName(): string
    {
        return 'Monthly Achievement Integrity & Period Reference';
    }

    public function getDescription(): string
    {
        return 'Validates that monthly achievement badges are linked to valid employees and legitimate performance periods.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::MEDIUM;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'achievement';
    }

    public function check(mixed $entity): ?array
    {
        if (! ($entity instanceof MonthlyAchievement)) {
            return null;
        }

        if (empty($entity->employee_id)) {
            return [
                'issue_type' => DataQualityIssueType::MISSING_PERFORMANCE_DATA,
                'severity' => DataQualityIssueSeverity::HIGH,
                'title' => "Monthly Achievement ID {$entity->id} has no linked employee",
                'description' => 'Achievement record is unassigned with NULL employee_id.',
                'detected_value' => ['employee_id' => null],
                'expected_value' => ['employee_id' => 'valid_employee_id'],
                'department_id' => $entity->department_id,
                'employee_id' => null,
            ];
        }

        return null;
    }
}
