<?php

namespace App\Services\DataQuality\Scanners;

use App\Models\EmployeePerformanceRecord;
use App\Services\DataQuality\DataQualityRuleRegistry;
use Closure;

class PerformanceDataScanner
{
    public function __construct(
        private readonly DataQualityRuleRegistry $registry
    ) {}

    public function scan(?int $departmentId, ?int $employeeId, Closure $onIssueFound, Closure $onRecordChecked): void
    {
        $rules = $this->registry->getRulesFor('performance');
        if (empty($rules)) {
            return;
        }

        $query = EmployeePerformanceRecord::query();
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $query->chunk(100, function ($records) use ($rules, $onIssueFound, $onRecordChecked) {
            foreach ($records as $record) {
                $onRecordChecked();

                foreach ($rules as $rule) {
                    $issue = $rule->check($record);
                    if ($issue) {
                        $onIssueFound('performance', $record->id, $issue);
                    }
                }
            }
        });
    }
}
