<?php

namespace App\Services\DataQuality\Scanners;

use App\Models\Employee;
use App\Services\DataQuality\DataQualityRuleRegistry;
use Closure;

class EmployeeDataScanner
{
    public function __construct(
        private readonly DataQualityRuleRegistry $registry
    ) {}

    public function scan(?int $departmentId, ?int $employeeId, Closure $onIssueFound, Closure $onRecordChecked): void
    {
        $rules = $this->registry->getRulesFor('employee');
        if (empty($rules)) {
            return;
        }

        $query = Employee::query();
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($employeeId) {
            $query->where('id', $employeeId);
        }

        $query->chunk(100, function ($employees) use ($rules, $onIssueFound, $onRecordChecked) {
            foreach ($employees as $employee) {
                $onRecordChecked();

                foreach ($rules as $rule) {
                    $issue = $rule->check($employee);
                    if ($issue) {
                        $onIssueFound('employee', $employee->id, $issue);
                    }
                }
            }
        });
    }
}
