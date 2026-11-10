<?php

namespace App\Services\Escalations;

use App\Models\DepartmentEscalation;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Shapes escalation records, their audit trail and their summary counters for
 * the API. Shared by the Department Head and Administration services so both
 * portals always render identical payloads.
 */
class EscalationPresenter
{
    /**
     * Summary counters used by the dashboard cards.
     *
     * @return array<string, int>
     */
    public function summary(?int $departmentId = null): array
    {
        $query = DepartmentEscalation::query();

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        $counts = $query
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pending = (int) ($counts['pending'] ?? 0);
        $underReview = (int) ($counts['under_review'] ?? 0);
        $actionRequired = (int) ($counts['action_required'] ?? 0);
        $resolved = (int) ($counts['resolved'] ?? 0);
        $rejected = (int) ($counts['rejected'] ?? 0);

        return [
            'total' => $pending + $underReview + $actionRequired + $resolved + $rejected,
            'pending' => $pending,
            'under_review' => $underReview,
            'action_required' => $actionRequired,
            'resolved' => $resolved,
            'rejected' => $rejected,
            'open' => $pending + $underReview + $actionRequired,
        ];
    }

    /**
     * Transform a single escalation into its API representation.
     *
     * @return array<string, mixed>
     */
    public function transform(DepartmentEscalation $escalation): array
    {
        $relatedType = $escalation->related_type
            ? array_search($escalation->related_type, DepartmentEscalation::RELATED_TYPES, true)
            : null;

        return [
            'id' => $escalation->id,
            'reference' => 'ESC-'.str_pad((string) $escalation->id, 5, '0', STR_PAD_LEFT),
            'title' => $escalation->title,
            'type' => $escalation->type->value,
            'type_label' => $escalation->type->label(),
            'reason' => $escalation->reason,
            'requested_action' => $escalation->requested_action,
            'priority' => $escalation->priority->value,
            'priority_label' => $escalation->priority->label(),
            'status' => $escalation->status->value,
            'status_label' => $escalation->status->label(),
            'department_id' => $escalation->department_id,
            'department_name' => $escalation->department?->name,
            'employee_id' => $escalation->employee_id,
            'employee_name' => $escalation->employee?->full_name,
            'employee_number' => $escalation->employee?->employee_number,
            'related_type' => $relatedType ?: null,
            'related_id' => $escalation->related_id,
            'related_label' => $this->relatedLabel($escalation),
            'assigned_to' => $escalation->assigned_to,
            'assigned_to_name' => $escalation->assignee?->name,
            'assigned_at' => $escalation->assigned_at?->toIso8601String(),
            'reviewed_by_name' => $escalation->reviewer?->name,
            'reviewed_at' => $escalation->reviewed_at?->toIso8601String(),
            'resolution_notes' => $escalation->resolution_notes,
            'resolver_name' => $escalation->resolver?->name,
            'resolved_at' => $escalation->resolved_at?->toIso8601String(),
            'attachment' => $escalation->attachment_path
                ? ['name' => $escalation->attachment_name ?? 'attachment']
                : null,
            'created_by_name' => $escalation->creator?->name,
            'created_at' => $escalation->created_at?->toIso8601String(),
        ];
    }

    /**
     * Transform the audit trail.
     *
     * @return list<array<string, mixed>>
     */
    public function history(DepartmentEscalation $escalation, bool $includeInternal): array
    {
        return $escalation->histories
            ->filter(fn ($history): bool => $includeInternal || ! $history->is_internal)
            ->map(fn ($history): array => [
                'id' => $history->id,
                'action' => $history->action,
                'note' => $history->note,
                'is_internal' => $history->is_internal,
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'actor_name' => $history->user?->name ?? 'System',
                'created_at' => $history->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function relatedLabel(DepartmentEscalation $escalation): ?string
    {
        $related = $escalation->related;

        if (! $related) {
            return null;
        }

        if ($related instanceof Task) {
            $code = $related->task_code ?? null;

            return $code ? "{$code} — {$related->title}" : $related->title;
        }

        return $related->title ?? null;
    }
}
