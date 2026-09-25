<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\ManagementSummaryService;

class GetManagementSummaryTool implements AiToolInterface
{
    public function __construct(private readonly ManagementSummaryService $managementSummaryService) {}

    public function name(): string
    {
        return 'GetManagementSummaryTool';
    }

    public function description(): string
    {
        return 'Generates the grounded executive management summary for a month (narrative, key takeaways, metrics, month-over-month change and rating distribution). Admin/hr only.';
    }

    public function parameters(): array
    {
        return [
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
        $summary = $this->managementSummaryService->generateSummary(
            (int) ($params['year'] ?? now()->year),
            (int) ($params['month'] ?? now()->month),
            $user,
        );

        return [
            'tool_name' => $this->name(),
            'period' => $summary['period'],
            'period_label' => $summary['period_label'],
            'narrative' => $summary['narrative'],
            'key_takeaways' => $summary['key_takeaways'],
            'metrics' => $summary['metrics'],
            'change' => $summary['change'] ?? null,
            'has_data' => $summary['has_data'] ?? false,
        ];
    }
}