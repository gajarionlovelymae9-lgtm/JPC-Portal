<?php

namespace App\Services\DataQuality;

use App\Enums\DataQualityIssueStatus;
use App\Models\DataQualityIssue;
use App\Models\DataQualityIssueHistory;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;

class DataQualityResolutionService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {}

    public function review(DataQualityIssue $issue, User $reviewer, ?string $notes = null): DataQualityIssue
    {
        $prev = $issue->status->value;
        $issue->update([
            'status' => DataQualityIssueStatus::UNDER_REVIEW,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => Carbon::now(),
            'resolution_notes' => $notes,
        ]);

        DataQualityIssueHistory::create([
            'data_quality_issue_id' => $issue->id,
            'previous_status' => $prev,
            'new_status' => DataQualityIssueStatus::UNDER_REVIEW->value,
            'action' => 'reviewed',
            'performed_by' => $reviewer->id,
            'notes' => $notes,
        ]);

        $this->activityLogger->log(
            'DATA_QUALITY_ISSUE_REVIEWED',
            "Data quality issue #{$issue->id} marked under review by {$reviewer->name}",
            $reviewer,
            $issue
        );

        return $issue->fresh();
    }

    public function confirm(DataQualityIssue $issue, User $reviewer, ?string $notes = null): DataQualityIssue
    {
        $prev = $issue->status->value;
        $issue->update([
            'status' => DataQualityIssueStatus::CONFIRMED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => Carbon::now(),
            'resolution_notes' => $notes,
        ]);

        DataQualityIssueHistory::create([
            'data_quality_issue_id' => $issue->id,
            'previous_status' => $prev,
            'new_status' => DataQualityIssueStatus::CONFIRMED->value,
            'action' => 'confirmed',
            'performed_by' => $reviewer->id,
            'notes' => $notes,
        ]);

        $this->activityLogger->log(
            'DATA_QUALITY_ISSUE_CONFIRMED',
            "Data quality issue #{$issue->id} confirmed as valid defect by {$reviewer->name}",
            $reviewer,
            $issue
        );

        return $issue->fresh();
    }

    public function dismiss(DataQualityIssue $issue, User $reviewer, string $reason): DataQualityIssue
    {
        $prev = $issue->status->value;
        $issue->update([
            'status' => DataQualityIssueStatus::DISMISSED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => Carbon::now(),
            'resolution_notes' => $reason,
        ]);

        DataQualityIssueHistory::create([
            'data_quality_issue_id' => $issue->id,
            'previous_status' => $prev,
            'new_status' => DataQualityIssueStatus::DISMISSED->value,
            'action' => 'dismissed',
            'performed_by' => $reviewer->id,
            'notes' => $reason,
        ]);

        $this->activityLogger->log(
            'DATA_QUALITY_ISSUE_DISMISSED',
            "Data quality issue #{$issue->id} dismissed as false positive: {$reason}",
            $reviewer,
            $issue
        );

        return $issue->fresh();
    }

    public function resolve(DataQualityIssue $issue, User $resolver, string $correctiveActionNotes): DataQualityIssue
    {
        $prev = $issue->status->value;
        $issue->update([
            'status' => DataQualityIssueStatus::RESOLVED,
            'resolved_by' => $resolver->id,
            'resolved_at' => Carbon::now(),
            'resolution_notes' => $correctiveActionNotes,
        ]);

        DataQualityIssueHistory::create([
            'data_quality_issue_id' => $issue->id,
            'previous_status' => $prev,
            'new_status' => DataQualityIssueStatus::RESOLVED->value,
            'action' => 'resolved',
            'performed_by' => $resolver->id,
            'notes' => $correctiveActionNotes,
        ]);

        $this->activityLogger->log(
            'DATA_QUALITY_ISSUE_RESOLVED',
            "Data quality issue #{$issue->id} marked resolved by {$resolver->name}: {$correctiveActionNotes}",
            $resolver,
            $issue
        );

        return $issue->fresh();
    }
}
