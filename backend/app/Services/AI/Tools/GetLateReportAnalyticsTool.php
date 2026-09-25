<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\ReportingService;

class GetLateReportAnalyticsTool implements AiToolInterface
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function name(): string
    {
        return 'GetLateReportAnalyticsTool';
    }

    public function description(): string
    {
        return 'Analytics on late report submissions for a period: totals, late rate, per-employee/per-department breakdowns and month-over-month change, scoped to the caller role.';
    }

    public function parameters(): array
    {
        return [
            'year' => ['type' => 'integer', 'required' => false],
            'month' => ['type' => 'integer', 'required' => false],
            'department_id' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'analytics.view';
    }

    public function execute(array $params, User $user): array
    {
        $report = $this->reportingService->generateLateReport($params, $user);

        return [
            'tool_name' => $this->name(),
            'period' => $report['period'],
            'summary' => $report['summary'],
            'comparison' => $report['comparison'],
            'by_employee' => array_slice($report['by_employee'] ?? [], 0, 10),
            'by_department' => $report['by_department'],
            'has_data' => $report['has_data'] ?? false,
        ];
    }
}