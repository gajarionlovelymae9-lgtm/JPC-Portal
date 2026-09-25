<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Report;
use App\Models\TaskAssignment;

class ReportAssignmentRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::REPORT_TASK_ASSIGNMENT_MISMATCH->value;
    }

    public function getName(): string
    {
        return 'Report Submitter / Task Assignee Mismatch';
    }

    public function getDescription(): string
    {
        return 'Verifies that the employee who submitted a report is officially assigned to the associated task.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::HIGH;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'report';
    }

    public function check(mixed $entity): ?array
    {
        if (!($entity instanceof Report)) {
            return null;
        }

        if (empty($entity->task_id)) {
            return [
                'issue_type' => DataQualityIssueType::REPORT_WITHOUT_TASK,
                'severity' => DataQualityIssueSeverity::HIGH,
                'title' => "Report '{$entity->title}' (ID: {$entity->id}) is not linked to any task",
                'description' => "Report record is missing mandatory task_id relationship.",
                'detected_value' => ['task_id' => null],
                'expected_value' => ['task_id' => 'valid_task_id'],
                'department_id' => $entity->department_id,
                'employee_id' => $entity->employee_id,
            ];
        }

        if ($entity->task_id && $entity->employee_id) {
            $isAssigned = TaskAssignment::query()
                ->where('task_id', $entity->task_id)
                ->where('employee_id', $entity->employee_id)
                ->exists();

            if (!$isAssigned) {
                return [
                    'issue_type' => DataQualityIssueType::REPORT_TASK_ASSIGNMENT_MISMATCH,
                    'severity' => $this->getSeverity(),
                    'title' => "Report #{$entity->id} submitter (Employee #{$entity->employee_id}) is not assigned to Task #{$entity->task_id}",
                    'description' => "Cross-assignment inconsistency: The employee submitted a progress report for a task they are not officially assigned to.",
                    'detected_value' => [
                        'report_employee_id' => $entity->employee_id,
                        'task_id' => $entity->task_id,
                    ],
                    'expected_value' => ['assigned_to_task' => true],
                    'department_id' => $entity->department_id,
                    'employee_id' => $entity->employee_id,
                    'sub_key' => "mismatch_{$entity->employee_id}_{$entity->task_id}",
                ];
            }
        }

        return null;
    }
}
