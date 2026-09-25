<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Employee;

class EmployeeRequiredFieldsRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::MISSING_REQUIRED_PROFILE_DATA->value;
    }

    public function getName(): string
    {
        return 'Missing Required Employee Profile Fields';
    }

    public function getDescription(): string
    {
        return 'Verifies that mandatory employee information (name, employee number, department, position, status) is present.';
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

        $missing = [];
        if (empty(trim($entity->first_name ?? ''))) $missing[] = 'first_name';
        if (empty(trim($entity->last_name ?? ''))) $missing[] = 'last_name';
        if (empty(trim($entity->employee_number ?? ''))) $missing[] = 'employee_number';
        if (empty($entity->department_id)) $missing[] = 'department_id';
        if (empty($entity->position_id)) $missing[] = 'position_id';
        if (empty($entity->employment_status)) $missing[] = 'employment_status';

        if (empty($missing)) {
            return null;
        }

        return [
            'issue_type' => DataQualityIssueType::MISSING_REQUIRED_PROFILE_DATA,
            'severity' => $this->getSeverity(),
            'title' => "Employee #{$entity->employee_number} is missing required fields (" . implode(', ', $missing) . ")",
            'description' => "Employee record has incomplete mandatory profile information. Missing: " . implode(', ', $missing),
            'detected_value' => ['missing_fields' => $missing],
            'expected_value' => ['all_required_fields_present' => true],
            'department_id' => $entity->department_id,
            'employee_id' => $entity->id,
            'sub_key' => implode('_', $missing),
        ];
    }
}
