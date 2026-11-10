<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\ReportingService;

class GetEmployeeProgressReportTool implements AiToolInterface
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function name(): string
    {
        return 'GetEmployeeProgressReportTool';
    }

    public function description(): string
    {
        return 'Generates the progress report for an employee (tasks, report completion, on-time, approval, targets, productivity and rating) for a period. Employees can only query themselves; department heads only their own staff.';
    }

    public function parameters(): array
    {
        return [
            'employee_id' => ['type' => 'integer', 'required' => false, 'description' => 'Employee to report on (defaults to the caller for employees).'],
            'year' => ['type' => 'integer', 'required' => false],
            'month' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'reports.view';
    }

    public function execute(array $params, User $user): array
    {
        $employeeId = (int) ($params['employee_id'] ?? $user->employee?->id ?? 0);

        $report = $this->reportingService->generateEmployeeReport(
            $employeeId,
            [
                'year' => $params['year'] ?? now()->year,
                'month' => $params['month'] ?? now()->month,
            ],
            $user,
        );

        return [
            'tool_name' => $this->name(),
            'period' => $report['period'],
            'employee' => $report['employee'],
            'metrics' => $report['metrics'],
            'source' => $report['source'],
            'has_data' => $report['has_data'] ?? false,
        ];
    }
}
