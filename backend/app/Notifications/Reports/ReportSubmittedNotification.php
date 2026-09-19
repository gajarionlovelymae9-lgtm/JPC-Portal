<?php

namespace App\Notifications\Reports;

use App\Models\Report;
use App\Models\User;

class ReportSubmittedNotification extends \App\Notifications\Tasks\JpcNotification
{
    use BuildsReportNotification;

    public function __construct(
        protected Report $report,
        protected User $recipient,
    ) {}

    public function toJpcDatabase(object $notifiable): array
    {
        return [
            'type' => 'report_submitted',
            'title' => 'Report awaiting review',
            'message' => "{$this->employeeName($this->report)} submitted {$this->reportCode($this->report)} for review.",
            'data' => [
                'report_id' => $this->report->id,
                'task_id' => $this->report->task_id,
                'task_code' => $this->report->task?->task_code,
                'report_title' => $this->report->title,
                'submitted_by' => $this->employeeName($this->report),
            ],
            'priority' => 'normal',
            'task_id' => $this->report->task_id,
            'reminder_days' => null,
        ];
    }
}