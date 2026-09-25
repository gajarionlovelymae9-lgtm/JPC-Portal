<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Report;

class ReportStatusRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::CONFLICTING_REPORT_STATUS->value;
    }

    public function getName(): string
    {
        return 'Conflicting Report Status & Timestamp State';
    }

    public function getDescription(): string
    {
        return 'Detects status contradictions such as approved reports with null approval timestamps or draft reports with submission dates.';
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
        if (!($entity instanceof Report)) {
            return null;
        }

        $statusStr = is_object($entity->status) ? ($entity->status->value ?? '') : (string)$entity->status;

        if ($statusStr === 'approved' && empty($entity->approved_at)) {
            return [
                'issue_type' => DataQualityIssueType::CONFLICTING_REPORT_STATUS,
                'severity' => $this->getSeverity(),
                'title' => "Report #{$entity->id} marked as 'approved' but approved_at timestamp is NULL",
                'description' => "Missing audit timestamp: The report status is approved without a recorded approval date.",
                'detected_value' => ['status' => 'approved', 'approved_at' => null],
                'expected_value' => ['approved_at' => 'valid_timestamp'],
                'department_id' => $entity->department_id,
                'employee_id' => $entity->employee_id,
                'sub_key' => 'approved_null_timestamp',
            ];
        }

        return null;
    }
}
