<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Task;
use App\Models\TaskAssignment;

class TaskAssignmentRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::TASK_WITHOUT_ASSIGNEE->value;
    }

    public function getName(): string
    {
        return 'Task Missing Assignee or Valid Assignment';
    }

    public function getDescription(): string
    {
        return 'Verifies that active tasks have at least one valid assigned employee and department relationship.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::HIGH;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'task';
    }

    public function check(mixed $entity): ?array
    {
        if (! ($entity instanceof Task)) {
            return null;
        }

        // Check if task has any assignments
        $assignmentCount = TaskAssignment::query()->where('task_id', $entity->id)->count();
        if ($assignmentCount === 0 && ! in_array($entity->status ?? 'draft', ['draft', 'cancelled'])) {
            return [
                'issue_type' => DataQualityIssueType::TASK_WITHOUT_ASSIGNEE,
                'severity' => $this->getSeverity(),
                'title' => "Task '{$entity->title}' (ID: {$entity->id}) has no assigned employee",
                'description' => 'The task is active/in-progress but has 0 records in task_assignments.',
                'detected_value' => ['assignment_count' => 0, 'task_status' => $entity->status],
                'expected_value' => ['min_assignment_count' => 1],
                'department_id' => $entity->department_id,
                'employee_id' => null,
            ];
        }

        return null;
    }
}
