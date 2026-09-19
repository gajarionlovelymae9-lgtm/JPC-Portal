<?php

namespace App\Notifications\Reports;

use App\Models\Report;

/**
 * Shared message helpers for report workflow notifications (Phase 3.3-K).
 */
trait BuildsReportNotification
{
    private function reportCode(Report $report): string
    {
        return $report->task?->task_code ?? "Report #{$report->id}";
    }

    private function employeeName(Report $report): string
    {
        return $report->employee?->full_name ?? "Employee #{$report->employee_id}";
    }

    private function selfServiced($recipient): bool
    {
        return method_exists($recipient, 'roleName') && $recipient->roleName() === 'employee';
    }
}