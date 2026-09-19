<?php

namespace App\Services\DepartmentHead;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DepartmentTaskService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context
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
            'creator:id,name',
        ]);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['search'])) {
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
                ? round($t->assignments->avg('completion_percentage') ?? 0, 1)
                : 0;

            return [
                'id' => $t->id,
                'title' => $t->title,
                'task_code' => $t->task_code ?? "TSK-{$t->id}",
                'description' => $t->description,
                'priority' => $t->priority ?? 'medium',
                'status' => $t->status,
                'start_date' => $t->assigned_date ? Carbon::parse($t->assigned_date)->format('Y-m-d') : null,
                'deadline' => $t->deadline ? Carbon::parse($t->deadline)->format('Y-m-d') : null,
                'is_overdue' => $t->deadline && Carbon::parse($t->deadline)->isPast() && $t->status !== 'completed',
                'average_progress' => $avgProgress,
                'total_assigned' => $totalAssignments,
                'completed_assigned' => $completedAssignments,
                'assignments' => $t->assignments->map(fn ($a) => [
                    'id' => $a->id,
                    'employee_id' => $a->employee_id,
                    'employee_name' => $a->employee ? $a->employee->first_name . ' ' . $a->employee->last_name : '—',
                    'employee_number' => $a->employee?->employee_number,
                    'progress' => (int) ($a->completion_percentage ?? 0),
                    'status' => $a->status,
                    'submitted_at' => $a->submitted_at,
                ]),
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        })->toArray();
    }

    public function createTask(User $user, array $data): array
    {
        $department = $this->context->resolveDepartment($user, $data['department_id'] ?? null);

        $employeeIds = $data['assigned_employee_ids'] ?? [];
        if (empty($employeeIds) && !empty($data['employee_id'])) {
            $employeeIds = [$data['employee_id']];
        }

        // Validate all assigned employees belong to this department
        if (!empty($employeeIds)) {
            $invalidEmployees = Employee::whereIn('id', $employeeIds)
                ->where('department_id', '!=', $department->id)
                ->count();
            if ($invalidEmployees > 0 && !$user->hasRole('admin')) {
                throw new HttpResponseException(
                    ApiResponse::error('Cannot assign employees outside your department.', Response::HTTP_FORBIDDEN)
                );
            }
        }

        return DB::transaction(function () use ($user, $department, $data, $employeeIds) {
            $task = Task::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'department_id' => $department->id,
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'assigned',
                'assigned_date' => $data['start_date'] ?? Carbon::now(),
                'deadline' => $data['deadline'] ?? null,
                'requirements' => $data['requirements'] ?? null,
                'instructions' => $data['instructions'] ?? $data['references'] ?? null,
                'created_by' => $user->id,
            ]);

            $assignments = [];
            foreach ($employeeIds as $empId) {
                $assignment = TaskAssignment::create([
                    'task_id' => $task->id,
                    'employee_id' => $empId,
                    'assigned_by' => $user->id,
                    'status' => 'assigned',
                    'completion_percentage' => 0,
                    'due_date' => $task->deadline,
                ]);
                $assignments[] = $assignment;
            }

            // Audit
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'department_head.task.created',
                'auditable_type' => Task::class,
                'auditable_id' => $task->id,
                'new_values' => [
                    'title' => $task->title,
                    'assigned_count' => count($employeeIds),
                    'department_id' => $department->id,
                ],
            ]);

            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'deadline' => $task->deadline,
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
            'creator:id,name',
        ]);

        return [
            'id' => $task->id,
            'title' => $task->title,
            'task_code' => $task->task_code ?? "TSK-{$task->id}",
            'description' => $task->description,
            'priority' => $task->priority ?? 'medium',
            'status' => $task->status,
            'start_date' => $task->assigned_date ? Carbon::parse($task->assigned_date)->format('Y-m-d') : null,
            'deadline' => $task->deadline ? Carbon::parse($task->deadline)->format('Y-m-d') : null,
            'requirements' => $task->requirements,
            'instructions' => $task->instructions,
            'department_name' => $task->department?->name,
            'created_by_name' => $task->creator?->name,
            'assignments' => $task->assignments->map(fn ($a) => [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'employee_name' => $a->employee ? $a->employee->first_name . ' ' . $a->employee->last_name : '—',
                'employee_number' => $a->employee?->employee_number,
                'position' => $a->employee?->position?->name ?? 'Faculty',
                'progress' => (int) ($a->completion_percentage ?? 0),
                'status' => $a->status,
                'due_date' => $a->due_date ? Carbon::parse($a->due_date)->format('Y-m-d') : null,
                'submitted_at' => $a->submitted_at,
                'notes' => $a->notes,
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
            'deadline' => $data['deadline'] ?? null,
            'requirements' => $data['requirements'] ?? null,
            'instructions' => $data['instructions'] ?? null,
        ]));

        return ['id' => $task->id, 'title' => $task->title, 'status' => $task->status];
    }

    public function assignEmployees(User $user, Task $task, array $employeeIds): array
    {
        $department = $this->context->resolveDepartment($user);
        $this->context->authorizeTask($department, $task);

        return DB::transaction(function () use ($user, $task, $employeeIds) {
            $created = 0;
            foreach ($employeeIds as $empId) {
                $exists = TaskAssignment::where('task_id', $task->id)->where('employee_id', $empId)->exists();
                if (!$exists) {
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'employee_id' => $empId,
                        'assigned_by' => $user->id,
                        'status' => 'assigned',
                        'completion_percentage' => 0,
                        'due_date' => $task->deadline,
                    ]);
                    $created++;
                }
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'department_head.task.assigned',
                'auditable_type' => Task::class,
                'auditable_id' => $task->id,
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
