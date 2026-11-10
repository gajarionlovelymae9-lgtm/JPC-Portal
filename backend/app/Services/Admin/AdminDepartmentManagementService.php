<?php

namespace App\Services\Admin;

use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Role;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin department management (People Management → Department Management).
 *
 * The read side (`listDepartments`, `showDepartment`) is contract-tested and
 * reused by the existing admin analytics views, so its keys are additive-only.
 * Everything below the read block is the People Management write surface:
 * create, edit, activate/deactivate, head assignment and employee transfer,
 * each wrapped in a transaction and audited through the shared ActivityLog
 * writer — never a second logging mechanism.
 */
class AdminDepartmentManagementService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /* ==================================================================== */
    /* Reads (compatible with the existing admin department views) */
    /* ==================================================================== */

    public function listDepartments(array $filters = []): array
    {
        $departments = Department::query()
            ->with(['departmentHeads' => fn ($q) => $q
                ->where('is_current', true)
                ->with('employee:id,first_name,last_name,employee_number')])
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term));
            })
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('name')
            ->get();

        $deptIds = $departments->modelKeys();
        $employees = $deptIds === []
            ? collect()
            : Employee::with('position:id,name')->whereIn('department_id', $deptIds)->get();
        $empIds = $employees->modelKeys();
        $assignments = $empIds === []
            ? collect()
            : TaskAssignment::whereIn('employee_id', $empIds)->with('task:id,due_date')->get();
        $reports = $empIds === [] ? collect() : Report::whereIn('employee_id', $empIds)->get();

        return $departments->map(function ($d) use ($employees, $assignments, $reports) {
            $currentHead = $d->departmentHeads->firstWhere('is_current', true);
            $deptEmployees = $employees->where('department_id', $d->id);
            $empIds = $deptEmployees->pluck('id');
            $deptAssignments = $assignments->whereIn('employee_id', $empIds);

            $total = $deptAssignments->count();
            $completed = $deptAssignments->whereIn('status', ['completed', 'approved'])->count();
            $pending = $deptAssignments->whereIn('status', ['assigned', 'in_progress'])->count();

            $overdue = $deptAssignments->filter(function ($a) {
                $deadline = $a->due_date ?? $a->task?->due_date;

                return $deadline && Carbon::parse($deadline)->isPast() && ! in_array($a->status, ['completed', 'approved']);
            })->count();

            $deptReports = $reports->whereIn('employee_id', $empIds)->count();
            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            $onTimeCount = $deptAssignments->filter(function ($a) {
                if (! $a->completed_at) {
                    return false;
                }
                $deadline = $a->due_date ?? $a->task?->due_date;

                return ! $deadline || Carbon::parse($a->completed_at)->lte(Carbon::parse($deadline));
            })->count();
            $onTimeRate = $completed > 0 ? round(($onTimeCount / $completed) * 100, 1) : 100;

            return [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'description' => $d->description ?? null,
                'status' => $d->status,
                'created_at' => $d->created_at?->toIso8601String(),
                'head_employee_id' => $currentHead?->employee?->id,
                'head_name' => $currentHead?->employee
                    ? trim($currentHead->employee->first_name.' '.$currentHead->employee->last_name)
                    : 'Unassigned',
                'head_employee_number' => $currentHead?->employee?->employee_number,
                'employees_count' => $deptEmployees->count(),
                'active_employees' => $deptEmployees->where('employment_status', 'active')->count(),
                'tasks_count' => $total,
                'completed_tasks' => $completed,
                'pending_tasks' => $pending,
                'overdue_tasks' => $overdue,
                'reports_count' => $deptReports,
                'completion_rate' => $rate,
                'on_time_rate' => $onTimeRate,
            ];
        })->values()->toArray();
    }

    public function showDepartment(Department $department): array
    {
        $department->load([
            'departmentHeads' => fn ($q) => $q->where('is_current', true)
                ->with('employee:id,first_name,last_name,employee_number,position_id'),
            'departmentHeads.employee.position:id,name',
        ]);
        $currentHead = $department->departmentHeads->firstWhere('is_current', true);
        $employees = Employee::where('department_id', $department->id)
            ->with(['position:id,name', 'user:id,email,status'])
            ->get();
        $empIds = $employees->pluck('id');

        $assignments = TaskAssignment::whereIn('employee_id', $empIds)
            ->with(['task:id,title,due_date,priority', 'employee:id,first_name,last_name'])
            ->get();

        $reports = Report::whereIn('employee_id', $empIds)
            ->with('task:id,title')
            ->get();

        $target = DepartmentTarget::where('department_id', $department->id)->first();

        $totalTasks = $assignments->count();
        $completedTasks = $assignments->whereIn('status', ['completed', 'approved'])->count();
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description ?? null,
            'status' => $department->status,
            'created_at' => $department->created_at?->toIso8601String(),
            'head' => [
                'employee_id' => $currentHead?->employee?->id,
                'name' => $currentHead?->employee
                    ? trim($currentHead->employee->first_name.' '.$currentHead->employee->last_name)
                    : 'Unassigned',
                'position' => $currentHead?->employee?->position?->name,
            ],
            'metrics' => [
                'total_employees' => $employees->count(),
                'active_employees' => $employees->where('employment_status', 'active')->count(),
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $assignments->whereIn('status', ['assigned', 'in_progress'])->count(),
                'reports_count' => $reports->count(),
                'completion_rate' => $completionRate,
            ],
            'target' => $target ? [
                'name' => $target->name ?? $target->title ?? 'Department Annual Target',
                'target' => (int) ($target->target_value ?? 10),
                'completed' => min((int) ($target->target_value ?? 10), $completedTasks),
            ] : null,
            'employees' => $employees->map(fn ($e) => [
                'id' => $e->id,
                'name' => trim($e->first_name.' '.$e->last_name),
                'employee_number' => $e->employee_number,
                'position' => $e->position?->name,
                'position_id' => $e->position_id,
                'status' => $e->employment_status,
                'employment_type' => $e->employment_type,
                'date_hired' => $e->date_hired?->toDateString(),
                'account_status' => $e->user?->status?->value,
            ])->toArray(),
        ];
    }

    /* ==================================================================== */
    /* Management reads (list header + assignment pickers) */
    /* ==================================================================== */

    /**
     * Counts for the Department summary cards — always live queries, never
     * derived from the (filtered) list on screen.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'total' => Department::count(),
            'active' => Department::where('status', 'active')->count(),
            'inactive' => Department::where('status', '!=', 'active')->count(),
        ];
    }

    /**
     * Options for the create/edit/assignment forms.
     *
     * `employees` lists every active employee with their department, role and
     * account state so the head picker can be validated client-side too — the
     * server still enforces the rule on every call.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $headRole = RoleEnum::DepartmentHead->value;
        $headRoleId = Role::query()->where('name', $headRole)->value('id');

        // Count head accounts across both supported role stores: the legacy
        // `users.role_id` column and the `role_user` pivot.
        $headRoleUsers = User::query()
            ->where(function ($query) use ($headRoleId, $headRole) {
                if ($headRoleId !== null) {
                    $query->where('role_id', $headRoleId);
                }
                $query->orWhereHas('roles', fn ($roles) => $roles->where('name', $headRole));
            })
            ->count();

        return [
            'head_role' => $headRole,
            'head_role_users' => $headRoleUsers,
            'departments' => Department::orderBy('name')->get()
                ->map(fn (Department $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'code' => $d->code,
                    'status' => $d->status,
                ])->all(),
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
            'employees' => Employee::query()
                ->with([
                    'department:id,name',
                    'user:id,name,role_id,status',
                    'user.role:id,name',
                    'user.roles:id,name',
                ])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'employee_number' => $e->employee_number,
                    'department_id' => $e->department_id,
                    'department' => $e->department?->name,
                    'employment_status' => $e->employment_status,
                    'has_account' => $e->user_id !== null,
                    'account_status' => $e->user?->status?->value,
                    'role' => $e->user?->role?->name,
                    'is_head_role' => $e->user !== null && $this->hasHeadRole($e->user),
                ])->all(),
        ];
    }

    /**
     * Employees who can actually be assigned as head of the given department.
     *
     * An eligible head must:
     *   1. belong to the department;
     *   2. be currently employed;
     *   3. have a linked login account;
     *   4. have an active account;
     *   5. already hold the Department Head role (via `users.role_id` or the
     *      `role_user` pivot). The role is never granted by this workflow.
     *
     * Diagnostic counts let the UI explain an empty result instead of showing
     * a silent, unexplained empty dropdown.
     *
     * @return array<string, mixed>
     */
    public function eligibleHeads(Department $department): array
    {
        $headRole = RoleEnum::DepartmentHead->value;

        $employees = Employee::query()
            ->where('department_id', $department->id)
            ->with([
                'position:id,name',
                'user:id,name,status,role_id',
                'user.role:id,name',
                'user.roles:id,name',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $active = $employees->where('employment_status', 'active');

        $withoutAccount = $active->filter(fn (Employee $e) => $e->user === null);
        $inactiveAccount = $active->filter(fn (Employee $e) => $e->user !== null && $e->user->status !== UserStatus::Active);
        $withoutHeadRole = $active->filter(fn (Employee $e) => $e->user !== null
            && $e->user->status === UserStatus::Active
            && ! $this->hasHeadRole($e->user));

        $eligible = $active->filter(fn (Employee $e) => $e->user !== null
            && $e->user->status === UserStatus::Active
            && $this->hasHeadRole($e->user))
            ->values();

        $currentHeadEmployeeId = DepartmentHead::query()
            ->where('department_id', $department->id)
            ->where('is_current', true)
            ->value('employee_id');

        return [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'status' => $department->status,
            ],
            'head_role' => $headRole,
            'current_head_employee_id' => $currentHeadEmployeeId !== null ? (int) $currentHeadEmployeeId : null,
            'employees' => $eligible->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->full_name,
                'employee_number' => $e->employee_number,
                'position' => $e->position?->name,
                'department_id' => $e->department_id,
                'department' => $department->name,
                'account_status' => $e->user?->status?->value,
            ])->all(),
            'diagnostics' => [
                'total_employees' => $employees->count(),
                'active_employees' => $active->count(),
                'without_account' => $withoutAccount->count(),
                'inactive_account' => $inactiveAccount->count(),
                'without_head_role' => $withoutHeadRole->count(),
                'eligible' => $eligible->count(),
            ],
        ];
    }

    /**
     * Whether the account holds the Department Head role in either supported
     * role store. Reads the legacy `users.role_id` first, then the pivot.
     */
    private function hasHeadRole(User $user): bool
    {
        $headRole = RoleEnum::DepartmentHead->value;

        if ($user->role?->name === $headRole) {
            return true;
        }

        if ($user->relationLoaded('roles')) {
            return $user->roles->contains(fn (Role $role) => $role->name === $headRole);
        }

        return $user->roles()->where('name', $headRole)->exists();
    }

    /* ==================================================================== */
    /* Writes */
    /* ==================================================================== */

    /**
     * Create a department.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?User $actor = null): array
    {
        $department = DB::transaction(function () use ($data, $actor): Department {
            $department = Department::create([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $this->activity->log(
                activityType: 'admin.department.create',
                description: sprintf('Created department %s.', $department->name),
                user: $actor,
                subject: $department,
                metadata: [
                    'name' => $department->name,
                    'code' => $department->code,
                    'status' => $department->status,
                ],
            );

            return $department;
        });

        return $this->showDepartment($department);
    }

    /**
     * Edit an existing department.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(Department $department, array $data, ?User $actor = null): array
    {
        DB::transaction(function () use ($department, $data, $actor): void {
            $changes = [];

            foreach (['name', 'code', 'description', 'status'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $current = $department->getAttribute($field);
                $next = $data[$field] ?? null;

                if ($next !== $current) {
                    $changes[$field] = ['from' => $current, 'to' => $next];
                    $department->setAttribute($field, $next);
                }
            }

            if ($changes === []) {
                return;
            }

            $department->save();

            $this->activity->log(
                activityType: 'admin.department.update',
                description: sprintf('Updated department %s.', $department->name),
                user: $actor,
                subject: $department,
                metadata: ['changes' => $changes],
            );
        });

        return $this->showDepartment($department->refresh());
    }

    /**
     * Activate or deactivate a department.
     *
     * Deactivation never removes records; it only stops the department from
     * being selected for new employee assignments.
     *
     * @return array<string, mixed>
     */
    public function setStatus(Department $department, string $status, ?User $actor = null): array
    {
        if ($department->status === $status) {
            throw ValidationException::withMessages([
                'status' => $status === 'active'
                    ? 'This department is already active.'
                    : 'This department is already inactive.',
            ]);
        }

        $previous = $department->status;

        DB::transaction(function () use ($department, $status, $previous, $actor): void {
            $department->status = $status;
            $department->save();

            $this->activity->log(
                activityType: $status === 'active' ? 'admin.department.activate' : 'admin.department.deactivate',
                description: sprintf(
                    '%s department %s.',
                    $status === 'active' ? 'Activated' : 'Deactivated',
                    $department->name,
                ),
                user: $actor,
                subject: $department,
                metadata: ['status' => ['from' => $previous, 'to' => $status]],
            );
        });

        return $this->showDepartment($department->refresh());
    }

    /**
     * Assign a department head.
     *
     * Rules:
     *   1. the department must be active — an inactive one has no head slot;
     *   2. the employee must belong to THIS department;
     *   3. the employee must be currently employed;
     *   4. the employee's login account must exist and be active;
     *   5. the account must already hold the Department Head role. The role is
     *      never granted here implicitly, so an Admin cannot accidentally widen
     *      someone's access through a transfer;
     *   6. an employee who is already the current head is rejected rather than
     *      silently reusing or overwriting their existing history row.
     *
     * @return array<string, mixed>
     */
    public function assignHead(Department $department, int $employeeId, ?User $actor = null): array
    {
        $employee = Employee::query()->with(['user.role', 'user.roles'])->find($employeeId);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'That employee record no longer exists.',
            ]);
        }

        if ((int) $employee->department_id !== (int) $department->id) {
            throw ValidationException::withMessages([
                'employee_id' => 'The department head must belong to the department first. Assign them through Assign employees.',
            ]);
        }

        if ($employee->employment_status !== 'active') {
            throw ValidationException::withMessages([
                'employee_id' => 'Only currently employed staff can be assigned as a department head.',
            ]);
        }

        if ($department->status !== 'active') {
            throw ValidationException::withMessages([
                'department' => 'Re-activate the department before assigning a head.',
            ]);
        }

        $user = $employee->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee has no login account yet. Create one with the Department Head role in User Accounts first.',
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee\'s account is not active. Activate it in User Accounts before assigning them as head.',
            ]);
        }

        if (! $this->hasHeadRole($user)) {
            throw ValidationException::withMessages([
                'employee_id' => 'Assign the Department Head role to this account in User Accounts first, then assign them as head.',
            ]);
        }

        $alreadyCurrent = DepartmentHead::query()
            ->where('department_id', $department->id)
            ->where('employee_id', $employee->id)
            ->where('is_current', true)
            ->exists();

        if ($alreadyCurrent) {
            throw ValidationException::withMessages([
                'employee_id' => sprintf('%s is already the current head of this department.', $employee->full_name),
            ]);
        }

        DB::transaction(function () use ($department, $employee, $actor): void {
            // Close any head currently serving this department, then add a new
            // history row. Never overwrite an earlier term, so past service
            // dates remain intact when someone is re-assigned later.
            DepartmentHead::query()
                ->where('department_id', $department->id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'end_date' => Carbon::today()]);

            DepartmentHead::create([
                'department_id' => $department->id,
                'employee_id' => $employee->id,
                'start_date' => Carbon::today(),
                'end_date' => null,
                'is_current' => true,
            ]);

            $this->activity->log(
                activityType: 'admin.department.head.assign',
                description: sprintf(
                    'Assigned %s (employee #%s) as head of %s.',
                    $employee->full_name,
                    $employee->employee_number,
                    $department->name,
                ),
                user: $actor,
                subject: $department,
                metadata: [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'department_id' => $department->id,
                ],
            );
        });

        return $this->showDepartment($department->refresh());
    }

    /**
     * Transfer employees into a department.
     *
     * Historical tasks, reports and department-head records are preserved; if
     * a transferred employee is the current head of another department, that
     * head record is closed so the old department is not left leaderless on a
     * record that no longer belongs to it.
     *
     * @param  list<int>  $employeeIds
     * @return array{department: array<string, mixed>, transferred: int}
     */
    public function assignEmployees(Department $department, array $employeeIds, ?User $actor = null): array
    {
        if ($department->status !== 'active') {
            throw ValidationException::withMessages([
                'department' => 'Inactive departments cannot receive new employee assignments. Activate it first.',
            ]);
        }

        $employees = Employee::query()->whereIn('id', $employeeIds)->get();
        $known = $employees->modelKeys();

        if (count($employeeIds) !== count($known)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'One or more selected employees no longer exist.',
            ]);
        }

        $transferred = 0;

        DB::transaction(function () use ($department, $employees, $actor, &$transferred): void {
            foreach ($employees as $employee) {
                $from = (int) $employee->department_id;
                $to = (int) $department->id;

                if ($from === $to) {
                    continue;
                }

                $oldDepartment = Department::query()->find($from);

                $employee->forceFill(['department_id' => $to])->save();
                $transferred++;

                // Close any current-head record the employee still holds so the
                // old department is never left with a head of another unit.
                DepartmentHead::query()
                    ->where('employee_id', $employee->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false, 'end_date' => Carbon::today()]);

                $this->activity->log(
                    activityType: 'admin.employee.transfer',
                    description: sprintf(
                        'Transferred %s (employee #%s) from %s to %s.',
                        $employee->full_name,
                        $employee->employee_number,
                        $oldDepartment?->name ?? ('Department #'.$from),
                        $department->name,
                    ),
                    user: $actor,
                    subject: $employee,
                    metadata: [
                        'from_department_id' => $from,
                        'to_department_id' => $to,
                        'employee_number' => $employee->employee_number,
                    ],
                );
            }
        });

        return [
            'department' => $this->showDepartment($department->refresh()),
            'transferred' => $transferred,
        ];
    }
}
