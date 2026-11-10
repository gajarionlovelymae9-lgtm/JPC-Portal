<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\ReportingService;

class GetMonthlyPerformanceReportTool implements AiToolInterface
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function name(): string
    {
        return 'GetMonthlyPerformanceReportTool';
    }

    public function description(): string
    {
        return 'Generates the monthly performance report for a given year and month (top performer, department rankings, average productivity, performance change). Scoped to the caller role.';
    }

    public function parameters(): array
    {
        return [
            'year' => ['type' => 'integer', 'required' => false, 'description' => 'Calendar year (defaults to current).'],
            'month' => ['type' => 'integer', 'required' => false, 'description' => 'Month 1-12 (defaults to current).'],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'analytics.view';
    }

    public function execute(array $params, User $user): array
    {
        $year = (int) ($params['year'] ?? now()->year);
        $month = (int) ($params['month'] ?? now()->month);

        $report = $this->reportingService->generateMonthlyReport($year, $month, $user);

        return [
            'tool_name' => $this->name(),
            'period' => $report['period'],
            'scope' => $report['scope'],
            'summary' => $report['summary'] ?? null,
            'top_performer' => $report['top_performer'],
            'most_improved' => $report['most_improved'],
            'top_department' => $report['top_department'],
            'department_rankings' => array_slice($report['department_rankings'] ?? [], 0, 5),
            'has_data' => $report['has_data'] ?? false,
        ];
    }
}
