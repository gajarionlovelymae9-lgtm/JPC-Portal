<?php

namespace App\Services\AI\Tools;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Models\User;
use App\Services\DataQuality\DataQualityIssueService;

class GetCriticalDataQualityIssuesTool implements AiToolInterface
{
    public function __construct(
        private readonly DataQualityIssueService $issueService
    ) {}

    public function name(): string
    {
        return 'GetCriticalDataQualityIssuesTool';
    }

    public function description(): string
    {
        return 'Retrieves active critical and high severity data quality issues requiring immediate attention.';
    }

    public function parameters(): array
    {
        return [
            'limit' => 'optional int',
        ];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function execute(array $params, User $user): array
    {
        $limit = min((int)($params['limit'] ?? 10), 20);
        $filters = [
            'severity' => DataQualityIssueSeverity::CRITICAL->value,
            'status' => DataQualityIssueStatus::OPEN->value,
        ];

        $paginator = $this->issueService->getFilteredIssues($user, $filters, $limit);

        $items = collect($paginator->items())->map(fn ($i) => [
            'id' => $i->id,
            'issue_type' => $i->issue_type->value ?? $i->issue_type,
            'severity' => $i->severity->value ?? $i->severity,
            'title' => $i->title,
            'description' => $i->description,
            'entity_type' => $i->entity_type,
            'department' => $i->department?->name,
            'employee' => $i->employee ? "{$i->employee->first_name} {$i->employee->last_name}" : null,
        ])->toArray();

        return [
            'tool_name' => $this->name(),
            'total_critical' => $paginator->total(),
            'data' => $items,
        ];
    }
}
