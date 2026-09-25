<?php

namespace App\Services\DataQuality;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualityScanStatus;
use App\Models\DataQualityIssue;
use App\Models\DataQualityIssueHistory;
use App\Models\DataQualityScan;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\DataQuality\Scanners\AchievementDataScanner;
use App\Services\DataQuality\Scanners\EmployeeDataScanner;
use App\Services\DataQuality\Scanners\PerformanceDataScanner;
use App\Services\DataQuality\Scanners\ReportDataScanner;
use App\Services\DataQuality\Scanners\TargetDataScanner;
use App\Services\DataQuality\Scanners\TaskDataScanner;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataQualityScanner
{
    public function __construct(
        private readonly EmployeeDataScanner $employeeScanner,
        private readonly TaskDataScanner $taskScanner,
        private readonly TargetDataScanner $targetScanner,
        private readonly ReportDataScanner $reportScanner,
        private readonly PerformanceDataScanner $performanceScanner,
        private readonly AchievementDataScanner $achievementScanner,
        private readonly ActivityLogger $activityLogger
    ) {}

    public function runScan(string $scanType = 'full', ?array $scope = null, ?User $initiatedBy = null): DataQualityScan
    {
        $scan = DataQualityScan::create([
            'scan_type' => $scanType,
            'scope' => $scope,
            'status' => DataQualityScanStatus::RUNNING,
            'started_at' => Carbon::now(),
            'initiated_by' => $initiatedBy?->id,
        ]);

        $this->activityLogger->log(
            'DATA_QUALITY_SCAN_STARTED',
            "Started {$scanType} data quality scan #{$scan->id}",
            $initiatedBy,
            $scan
        );

        $departmentId = $scope['department_id'] ?? null;
        $employeeId = $scope['employee_id'] ?? null;

        $recordsChecked = 0;
        $issuesFound = 0;
        $criticalCount = 0;
        $highCount = 0;
        $mediumCount = 0;
        $lowCount = 0;

        $onRecordChecked = function () use (&$recordsChecked) {
            $recordsChecked++;
        };

        $onIssueFound = function (string $entityType, int|string $entityId, array $issueData) use (
            $scan,
            &$issuesFound,
            &$criticalCount,
            &$highCount,
            &$mediumCount,
            &$lowCount
        ) {
            $issuesFound++;
            $severity = $issueData['severity'] instanceof DataQualityIssueSeverity
                ? $issueData['severity']
                : DataQualityIssueSeverity::from($issueData['severity']);

            match ($severity) {
                DataQualityIssueSeverity::CRITICAL => $criticalCount++,
                DataQualityIssueSeverity::HIGH => $highCount++,
                DataQualityIssueSeverity::MEDIUM => $mediumCount++,
                DataQualityIssueSeverity::LOW => $lowCount++,
            };

            $fingerprint = DataQualityIssue::generateFingerprint(
                $issueData['issue_type']->value ?? (string)$issueData['issue_type'],
                $entityType,
                $entityId,
                $issueData['sub_key'] ?? null
            );

            // Idempotent upsert
            $existing = DataQualityIssue::where('fingerprint', $fingerprint)->first();

            if ($existing) {
                // If previously dismissed/resolved, keep its status unless reopened
                $existing->update([
                    'scan_id' => $scan->id,
                    'title' => $issueData['title'],
                    'description' => $issueData['description'],
                    'detected_value' => $issueData['detected_value'] ?? null,
                    'expected_value' => $issueData['expected_value'] ?? null,
                    'metadata' => $issueData['metadata'] ?? null,
                ]);
            } else {
                $created = DataQualityIssue::create([
                    'scan_id' => $scan->id,
                    'issue_type' => $issueData['issue_type'],
                    'severity' => $severity,
                    'status' => DataQualityIssueStatus::OPEN,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'department_id' => $issueData['department_id'] ?? null,
                    'employee_id' => $issueData['employee_id'] ?? null,
                    'title' => $issueData['title'],
                    'description' => $issueData['description'],
                    'detected_value' => $issueData['detected_value'] ?? null,
                    'expected_value' => $issueData['expected_value'] ?? null,
                    'detected_by' => 'automated_rule',
                    'metadata' => $issueData['metadata'] ?? null,
                    'fingerprint' => $fingerprint,
                ]);

                DataQualityIssueHistory::create([
                    'data_quality_issue_id' => $created->id,
                    'previous_status' => null,
                    'new_status' => DataQualityIssueStatus::OPEN->value,
                    'action' => 'created',
                    'performed_by' => $scan->initiated_by,
                    'notes' => 'Issue detected automatically during scan #' . $scan->id,
                ]);
            }
        };

        try {
            DB::transaction(function () use (
                $departmentId,
                $employeeId,
                $onIssueFound,
                $onRecordChecked
            ) {
                $this->employeeScanner->scan($departmentId, $employeeId, $onIssueFound, $onRecordChecked);
                $this->taskScanner->scan($departmentId, $employeeId, $onIssueFound, $onRecordChecked);
                $this->targetScanner->scan($departmentId, $employeeId, $onIssueFound, $onRecordChecked);
                $this->reportScanner->scan($departmentId, $employeeId, $onIssueFound, $onRecordChecked);
                $this->performanceScanner->scan($departmentId, $employeeId, $onIssueFound, $onRecordChecked);
                $this->achievementScanner->scan($departmentId, $employeeId, $onIssueFound, $onRecordChecked);
            });

            $scan->update([
                'status' => DataQualityScanStatus::COMPLETED,
                'completed_at' => Carbon::now(),
                'records_checked' => $recordsChecked,
                'issues_found' => $issuesFound,
                'critical_count' => $criticalCount,
                'high_count' => $highCount,
                'medium_count' => $mediumCount,
                'low_count' => $lowCount,
            ]);

            $this->activityLogger->log(
                'DATA_QUALITY_SCAN_COMPLETED',
                "Completed data quality scan #{$scan->id}: checked {$recordsChecked} records, found {$issuesFound} issues.",
                $initiatedBy,
                $scan
            );

        } catch (Exception $e) {
            Log::error("DataQuality scan #{$scan->id} failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $scan->update([
                'status' => DataQualityScanStatus::FAILED,
                'completed_at' => Carbon::now(),
                'error_message' => $e->getMessage(),
            ]);
        }

        return $scan;
    }
}
