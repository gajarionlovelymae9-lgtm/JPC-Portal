<?php

namespace App\Services\DataQuality\Scanners;

use App\Models\MonthlyAchievement;
use App\Services\DataQuality\DataQualityRuleRegistry;
use Closure;

class AchievementDataScanner
{
    public function __construct(
        private readonly DataQualityRuleRegistry $registry
    ) {}

    public function scan(?int $departmentId, ?int $employeeId, Closure $onIssueFound, Closure $onRecordChecked): void
    {
        $rules = $this->registry->getRulesFor('achievement');
        if (empty($rules)) {
            return;
        }

        $query = MonthlyAchievement::query();
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $query->chunk(100, function ($achievements) use ($rules, $onIssueFound, $onRecordChecked) {
            foreach ($achievements as $achievement) {
                $onRecordChecked();

                foreach ($rules as $rule) {
                    $issue = $rule->check($achievement);
                    if ($issue) {
                        $onIssueFound('achievement', $achievement->id, $issue);
                    }
                }
            }
        });
    }
}
