<?php

namespace App\Services\Admin;

use App\Enums\EscalationStatus;
use App\Models\AuditLog;
use App\Models\DepartmentEscalation;
use App\Models\EscalationHistory;
use App\Models\User;
use App\Services\Escalations\EscalationNotificationService;
use App\Services\Escalations\EscalationPresenter;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration side of the escalations module.
 *
 * Administrators may list every department's escalations and drive the review
 * workflow: assign a responsible administrator, add internal notes, and move
 * the escalation through the permitted status transitions
 * (see {@see EscalationStatus::canTransitionTo()}). Every action writes an
 * append-only history entry and an audit-log record, and invalid transitions
 * are rejected rather than silently applied.
 */
class AdminEscalationManagementService
{
    public function __construct(
        private readonly EscalationPresenter $presenter,
        private readonly EscalationNotificationService $notifications,
    ) {}

    /**
     * Paginated, filtered list across every department.
     */
    public function listEscalations(array $filters): array
    {
        $paginator = $this->filteredQuery($filters)
            ->paginate($this->perPage($filters))
            ->through(fn (DepartmentEscalation $escalation): array => $this->presenter->transform($escalation));

        return [
            'items' => $paginator->items(),
            'summary' => $this->presenter->summary(),
            'pagination' => $this->pagination($paginator),
        ];
    }

    /**
     * Summary counters for the dashboard cards.
     */
    public function summary(): array
    {
        return $this->presenter->summary();
    }

    /**
     * Full detail, including the internal notes hidden from Department Heads.
     */
    public function showEscalation(DepartmentEscalation $escalation): array
    {
        $escalation->load($this->relations());

        return $this->presenter->transform($escalation) + [
            'history' => $this->presenter->history($escalation, includeInternal: true),
        ];
    }

    /**
     * As soon as a responsible administrator is set, a still-pending escalation
     * moves to "under review".
     */
    public function assign(User $user, DepartmentEscalation $escalation, array $data): array
    {
        $this->guardNotTerminal($escalation);

        $assignee = $this->resolveAssignableUser((int) $data['assigned_to']);

        DB::transaction(function () use ($user, $escalation, $assignee, $data): void {
            $previous = $escalation->status;

            $escalation->assigned_to = $assignee->id;
            $escalation->assigned_at = now();

            if ($escalation->reviewed_by === null) {
                $escalation->reviewed_by = $user->id;
                $escalation->reviewed_at = now();
            }

            if ($previous === EscalationStatus::Pending) {
                $escalation->status = EscalationStatus::UnderReview;
            }

            $escalation->save();

            $this->recordHistory($escalation, $user, EscalationHistory::ACTION_ASSIGNED, [
                'note' => $data['note'] ?? "Assigned to {$assignee->name}.",
                'old_status' => $previous->value,
                'new_status' => $escalation->status->value,
            ]);

            $this->audit($user, $escalation, 'admin.escalation.assigned', [
                'assigned_to' => $assignee->id,
            ]);
        });

        $escalation->load($this->relations());

        $this->notifications->notifyAssigned($escalation);

        return $this->showEscalation($escalation->fresh($this->relations()));
    }

    /**
     * Move the escalation through the permitted status transitions and, for
     * terminal statuses, record the resolution.
     */
    public function updateStatus(User $user, DepartmentEscalation $escalation, array $data): array
    {
        $target = EscalationStatus::from($data['status']);
        $current = $escalation->status;

        $this->guardNotTerminal($escalation);

        if (! $current->canTransitionTo($target)) {
            $this->fail(
                "An escalation that is {$current->label()} cannot be moved to {$target->label()}.",
                'status',
            );
        }

        if ($target->isTerminal() && blank($data['resolution_notes'] ?? null)) {
            $this->fail('A resolution note is required when resolving or rejecting an escalation.', 'resolution_notes');
        }

        DB::transaction(function () use ($user, $escalation, $target, $current, $data): void {
            $escalation->status = $target;

            if ($escalation->reviewed_by === null) {
                $escalation->reviewed_by = $user->id;
                $escalation->reviewed_at = now();
            }

            if ($target->isTerminal()) {
                $escalation->resolution_notes = $data['resolution_notes'];
                $escalation->resolved_by = $user->id;
                $escalation->resolved_at = now();
            }

            $escalation->save();

            $action = match ($target) {
                EscalationStatus::Resolved => EscalationHistory::ACTION_RESOLVED,
                EscalationStatus::Rejected => EscalationHistory::ACTION_REJECTED,
                default => EscalationHistory::ACTION_REVIEWED,
            };

            $this->recordHistory($escalation, $user, $action, [
                'note' => $data['resolution_notes'] ?? $data['internal_note'] ?? null,
                'old_status' => $current->value,
                'new_status' => $target->value,
            ]);

            $this->audit($user, $escalation, "admin.escalation.{$target->value}", [
                'status' => $target->value,
                'resolution_notes' => $data['resolution_notes'] ?? null,
            ]);
        });

        $escalation->load($this->relations());

        $this->notifications->notifyStatusChanged($escalation);

        return $this->showEscalation($escalation->fresh($this->relations()));
    }

