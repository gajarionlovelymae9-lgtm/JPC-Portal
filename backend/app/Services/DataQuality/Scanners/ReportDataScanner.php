<?php

namespace App\Services\DataQuality\Scanners;

use App\Models\Report;
use App\Services\DataQuality\DataQualityRuleRegistry;
use Closure;

class ReportDataScanner
{
    public function __construct(
        private readonly DataQualityRuleRegistry $registry
    ) {}

    public function scan(?int $departmentId, ?int $employeeId, Closure $onIssueFound, Closure $onRecordChecked): void
    {
        $rules = $this->registry->getRulesFor('report');
        if (empty($rules)) {
            return;
        }

        $query = Report::query();
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $query->chunk(100, function ($reports) use ($rules, $onIssueFound, $onRecordChecked) {
            foreach ($reports as $report) {
                $onRecordChecked();

                foreach ($rules as $rule) {
                    $issue = $rule->check($report);
                    if ($issue) {
                        $onIssueFound('report', $report->id, $issue);
                    }
                }
            }
        });
    }
}
