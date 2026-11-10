<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\ReportingService;

class GetDepartmentReportTool implements AiToolInterface
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function name(): string
    {
        return 'GetDepartmentReportTool';
    }

    public function description(): string
    {
        return 'Generates the department performance report (employees, task/report completion, on-time, approval, target achievement and average productivity). Department heads are locked to their own department.';
    }

    public function parameters(): array
    {
        return [
            'department_id' => ['type' => 'integer', 'required' => false, 'description' => 'Department to report on (required for admins/hr; dept heads default to their own).'],
            'year' => ['type' => 'integer', 'required' => false],
            'month' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'analytics.view';
    }

    public function execute(array $params, User $user): array
    {
        $departmentId = (int) ($params['department_id'] ?? ($user->employee?->department_id ?? 0));

        $report = $this->reportingService->generateDepartmentReport(
            $departmentId,
            [
                'year' => $params['year'] ?? now()->year,
                'month' => $params['month'] ?? now()->month,
            ],
            $user,
        );

        return [
            'tool_name' => $this->name(),
            'period' => $report['period'],
            'department' => $report['department'],
            'metrics' => $report['metrics'],
            'source' => $report['source'],
            'has_data' => $report['has_data'] ?? false,
        ];
    }
}