    /**
     * Add a review note. Internal notes stay hidden from the Department Head
     * and are never notified; shared notes notify the originating head.
     */
    public function addNote(User $user, DepartmentEscalation $escalation, array $data): array
    {
        $internal = (bool) ($data['is_internal'] ?? false);

        $historyId = DB::transaction(function () use ($user, $escalation, $internal, $data): int {
            if ($escalation->reviewed_by === null) {
                $escalation->reviewed_by = $user->id;
                $escalation->reviewed_at = now();
                $escalation->save();
            }

            $history = $this->recordHistory($escalation, $user, EscalationHistory::ACTION_NOTE, [
                'note' => $data['note'],
                'is_internal' => $internal,
                'old_status' => $escalation->status->value,
                'new_status' => $escalation->status->value,
            ]);

            $this->audit($user, $escalation, $internal ? 'admin.escalation.note_internal' : 'admin.escalation.note', [
                'history_id' => $history->id,
            ]);

            return $history->id;
        });

        $escalation->load($this->relations());

        if (! $internal) {
            $this->notifications->notifyNoteAdded($escalation, 'note:'.$historyId);
        }

        return $this->showEscalation($escalation->fresh($this->relations()));
    }

    /**
     * Reference data for the review workspace.
     *
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'administrators' => $this->assignableAdministrators(),
            'statuses' => array_map(
                fn (EscalationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'is_terminal' => $status->isTerminal(),
                ],
                EscalationStatus::cases(),
            ),
        ];
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    private function filteredQuery(array $filters): Builder
    {
        $query = DepartmentEscalation::query()->with($this->relations());

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'like', $search)
                    ->orWhere('reason', 'like', $search)
                    ->orWhereHas('department', fn (Builder $department) => $department->where('name', 'like', $search))
                    ->orWhereHas('employee', function (Builder $employee) use ($search): void {
                        $employee->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('employee_number', 'like', $search);
                    });
            });
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return match ($filters['sort'] ?? 'created_at') {
            'priority' => $query->orderByRaw(
                "CASE priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END {$direction}"
            ),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderBy('created_at', $direction),
        };
    }

    private function resolveAssignableUser(int $userId): User
    {
        $user = User::query()->find($userId);

        if (! $user || ! $user->hasAnyRole(config('escalation.assignable_roles', ['admin']))) {
            $this->fail('The selected administrator is not authorized to receive escalations.', 'assigned_to');
        }

        return $user;
    }

    private function guardNotTerminal(DepartmentEscalation $escalation): void
    {
        if ($escalation->status->isTerminal()) {
            $this->fail('This escalation has been finalized and can no longer be changed.', 'status');
        }
    }

    private function recordHistory(DepartmentEscalation $escalation, User $user, string $action, array $attributes = []): EscalationHistory
    {
        return EscalationHistory::create(array_merge([
            'escalation_id' => $escalation->id,
            'user_id' => $user->id,
            'action' => $action,
            'is_internal' => false,
        ], $attributes));
    }

    private function audit(User $user, DepartmentEscalation $escalation, string $action, array $newValues): void
    {
        AuditLog::forceCreate([
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => DepartmentEscalation::class,
            'entity_id' => $escalation->id,
            'new_values' => $newValues,
        ]);
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'department:id,name',
            'employee:id,first_name,middle_name,last_name,suffix,employee_number,department_id',
            'creator:id,name',
            'assignee:id,name',
            'reviewer:id,name',
            'resolver:id,name',
            'related',
            'histories.user:id,name',
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function assignableAdministrators(): array
    {
        return User::query()
            ->whereHas('role', fn (Builder $query) => $query->whereIn('name', config('escalation.assignable_roles', ['admin'])))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $admin): array => ['id' => $admin->id, 'name' => $admin->name])
            ->all();
    }

    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 10);

        return max(1, min($perPage, 100));
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function fail(string $message, string $field): never
    {
        throw new HttpResponseException(
            ApiResponse::validationError($message, [$field => [$message]], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
