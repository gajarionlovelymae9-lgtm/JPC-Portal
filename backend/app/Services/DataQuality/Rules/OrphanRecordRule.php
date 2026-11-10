<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;

class OrphanRecordRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::ORPHAN_RECORD->value;
    }

    public function getName(): string
    {
        return 'Orphan Database Relationship Reference';
    }

    public function getDescription(): string
    {
        return 'Detects child records pointing to missing or soft-deleted parent entities.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::HIGH;
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['task_assignment', 'report'], true);
    }

    public function check(mixed $entity): ?array
    {
        if ($entity instanceof TaskAssignment) {
            $taskExists = Task::query()->where('id', $entity->task_id)->exists();
            if (! $taskExists) {
                return [
                    'issue_type' => DataQualityIssueType::ORPHAN_RECORD,
                    'severity' => $this->getSeverity(),
                    'title' => "Task Assignment ID {$entity->id} references missing Task ID {$entity->task_id}",
                    'description' => 'Orphan foreign key: Parent task was deleted or does not exist.',
                    'detected_value' => ['task_id' => $entity->task_id],
                    'expected_value' => ['task_exists' => true],
                    'department_id' => null,
                    'employee_id' => $entity->employee_id,
                    'sub_key' => "missing_task_{$entity->task_id}",
                ];
            }

            $empExists = Employee::query()->where('id', $entity->employee_id)->exists();
            if (! $empExists) {
                return [
                    'issue_type' => DataQualityIssueType::ORPHAN_RECORD,
                    'severity' => $this->getSeverity(),
                    'title' => "Task Assignment ID {$entity->id} references missing Employee ID {$entity->employee_id}",
                    'description' => 'Orphan foreign key: Parent employee was deleted or does not exist.',
                    'detected_value' => ['employee_id' => $entity->employee_id],
                    'expected_value' => ['employee_exists' => true],
                    'department_id' => null,
                    'employee_id' => null,
                    'sub_key' => "missing_employee_{$entity->employee_id}",
                ];
            }
        }

        return null;
    }
}
