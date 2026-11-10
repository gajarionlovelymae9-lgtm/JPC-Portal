<?php

namespace App\Services\DepartmentHead;

use App\Enums\EscalationPriority;
use App\Enums\EscalationStatus;
use App\Enums\EscalationType;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentEscalation;
use App\Models\Employee;
use App\Models\EscalationHistory;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Services\Escalations\EscalationNotificationService;
use App\Services\Escalations\EscalationPresenter;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Department Head side of the escalations module.
 *
 * A Department Head may only ever raise, list and read escalations belonging
 * to their own department: the department is resolved from the authenticated
 * user (never trusted from the request), the subject employee, the related
 * task/report and the assigned administrator are all validated against that
 * scope, and every state change is written to the append-only escalation
 * history and the shared audit log.
 */
class DepartmentEscalationService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context,
        private readonly EscalationPresenter $presenter,
        private readonly EscalationNotificationService $notifications,
    ) {}

    /**
     * Paginated, filtered list of the department's escalations.
     */
    public function listEscalations(User $user, array $filters): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);

        $paginator = $this->filteredQuery($department->id, $filters)
            ->paginate($this->perPage($filters))
            ->through(fn (DepartmentEscalation $escalation): array => $this->presenter->transform($escalation));

        return [
            'items' => $paginator->items(),
            'summary' => $this->presenter->summary($department->id),
            'pagination' => $this->pagination($paginator),
        ];
    }

    /**
     * Summary counters for the dashboard cards.
     */
    public function summary(User $user, array $filters = []): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);

        return $this->presenter->summary($department->id);
    }

    /**
     * Raise a new escalation on behalf of the department.
     */
    public function createEscalation(User $user, array $data): array
    {
        $department = $this->context->resolveDepartment($user, $data['department_id'] ?? null);

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $this->context->authorizeEmployee($department, $employee);

        $related = $this->resolveRelated($department, $employee, $data);
        $assignee = $this->resolveAssignee($data['assigned_to'] ?? null);
        $attachment = $this->storeAttachment($data['attachment'] ?? null);

        $escalation = DB::transaction(function () use ($user, $department, $employee, $related, $assignee, $attachment, $data): DepartmentEscalation {
            $escalation = DepartmentEscalation::create([
                'department_id' => $department->id,
                'employee_id' => $employee->id,
                'created_by' => $user->id,
                'title' => $data['title'],
                'type' => $data['type'],
                'related_type' => $related['type'] ?? null,
                'related_id' => $related['id'] ?? null,
                'reason' => $data['reason'],
                'requested_action' => $data['requested_action'] ?? null,
                'attachment_path' => $attachment['path'] ?? null,
                'attachment_name' => $attachment['name'] ?? null,
                'priority' => $data['priority'],
                'status' => EscalationStatus::Pending,
                'assigned_to' => $assignee?->id,
                'assigned_at' => $assignee ? now() : null,
            ]);

            $this->recordHistory($escalation, $user, EscalationHistory::ACTION_CREATED, [
                'note' => 'Escalation raised by the Department Head.',
                'new_status' => EscalationStatus::Pending->value,
            ]);

            AuditLog::forceCreate([
                'user_id' => $user->id,
                'action' => 'department_head.escalation.created',
                'entity_type' => DepartmentEscalation::class,
                'entity_id' => $escalation->id,
                'new_values' => [
                    'title' => $escalation->title,
                    'type' => $escalation->type->value,
                    'priority' => $escalation->priority->value,
                    'status' => $escalation->status->value,
                    'employee_id' => $escalation->employee_id,
                    'assigned_to' => $escalation->assigned_to,
                ],
            ]);

            return $escalation;
        });

        $escalation->load($this->relations());

        $this->notifications->notifyCreated($escalation);
        if ($escalation->assigned_to) {
            $this->notifications->notifyAssigned($escalation);
        }

        return $this->presenter->transform($escalation);
    }

    /**
     * Full detail (with the department-visible history) for one escalation.
     */
    public function showEscalation(DepartmentEscalation $escalation): array
    {
        $escalation->load($this->relations());

        return $this->presenter->transform($escalation) + [
            'history' => $this->presenter->history($escalation, includeInternal: false),
        ];
    }

    /**
     * Reference data the raise-escalation form needs from the backend.
     *
     * The employee list is scoped to the department so the form cannot submit
     * an arbitrary employee id; the administrator list only contains users
     * holding an assignable (Administration) role.
     */
    public function formOptions(User $user, array $filters = []): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);

        $employees = Employee::query()
            ->where('department_id', $department->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();

        return [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
            ],
            'employees' => $employees,
            'administrators' => $this->assignableAdministrators(),
            'issue_types' => $this->enumOptions(EscalationType::cases()),
            'priorities' => $this->enumOptions(EscalationPriority::cases()),
        ];
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    private function filteredQuery(int $departmentId, array $filters): Builder
    {
        $query = DepartmentEscalation::query()
            ->where('department_id', $departmentId)
            ->with($this->relations());

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'like', $search)
                    ->orWhere('reason', 'like', $search)
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

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRelated(Department $department, Employee $employee, array $data): ?array
    {
        $type = $data['related_type'] ?? null;
        $id = $data['related_id'] ?? null;

        if (! $type || ! $id) {
            return null;
        }

        $modelClass = DepartmentEscalation::RELATED_TYPES[$type] ?? null;
        if (! $modelClass) {
            $this->fail('Invalid related record type.', 'related_type');
        }

        $record = $modelClass::query()->find($id);
        if (! $record) {
            $this->fail('The selected related record could not be found.', 'related_id');
        }

        if ($record instanceof Task) {
            $this->context->authorizeTask($department, $record);
        } elseif ($record instanceof Report) {
            $this->context->authorizeReport($department, $record);
            if ($record->employee_id !== $employee->id) {
                $this->fail('The selected report does not belong to the selected employee.', 'related_id');
            }
        }

        return ['type' => $modelClass, 'id' => $record->id];
    }

    private function resolveAssignee(?int $assigneeId): ?User
    {
        if (! $assigneeId) {
            return null;
        }

        $assignee = User::query()->find($assigneeId);

        if (! $assignee || ! $assignee->hasAnyRole(config('escalation.assignable_roles', ['admin']))) {
            $this->fail('The selected administrator is not authorized to receive escalations.', 'assigned_to');
        }

        return $assignee;
    }

    /**
     * @return array{path: string, name: string}|null
     */
    private function storeAttachment(mixed $file): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $disk = config('escalation.attachment.disk', 'local');
        $path = $file->store('escalations', $disk);

        return ['path' => $path, 'name' => $file->getClientOriginalName()];
    }

    private function recordHistory(DepartmentEscalation $escalation, User $user, string $action, array $attributes = []): void
    {
        EscalationHistory::create(array_merge([
            'escalation_id' => $escalation->id,
            'user_id' => $user->id,
            'action' => $action,
            'is_internal' => false,
        ], $attributes));
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
        ];
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            fn ($case): array => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
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
