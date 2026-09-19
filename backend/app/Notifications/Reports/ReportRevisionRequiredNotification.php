<?php

namespace App\Notifications\Reports;

use App\Models\Report;
use App\Models\ReportReview;
use App\Models\User;

class ReportRevisionRequiredNotification extends \App\Notifications\Tasks\JpcNotification
{
    use BuildsReportNotification;

    public function __construct(
        protected Report $report,
        protected User $recipient,
        protected ReportReview $review,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        return [
            'type' => 'report_revision_required',
            'title' => 'Report revision requested',
            'message' => "Your report {$this->reportCode($this->report)} needs revision. Review the feedback and upload a new version.",
            'data' => [
                'report_id' => $this->report->id,
                'task_id' => $this->report->task_id,
                'task_code' => $this->report->task?->task_code,
                'report_title' => $this->report->title,
                'decision' => $this->review->decision,
                'remarks' => $this->review->remarks,
                'reviewed_by' => $this->review->reviewer_id,
            ],
            'priority' => 'normal',
            'task_id' => $this->report->task_id,
            'reminder_days' => null,
        ];
    }
}