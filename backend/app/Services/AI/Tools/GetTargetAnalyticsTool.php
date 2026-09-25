<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\ReportingService;

class GetTargetAnalyticsTool implements AiToolInterface
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function name(): string
    {
        return 'GetTargetAnalyticsTool';
    }

    public function description(): string
    {
        return 'Target analytics for a period: totals, achieved/partial/unachieved counts, achievement rate and per-department breakdown, scoped to the caller role.';
    }

    public function parameters(): array
    {
        return [
            'year' => ['type' => 'integer', 'required' => false],
            'month' => ['type' => 'integer', 'required' => false],
            'department_id' => ['type' => 'integer', 'required' => false],
            'employee_id' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'reports.view';
    }

    public function execute(array $params, User $user): array
    {
        $report = $this->reportingService->generateTargetReport($params, $user);

        return [
            'tool_name' => $this->name(),
            'period' => $report['period'],
            'total_targets' => $report['total_targets'],
            'achieved_targets' => $report['achieved_targets'],
            'partially_achieved' => $report['partially_achieved'],
            'unachieved' => $report['unachieved'],
            'achievement_rate' => $report['achievement_rate'],
            'by_department' => $report['by_department'],
            'has_data' => $report['has_data'] ?? false,
        ];
    }
}