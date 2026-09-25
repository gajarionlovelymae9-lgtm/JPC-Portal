<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Reporting\AnalyticsService;

class GetRankingTool implements AiToolInterface
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function name(): string
    {
        return 'GetRankingTool';
    }

    public function description(): string
    {
        return 'Returns the top performers leaderboard for a period and metric (productivity, completion_rate, on_time_rate, approval_rate, target_achievement), scoped to the caller role.';
    }

    public function parameters(): array
    {
        return [
            'year' => ['type' => 'integer', 'required' => false],
            'month' => ['type' => 'integer', 'required' => false],
            'metric' => ['type' => 'string', 'required' => false, 'description' => 'productivity | completion_rate | on_time_rate | approval_rate | target_achievement'],
            'limit' => ['type' => 'integer', 'required' => false, 'description' => 'Max rows (1-10, default 5).'],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'analytics.view';
    }

    public function execute(array $params, User $user): array
    {
        $filters = [
            'year' => $params['year'] ?? now()->year,
            'month' => $params['month'] ?? now()->month,
            'limit' => $params['limit'] ?? config('reports.top_performers_limit', 5),
        ];

        $metric = (string) ($params['metric'] ?? 'productivity');

        return [
            'tool_name' => $this->name(),
            'metric' => $metric,
            'ranking' => $this->analyticsService->getRankings($user, $filters, $metric),
        ];
    }
}