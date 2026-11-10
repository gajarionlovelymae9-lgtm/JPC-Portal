<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\DataQuality\DataQualityStatisticsService;

class GetDataQualityTrendsTool implements AiToolInterface
{
    public function __construct(
        private readonly DataQualityStatisticsService $statisticsService
    ) {}

    public function name(): string
    {
        return 'GetDataQualityTrendsTool';
    }

    public function description(): string
    {
        return 'Retrieves monthly data quality issue detection and resolution trends.';
    }

    public function parameters(): array
    {
        return [
            'months' => 'optional int: number of months to analyze (default 6)',
        ];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function execute(array $params, User $user): array
    {
        $months = (int) ($params['months'] ?? 6);
        $trends = $this->statisticsService->getMonthlyTrends($user, $months);

        return [
            'tool_name' => $this->name(),
            'months' => $months,
            'trends' => $trends,
        ];
    }
}
