<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\DataQuality\DataQualityStatisticsService;

class GetDataQualityStatisticsTool implements AiToolInterface
{
    public function __construct(
        private readonly DataQualityStatisticsService $statisticsService
    ) {}

    public function name(): string
    {
        return 'GetDataQualityStatisticsTool';
    }

    public function description(): string
    {
        return 'Retrieves overall data quality health score, open issues count, severity breakdown, and department stats within authorized scope.';
    }

    public function parameters(): array
    {
        return [];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function execute(array $params, User $user): array
    {
        $stats = $this->statisticsService->getDashboardStatistics($user);

        return [
            'tool_name' => $this->name(),
            'score' => $stats['score'],
            'total_issues' => $stats['total_issues'],
            'open_issues' => $stats['open_issues'],
            'critical' => $stats['critical'],
            'high' => $stats['high'],
            'medium' => $stats['medium'],
            'low' => $stats['low'],
            'resolved' => $stats['resolved'],
            'by_category' => $stats['by_category'],
        ];
    }
}
