<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Department;
use App\Models\Employee;

class InvalidDepartmentRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::INVALID_DEPARTMENT->value;
    }

    public function getName(): string
    {
        return 'Invalid or Inactive Department Reference';
    }

    public function getDescription(): string
    {
        return 'Checks if the employee or task is associated with a non-existent or inactive department.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::HIGH;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'employee';
    }

    public function check(mixed $entity): ?array
    {
        if (!($entity instanceof Employee)) {
            return null;
        }

        if (empty($entity->department_id)) {
            return [
                'issue_type' => DataQualityIssueType::INVALID_DEPARTMENT,
                'severity' => $this->getSeverity(),
                'title' => "Employee #{$entity->employee_number} has no assigned department",
                'description' => "The employee profile does not specify any valid department_id.",
                'detected_value' => ['department_id' => null],
                'expected_value' => ['department_id' => 'valid_active_department_id'],
                'department_id' => null,
                'employee_id' => $entity->id,
            ];
        }

        $deptExists = Department::query()->where('id', $entity->department_id)->exists();
        if (!$deptExists) {
            return [
                'issue_type' => DataQualityIssueType::INVALID_DEPARTMENT_REFERENCE,
                'severity' => DataQualityIssueSeverity::CRITICAL,
                'title' => "Employee #{$entity->employee_number} points to non-existent Department ID {$entity->department_id}",
                'description' => "Foreign key reference points to a department that does not exist in the database.",
                'detected_value' => ['department_id' => $entity->department_id],
                'expected_value' => ['exists_in_departments_table' => true],
                'department_id' => null,
                'employee_id' => $entity->id,
            ];
        }

        return null;
    }
}
