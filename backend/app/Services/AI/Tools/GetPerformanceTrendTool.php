<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\AnalyticsService;

class GetPerformanceTrendTool implements AiToolInterface
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function name(): string
    {
        return 'GetPerformanceTrendTool';
    }

    public function description(): string
    {
        return 'Returns the month-by-month productivity/completion trend for the last N months within the caller scope (employees see their own, dept heads their department).';
    }

    public function parameters(): array
    {
        return [
            'months' => ['type' => 'integer', 'required' => false, 'description' => 'Window length (1-12, default 6).'],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'reports.view';
    }

    public function execute(array $params, User $user): array
    {
        $months = (int) ($params['months'] ?? config('reports.trend_months', 6));

        return [
            'tool_name' => $this->name(),
            'trend' => $this->analyticsService->getPerformanceTrend($user, [], $months),
        ];
    }
}