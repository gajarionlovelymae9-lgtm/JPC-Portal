<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Report;

class DuplicateReportRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::DUPLICATE_REPORT->value;
    }

    public function getName(): string
    {
        return 'Potential Duplicate Report Submission';
    }

    public function getDescription(): string
    {
        return 'Detects multiple reports submitted by the same employee for the same task within the same period.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::MEDIUM;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'report';
    }

    public function check(mixed $entity): ?array
    {
        if (! ($entity instanceof Report) || empty($entity->task_id) || empty($entity->employee_id)) {
            return null;
        }

        $duplicateCount = Report::query()
            ->where('id', '!=', $entity->id)
            ->where('task_id', $entity->task_id)
            ->where('employee_id', $entity->employee_id)
            ->where('title', $entity->title)
            ->count();

        if ($duplicateCount > 0) {
            return [
                'issue_type' => DataQualityIssueType::DUPLICATE_REPORT,
                'severity' => $this->getSeverity(),
                'title' => "Potential duplicate report: '{$entity->title}' for Task #{$entity->task_id}",
                'description' => "Found {$duplicateCount} other report(s) with identical title, task, and employee assignment.",
                'detected_value' => [
                    'title' => $entity->title,
                    'task_id' => $entity->task_id,
                    'duplicate_count' => $duplicateCount,
                ],
                'expected_value' => ['unique_report_per_task' => true],
                'department_id' => $entity->department_id,
                'employee_id' => $entity->employee_id,
                'sub_key' => "duplicate_report_{$entity->task_id}",
            ];
        }

        return null;
    }
}
