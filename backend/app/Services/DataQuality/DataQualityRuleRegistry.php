<?php

namespace App\Services\DataQuality;

use App\Services\DataQuality\Rules\AchievementConsistencyRule;
use App\Services\DataQuality\Rules\DataQualityRuleInterface;
use App\Services\DataQuality\Rules\DuplicateEmployeeRule;
use App\Services\DataQuality\Rules\DuplicateReportRule;
use App\Services\DataQuality\Rules\EmployeeRequiredFieldsRule;
use App\Services\DataQuality\Rules\InvalidDepartmentRule;
use App\Services\DataQuality\Rules\OcrQualityRule;
use App\Services\DataQuality\Rules\OrphanRecordRule;
use App\Services\DataQuality\Rules\PerformanceConsistencyRule;
use App\Services\DataQuality\Rules\ReportAssignmentRule;
use App\Services\DataQuality\Rules\ReportDateRule;
use App\Services\DataQuality\Rules\ReportStatusRule;
use App\Services\DataQuality\Rules\TargetConsistencyRule;
use App\Services\DataQuality\Rules\TaskAssignmentRule;
use App\Services\DataQuality\Rules\TaskDateRule;

class DataQualityRuleRegistry
{
    /** @var array<string, list<DataQualityRuleInterface>> */
    private array $rulesByEntityType = [];

    public function __construct()
    {
        $this->registerDefaultRules();
    }

    private function registerDefaultRules(): void
    {
        $rules = [
            new EmployeeRequiredFieldsRule(),
            new DuplicateEmployeeRule(),
            new InvalidDepartmentRule(),
            new TaskAssignmentRule(),
            new TaskDateRule(),
            new TargetConsistencyRule(),
            new ReportAssignmentRule(),
            new ReportDateRule(),
            new ReportStatusRule(),
            new DuplicateReportRule(),
            new PerformanceConsistencyRule(),
            new AchievementConsistencyRule(),
            new OcrQualityRule(),
            new OrphanRecordRule(),
        ];

        foreach ($rules as $rule) {
            $this->register($rule);
        }
    }

    public function register(DataQualityRuleInterface $rule): void
    {
        $supportedTypes = ['employee', 'task', 'target', 'report', 'performance', 'achievement', 'task_assignment'];

        foreach ($supportedTypes as $type) {
            if ($rule->supports($type)) {
                $this->rulesByEntityType[$type][] = $rule;
            }
        }
    }

    /**
     * @return list<DataQualityRuleInterface>
     */
    public function getRulesFor(string $entityType): array
    {
        return $this->rulesByEntityType[$entityType] ?? [];
    }
}
