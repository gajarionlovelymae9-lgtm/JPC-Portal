<?php

namespace App\Services\DepartmentHead;

use App\Events\TaskManagement\TaskAssigned;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\TaskManagement\TaskAssignmentService;
use App\Services\TaskManagement\TaskService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DepartmentTaskService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context,
        private readonly TaskAssignmentService $assignmentService,
        private readonly TaskService $taskService,
    ) {}

    public function listTasks(User $user, array $filters = []): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);

        $query = Task::where(function ($q) use ($department) {
            $q->where('department_id', $department->id)
                ->orWhereNull('department_id');
        })->with([
            'assignments.employee:id,first_name,last_name,employee_number',
            'department:id,name',
            'createdBy:id,name',
        ]);

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tasks = $query->orderBy('created_at', 'desc')->get();

        return $tasks->map(function ($t) {
            $totalAssignments = $t->assignments->count();
            $completedAssignments = $t->assignments->whereIn('status', ['completed', 'approved'])->count();
            $avgProgress = $totalAssignments > 0
                ? round($t->assignments->avg('progress_percentage') ?? 0, 1)
                : 0;

            return [
                'id' => $t->id,
                'title' => $t->title,
                'task_code' => $t->task_code ?? "TSK-{$t->id}",
                'description' => $t->description,
                'priority' => $t->priority ?? 'medium',
                'status' => $t->status,
                'start_date' => $t->start_date ? Carbon::parse($t->start_date)->format('Y-m-d') : null,
                'deadline' => $t->due_date ? Carbon::parse($t->due_date)->format('Y-m-d') : null,
                'is_overdue' => $t->due_date && Carbon::parse($t->due_date)->isPast() && $t->status !== 'completed',
                'average_progress' => $avgProgress,
                'total_assigned' => $totalAssignments,
                'completed_assigned' => $completedAssignments,
                'assignments' => $t->assignments->map(fn ($a) => [
                    'id' => $a->id,
                    'employee_id' => $a->employee_id,
                    'employee_name' => $a->employee ? $a->employee->first_name.' '.$a->employee->last_name : '—',
                    'employee_number' => $a->employee?->employee_number,
                    'progress' => (int) ($a->progress_percentage ?? 0),
                    'status' => $a->status,
                    'submitted_at' => $a->completed_at,
                ]),
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        })->toArray();
    }

    public function createTask(User $user, array $data): array
    {
        $department = $this->context->resolveDepartment($user, $data['department_id'] ?? null);

        $employeeIds = array_values(array_unique(array_map('intval', $data['assigned_employee_ids'] ?? [])));
        if (empty($employeeIds) && ! empty($data['employee_id'])) {
            $employeeIds = [(int) $data['employee_id']];
        }

        if (empty($employeeIds)) {
            throw ValidationException::withMessages([
                'assigned_employee_ids' => 'Select at least one employee to assign this task to.',
            ]);
        }

        $employees = Employee::query()->whereIn('id', $employeeIds)->get()->keyBy('id');

        if ($employees->count() !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'assigned_employee_ids' => 'One of the selected employees no longer exists.',
            ]);
        }

        // Department heads may only assign employees inside their own
        // department. Admins retain the school-wide override.
        if (! $user->hasRole('admin')) {
            $outside = $employees->contains(fn (Employee $employee) => (int) $employee->department_id !== (int) $department->id);

            if ($outside) {
                throw new HttpResponseException(
                    ApiResponse::error('Cannot assign employees outside your department.', null, Response::HTTP_FORBIDDEN)
                );
            }
        }

        return DB::transaction(function () use ($user, $department, $data, $employeeIds, $employees) {
            $task = Task::create([
                'task_code' => $this->taskService->generateTaskCode(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'department_id' => $department->id,
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'assigned',
                'start_date' => $data['start_date'] ?? Carbon::now(),
                'due_date' => $data['deadline'] ?? null,
                'target_value' => $data['target_value'] ?? null,
                'target_unit' => $data['target_unit'] ?? null,
                'required_output' => trim(implode("\n\n", array_filter([
                    $data['requirements'] ?? null,
                    $data['instructions'] ?? null,
                ]))) ?: null,
                'created_by' => $user->id,
            ]);

            $assignments = [];
            foreach ($employeeIds as $empId) {
                $employee = $employees->get($empId);

                if ($employee === null) {
                    continue;
                }

                // Reuse the shared assignment service so the TaskAssigned
                // notification and activity log are emitted for every assignee.
                $assignments[] = $this->assignmentService->assignEmployee($task, $employee, $user);
            }

            // Audit
            AuditLog::query()->forceCreate([
                'user_id' => $user->id,
                'action' => 'department_head.task.created',
                'entity_type' => Task::class,
                'entity_id' => $task->id,
                'new_values' => [
                    'title' => $task->title,
                    'assigned_count' => count($assignments),
                    'department_id' => $department->id,
                ],
            ]);

            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'deadline' => $task->due_date,
                'assignments_count' => count($assignments),
            ];
        });
    }

    public function showTask(User $user, Task $task): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeTask($department, $task);

        $task->load([
            'assignments.employee.position:id,name',
            'department:id,name',
            'createdBy:id,name',
        ]);

        return [
            'id' => $task->id,
            'title' => $task->title,
            'task_code' => $task->task_code ?? "TSK-{$task->id}",
            'description' => $task->description,
            'priority' => $task->priority ?? 'medium',
            'status' => $task->status,
            'start_date' => $task->start_date ? Carbon::parse($task->start_date)->format('Y-m-d') : null,
            'deadline' => $task->due_date ? Carbon::parse($task->due_date)->format('Y-m-d') : null,
            'requirements' => $task->required_output,
            'instructions' => $task->required_output,
            'department_name' => $task->department?->name,
            'created_by_name' => $task->createdBy?->name,
            'assignments' => $task->assignments->map(fn ($a) => [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'employee_name' => $a->employee ? $a->employee->first_name.' '.$a->employee->last_name : '—',
                'employee_number' => $a->employee?->employee_number,
                'position' => $a->employee?->position?->name ?? 'Faculty',
                'progress' => (int) ($a->progress_percentage ?? 0),
                'status' => $a->status,
                'due_date' => $a->due_date ? Carbon::parse($a->due_date)->format('Y-m-d') : null,
                'notes' => $a->remarks,
            ]),
        ];
    }

    public function updateTask(User $user, Task $task, array $data): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeTask($department, $task);

        $task->update(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? null,
            'due_date' => $data['deadline'] ?? null,
            'required_output' => trim(implode("\n\n", array_filter([
                $data['requirements'] ?? null,
                $data['instructions'] ?? null,
            ]))) ?: null,
        ]));

        return ['id' => $task->id, 'title' => $task->title, 'status' => $task->status];
    }

    public function assignEmployees(User $user, Task $task, array $employeeIds): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeTask($department, $task);

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $employees = Employee::query()->whereIn('id', $employeeIds)->get()->keyBy('id');

        if ($employees->count() !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'One of the selected employees no longer exists.',
            ]);
        }

        if (! $user->hasRole('admin')) {
            $outside = $employees->contains(fn (Employee $employee) => (int) $employee->department_id !== (int) $department->id);

            if ($outside) {
                throw new HttpResponseException(
                    ApiResponse::error('Cannot assign employees outside your department.', null, Response::HTTP_FORBIDDEN)
                );
            }
        }

        return DB::transaction(function () use ($user, $task, $employees) {
            $created = 0;
            foreach ($employees as $empId => $employee) {
                $exists = TaskAssignment::where('task_id', $task->id)->where('employee_id', $empId)->exists();
                if (! $exists) {
                    $assignment = TaskAssignment::create([
                        'task_id' => $task->id,
                        'employee_id' => $empId,
                        'assigned_by' => $user->id,
                        'status' => 'assigned',
                        'progress_percentage' => 0,
                        'due_date' => $task->due_date,
                    ]);

                    TaskAssigned::dispatch($assignment);
                    $created++;
                }
            }

            AuditLog::query()->forceCreate([
                'user_id' => $user->id,
                'action' => 'department_head.task.assigned',
                'entity_type' => Task::class,
                'entity_id' => $task->id,
                'new_values' => ['newly_assigned_count' => $created],
            ]);

            return ['assigned_count' => $created];
        });
    }

    public function cancelTask(User $user, Task $task, string $reason): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeTask($department, $task);

        $task->update(['status' => 'cancelled']);
        TaskAssignment::where('task_id', $task->id)->update(['status' => 'cancelled']);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'department_head.task.cancelled',
            'auditable_type' => Task::class,
            'auditable_id' => $task->id,
            'new_values' => ['reason' => $reason],
        ]);

        return ['id' => $task->id, 'status' => 'cancelled', 'reason' => $reason];
    }
}
