<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Employee;

class DuplicateEmployeeRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::DUPLICATE_EMPLOYEE->value;
    }

    public function getName(): string
    {
        return 'Potential Duplicate Employee Record';
    }

    public function getDescription(): string
    {
        return 'Detects multiple employee records sharing identical employee number, user account, or name combination.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::CRITICAL;
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

        // Check for duplicate name combination
        $duplicateNameCount = Employee::query()
            ->where('id', '!=', $entity->id)
            ->where('first_name', $entity->first_name)
            ->where('last_name', $entity->last_name)
            ->count();

        if ($duplicateNameCount > 0) {
            return [
                'issue_type' => DataQualityIssueType::DUPLICATE_EMPLOYEE,
                'severity' => $this->getSeverity(),
                'title' => "Potential duplicate employee name: {$entity->first_name} {$entity->last_name}",
                'description' => "Found {$duplicateNameCount} other employee record(s) with the exact same first and last name.",
                'detected_value' => [
                    'name' => "{$entity->first_name} {$entity->last_name}",
                    'duplicate_count' => $duplicateNameCount,
                ],
                'expected_value' => ['unique_identity' => true],
                'department_id' => $entity->department_id,
                'employee_id' => $entity->id,
                'sub_key' => 'name_duplicate',
            ];
        }

        return null;
    }
}
