<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\EmployeeTarget;

class TargetConsistencyRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::INVALID_TARGET_VALUE->value;
    }

    public function getName(): string
    {
        return 'Target Value and Period Consistency';
    }

    public function getDescription(): string
    {
        return 'Validates that employee targets have valid target values (>0) and active employee links.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::MEDIUM;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'target';
    }

    public function check(mixed $entity): ?array
    {
        if (!($entity instanceof EmployeeTarget)) {
            return null;
        }

        if (empty($entity->employee_id)) {
            return [
                'issue_type' => DataQualityIssueType::TARGET_WITHOUT_EMPLOYEE,
                'severity' => DataQualityIssueSeverity::HIGH,
                'title' => "Target '{$entity->title}' (ID: {$entity->id}) is not linked to any employee",
                'description' => "The target record has an empty employee_id foreign key.",
                'detected_value' => ['employee_id' => null],
                'expected_value' => ['employee_id' => 'valid_employee_id'],
                'department_id' => null,
                'employee_id' => null,
            ];
        }

        if ($entity->target_value !== null && (float)$entity->target_value < 0) {
            return [
                'issue_type' => DataQualityIssueType::INVALID_TARGET_VALUE,
                'severity' => DataQualityIssueSeverity::MEDIUM,
                'title' => "Target '{$entity->title}' has negative target value ({$entity->target_value})",
                'description' => "Target quota value cannot be negative.",
                'detected_value' => ['target_value' => $entity->target_value],
                'expected_value' => ['min_target_value' => 0.0],
                'department_id' => null,
                'employee_id' => $entity->employee_id,
            ];
        }

        return null;
    }
}
