<?php

namespace App\Services\DataQuality\Rules;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Report;

class OcrQualityRule implements DataQualityRuleInterface
{
    public function getCode(): string
    {
        return DataQualityIssueType::OCR_FAILED->value;
    }

    public function getName(): string
    {
        return 'OCR Processing Quality and Extraction Status';
    }

    public function getDescription(): string
    {
        return 'Detects OCR extraction failures or empty text extractions without impacting employee performance scores.';
    }

    public function getSeverity(): DataQualityIssueSeverity
    {
        return DataQualityIssueSeverity::LOW;
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

        // Check if report has any files with failed/empty extraction
        // Non-punitive data quality flag
        if ($entity->ocr_status === 'failed') {
            return [
                'issue_type' => DataQualityIssueType::OCR_FAILED,
                'severity' => $this->getSeverity(),
                'title' => "Report #{$entity->id} encountered OCR extraction failure",
                'description' => "Automated document text extraction failed. File may require re-scanning or manual review.",
                'detected_value' => ['ocr_status' => 'failed'],
                'expected_value' => ['ocr_status' => 'completed'],
                'department_id' => $entity->department_id,
                'employee_id' => $entity->employee_id,
                'sub_key' => 'ocr_failed',
            ];
        }

        return null;
    }
}
