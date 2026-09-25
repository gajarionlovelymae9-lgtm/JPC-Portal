<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\DataQuality\DataQualityIssueService;

class GetDataQualityIssuesTool implements AiToolInterface
{
    public function __construct(
        private readonly DataQualityIssueService $issueService
    ) {}

    public function name(): string
    {
        return 'GetDataQualityIssuesTool';
    }

    public function description(): string
    {
        return 'Retrieves a list of detected data quality issues filtered by severity, status, or category within authorized scope.';
    }

    public function parameters(): array
    {
        return [
            'severity' => 'optional string: LOW, MEDIUM, HIGH, CRITICAL',
            'status' => 'optional string: OPEN, UNDER_REVIEW, CONFIRMED, DISMISSED, RESOLVED',
            'limit' => 'optional int',
        ];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function execute(array $params, User $user): array
    {
        $limit = min((int)($params['limit'] ?? 10), 50);
        $paginator = $this->issueService->getFilteredIssues($user, $params, $limit);

        $items = collect($paginator->items())->map(fn ($i) => [
            'id' => $i->id,
            'issue_type' => $i->issue_type->value ?? $i->issue_type,
            'severity' => $i->severity->value ?? $i->severity,
            'status' => $i->status->value ?? $i->status,
            'entity_type' => $i->entity_type,
            'title' => $i->title,
            'description' => $i->description,
            'department' => $i->department?->name,
            'employee' => $i->employee ? "{$i->employee->first_name} {$i->employee->last_name}" : null,
            'created_at' => $i->created_at?->toDateString(),
        ])->toArray();

        return [
            'tool_name' => $this->name(),
            'total' => $paginator->total(),
            'count' => count($items),
            'data' => $items,
        ];
    }
}
