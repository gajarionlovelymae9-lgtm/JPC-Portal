<?php

namespace App\Services\DataQuality\Scanners;

use App\Models\EmployeeTarget;
use App\Services\DataQuality\DataQualityRuleRegistry;
use Closure;

class TargetDataScanner
{
    public function __construct(
        private readonly DataQualityRuleRegistry $registry
    ) {}

    public function scan(?int $departmentId, ?int $employeeId, Closure $onIssueFound, Closure $onRecordChecked): void
    {
        $rules = $this->registry->getRulesFor('target');
        if (empty($rules)) {
            return;
        }

        $query = EmployeeTarget::query();
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $query->chunk(100, function ($targets) use ($rules, $onIssueFound, $onRecordChecked) {
            foreach ($targets as $target) {
                $onRecordChecked();

                foreach ($rules as $rule) {
                    $issue = $rule->check($target);
                    if ($issue) {
                        $onIssueFound('target', $target->id, $issue);
                    }
                }
            }
        });
    }
}
