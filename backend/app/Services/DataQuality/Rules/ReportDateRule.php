<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Report;
use Carbon\Carbon;

class ReportDateRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::INVALID_REPORT_DATES->value;
    }

    public function getName(): string
    {
        return 'Impossible Report Date Sequence';
    }

    public function getDescription(): string
    {
        return 'Detects chronological contradictions in report lifecycle timestamps (e.g. approved_at before submitted_at).';
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

        if ($entity->submitted_at && $entity->approved_at) {
            $submitted = Carbon::parse($entity->submitted_at);
            $approved = Carbon::parse($entity->approved_at);

            if ($approved->lt($submitted)) {
                return [
                    'issue_type' => DataQualityIssueType::INVALID_REPORT_DATES,
                    'severity' => $this->getSeverity(),
                    'title' => "Report #{$entity->id} approval timestamp ({$approved->toDateTimeString()}) precedes submission timestamp ({$submitted->toDateTimeString()})",
                    'description' => "Logical anomaly: Report was recorded as approved before it was submitted by the employee.",
                    'detected_value' => [
                        'submitted_at' => $submitted->toDateTimeString(),
                        'approved_at' => $approved->toDateTimeString(),
                    ],
                    'expected_value' => ['approved_at_greater_than_or_equal_to_submitted_at' => true],
                    'department_id' => $entity->department_id,
                    'employee_id' => $entity->employee_id,
                    'sub_key' => 'approved_before_submitted',
                ];
            }
        }

        return null;
    }
}
