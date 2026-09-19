<?php

namespace App\Notifications\Reports;

use App\Models\Report;
use App\Models\User;

class ReportReadyForReviewNotification extends \App\Notifications\Tasks\JpcNotification
{
    use BuildsReportNotification;

    public function __construct(
        protected Report $report,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        return [
            'type' => 'report_ready_review',
            'title' => 'Report ready for review',
            'message' => "AI processing finished for {$this->reportCode($this->report)} — {$this->report->title}. Ready for manual review.",
            'data' => [
                'report_id' => $this->report->id,
                'task_id' => $this->report->task_id,
                'task_code' => $this->report->task?->task_code,
                'report_title' => $this->report->title,
                'submitted_by' => $this->employeeName($this->report),
                'advisory_only' => true,
            ],
            'priority' => 'normal',
            'task_id' => $this->report->task_id,
            'reminder_days' => null,
        ];
    }
}