<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Task;
use Carbon\Carbon;

class TaskDateRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::DUE_DATE_BEFORE_START_DATE->value;
    }

    public function getName(): string
    {
        return 'Task Due Date Before Start Date';
    }

    public function getDescription(): string
    {
        return 'Detects chronological anomalies such as task due dates occurring earlier than start dates.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::CRITICAL;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'task';
    }

    public function check(mixed $entity): ?array
    {
        if (!($entity instanceof Task)) {
            return null;
        }

        if ($entity->start_date && $entity->due_date) {
            $start = Carbon::parse($entity->start_date);
            $due = Carbon::parse($entity->due_date);

            if ($due->lt($start)) {
                return [
                    'issue_type' => DataQualityIssueType::DUE_DATE_BEFORE_START_DATE,
                    'severity' => $this->getSeverity(),
                    'title' => "Task '{$entity->title}' due date ({$due->toDateString()}) is before start date ({$start->toDateString()})",
                    'description' => "Chronological sequence contradiction: deadline is scheduled prior to work start date.",
                    'detected_value' => [
                        'start_date' => $start->toDateString(),
                        'due_date' => $due->toDateString(),
                    ],
                    'expected_value' => ['due_date_greater_or_equal_to_start' => true],
                    'department_id' => $entity->department_id,
                    'employee_id' => null,
                    'sub_key' => 'due_before_start',
                ];
            }
        }

        return null;
    }
}
