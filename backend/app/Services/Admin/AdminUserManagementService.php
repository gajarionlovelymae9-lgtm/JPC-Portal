<?php

namespace App\Services\Admin;

use App\Enums\Permissions;
use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Admin user management (People Management → User Accounts).
 *
 * Uses the project's existing custom RBAC (users.role_id -> roles ->
 * permissions) rather than introducing a package. Every mutation runs inside
 * a transaction, keeps `users.role_id` and the `role_user` pivot in sync, and
 * writes an audit log entry plus a matching ActivityLog record through the
 * shared writer.
 *
 * Account-status rules:
 *  - the employee record is never deleted when a login is deactivated;
 *  - employment status lives on `employees`, account status on `users`, and
 *    the two are updated through separate endpoints on purpose.
 */
class AdminUserManagementService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Paginated user list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return User::query()
            ->with(['role', 'employee.department'])
            ->when(! empty($filters['role']), fn (Builder $q) => $q->whereHas(
                'role',
                fn (Builder $r) => $r->where('name', $filters['role']),
            ))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn (Builder $inner) => $inner->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('username', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (User $user) => $this->row($user));
    }

    /**
     * @return array<string, mixed>
     */
    public function find(int $id): array
    {
        $user = User::query()->with(['role.permissions', 'employee.department'])->findOrFail($id);

        $row = $this->row($user);
        $row['permissions'] = $user->role?->permissions->pluck('name')->values()->all() ?? [];

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?User $actor = null): array
    {
        $role = $this->resolveRole($data['role']);

        $user = DB::transaction(function () use ($data, $role): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'] ?? User::generateUsername((string) $data['email']),
                'password' => Hash::make($data['password']),
                'role_id' => $role->id,
                'status' => $data['status'] ?? UserStatus::Active->value,
                'email_verified_at' => now(),
            ]);

            // Keep the role pivot in sync with users.role_id (the legacy read).
            $user->roles()->sync([$role->id]);

            if (! empty($data['employee_id'])) {
                $this->linkEmployee($user, (int) $data['employee_id']);
            }

            return $user;
        });

        $this->activity->log(
            activityType: 'admin.user.create',
            description: sprintf('Created user %s (%s) with role %s.', $user->name, $user->email, $role->name),
            user: $actor,
            subject: $user,
            metadata: ['role' => $role->name, 'email' => $user->email],
        );

        return $this->find($user->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, ?User $actor = null): array
    {
        $user = User::query()->with('role')->findOrFail($id);

        $previousRole = $this->resolveRoleByName($user->role?->name);

        [$user, $changes] = DB::transaction(function () use ($user, $data): array {
            $changes = [];

            if (array_key_exists('name', $data) && $data['name'] !== $user->name) {
                $changes['name'] = ['from' => $user->name, 'to' => $data['name']];
                $user->name = $data['name'];
            }

            if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
                $changes['email'] = ['from' => $user->email, 'to' => $data['email']];
                $user->email = $data['email'];
            }

            if (array_key_exists('username', $data) && $data['username'] !== $user->username) {
                $changes['username'] = ['from' => $user->username, 'to' => $data['username']];
                $user->username = $data['username'];
            }

            if (array_key_exists('status', $data) && $data['status'] !== $this->statusValue($user)) {
                $changes['status'] = ['from' => $this->statusValue($user), 'to' => $data['status']];
                $user->status = $data['status'];
            }

            if (! empty($data['role'])) {
                $role = $this->resolveRole($data['role']);
                if ($role->id !== $user->role_id) {
                    $changes['role'] = ['from' => $user->role?->name, 'to' => $role->name];
                    $user->role_id = $role->id;
                    // One role per account: mirror the pivot to the same value.
                    $user->roles()->sync([$role->id]);
                }
            }

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
                $changes['password'] = 'reset';
            }

            $user->save();

            if (array_key_exists('employee_id', $data)) {
                $linked = ! empty($data['employee_id'])
                    ? $this->linkEmployee($user, (int) $data['employee_id'])
                    : $this->linkEmployee($user, null);

                if ($linked !== null) {
                    $changes['employee_id'] = $linked;
                }
            }

            return [$user, $changes];
        });

        if ($changes !== []) {
            $this->activity->log(
                activityType: 'admin.user.update',
                description: sprintf('Updated user %s.', $user->name),
                user: $actor,
                subject: $user,
                metadata: [
                    'changes' => $changes,
                    'role_change' => $previousRole?->name !== ($user->role?->name)
                        ? ['from' => $previousRole?->name, 'to' => $user->role?->name]
                        : null,
                ],
            );
        }

        return $this->find($user->id);
    }

    /**
     * Deactivate a user. The row is never hard-deleted so historical audit
     * entries and foreign keys keep resolving.
     *
     * @return array<string, mixed>
     */
    public function deactivate(int $id, ?User $actor = null): array
    {
        $user = User::query()->with('role')->findOrFail($id);

        $this->guardSelfAction($user, $actor, 'deactivate');

        if ($this->statusOf($user) === UserStatus::Inactive) {
            throw ValidationException::withMessages([
                'user' => 'This account is already inactive.',
            ]);
        }

        $previous = $this->statusOf($user);

        DB::transaction(function () use ($user): void {
            $user->status = UserStatus::Inactive;
            $user->save();
        });

        $this->activity->log(
            activityType: 'admin.user.deactivate',
            description: sprintf('Deactivated user %s.', $user->name),
            user: $actor,
            subject: $user,
            metadata: ['status' => ['from' => $previous?->value, 'to' => UserStatus::Inactive->value]],
        );

        return $this->find($user->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function reactivate(int $id, ?User $actor = null): array
    {
        $user = User::query()->with('role')->findOrFail($id);

        $previous = $this->statusOf($user);

        DB::transaction(function () use ($user): void {
            $user->status = UserStatus::Active;
            $user->save();
        });

        $this->activity->log(
            activityType: 'admin.user.reactivate',
            description: sprintf('Reactivated user %s.', $user->name),
            user: $actor,
            subject: $user,
            metadata: ['status' => ['from' => $previous?->value, 'to' => UserStatus::Active->value]],
        );

        return $this->find($user->id);
    }

    /**
     * Suspend a user. Administrators cannot suspend their own account so a
     * sole admin cannot lock the institution out of the console.
     *
     * @return array<string, mixed>
     */
    public function suspend(int $id, ?User $actor = null): array
    {
        $user = User::query()->with('role')->findOrFail($id);

        $this->guardSelfAction($user, $actor, 'suspend');

        if ($this->statusOf($user) === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'user' => 'This account is already suspended.',
            ]);
        }

        $previous = $this->statusOf($user);

        DB::transaction(function () use ($user): void {
            $user->status = UserStatus::Suspended;
            $user->save();
        });

        $this->activity->log(
            activityType: 'admin.user.suspend',
            description: sprintf('Suspended user %s.', $user->name),
            user: $actor,
            subject: $user,
            metadata: ['status' => ['from' => $previous?->value, 'to' => UserStatus::Suspended->value]],
        );

        return $this->find($user->id);
    }

    /**
     * Reset an account password to a value the operator chose.
     *
     * The password itself is never stored or returned in plaintext; existing
     * API tokens are revoked so a forced reset takes effect immediately.
     *
     * @return array<string, mixed>
     */
    public function resetPassword(int $id, string $password, ?User $actor = null): array
    {
        $user = User::query()->with('role')->findOrFail($id);

        DB::transaction(function () use ($user, $password): void {
            $user->password = Hash::make($password);
            $user->save();
            $user->tokens()->delete();
        });

        $this->activity->log(
            activityType: 'admin.user.password.reset',
            description: sprintf('Reset the password for %s.', $user->name),
            user: $actor,
            subject: $user,
            metadata: ['password' => 'reset'],
        );

        return $this->find($user->id);
    }

    /**
     * Counts and role distribution for the user-management header.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $total = User::query()->count();
        $active = User::query()->where('status', UserStatus::Active->value)->count();
        $inactive = User::query()->where('status', UserStatus::Inactive->value)->count();
        $suspended = User::query()->where('status', UserStatus::Suspended->value)->count();

        return [
            'total_users' => $total,
            'active_users' => $active,
            'inactive_users' => $inactive,
            'suspended_users' => $suspended,
            'unlinked_employees' => Employee::query()->whereNull('user_id')->count(),
            'accounts_without_employee' => User::query()->whereDoesntHave('employee')->count(),
            'by_role' => DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->select('roles.name')
                ->selectRaw('count(*) as total')
                ->groupBy('roles.name')
                ->orderBy('roles.name')
                ->get()
                ->map(fn ($row) => [
                    'role' => $row->name,
                    'label' => RoleEnum::tryFrom($row->name)?->label() ?? $row->name,
                    'total' => (int) $row->total,
                ])
                ->all(),
        ];
    }

    /**
     * Role + permission catalogue for the create/edit form.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name'])->map(fn (Role $role) => [
                'id' => $role->id,
                'value' => $role->name,
                'label' => RoleEnum::tryFrom($role->name)?->label() ?? $role->name,
            ])->all(),
            'statuses' => array_map(fn (UserStatus $case) => [
                'value' => $case->value,
                'label' => ucfirst($case->name),
            ], UserStatus::cases()),
            'unlinked_employees' => Employee::query()
                ->with('department:id,name')
                ->whereNull('user_id')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number', 'department_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                    'department' => $employee->department?->name,
                ])->all(),
            // Every employee, whether or not they already hold an account.
            // The create form uses the nested account block to block a second
            // login and point at the existing one.
            'employees' => Employee::query()
                ->with(['department:id,name', 'user:id,name,email,username,status'])
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number', 'department_id', 'user_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                    'department' => $employee->department?->name,
                    'account' => $employee->user === null ? null : [
                        'id' => $employee->user->id,
                        'name' => $employee->user->name,
                        'email' => $employee->user->email,
                        'username' => $employee->user->username,
                        'status' => $employee->user->status instanceof UserStatus
                            ? $employee->user->status->value
                            : $employee->user->status,
                    ],
                ])->all(),
        ];
    }

    /**
     * Move one employee's login link onto `$user`, safely.
     *
     * `employees.user_id` is unique, so any employee currently pointing at
     * this account is unlinked first and an employee owned by a DIFFERENT
     * account is rejected rather than silently re-pointed.
     *
     * @return int|null the employee id, or null when the link was cleared
     */
    private function linkEmployee(User $user, ?int $employeeId): ?int
    {
        $current = Employee::query()->where('user_id', $user->id)->first();

        if ($employeeId === null) {
            if ($current) {
                $current->forceFill(['user_id' => null])->save();
            }

            return null;
        }

        $employee = Employee::query()->find($employeeId);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'That employee record no longer exists.',
            ]);
        }

        if ($employee->user_id !== null && (int) $employee->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'employee_id' => 'That employee is already linked to another account.',
            ]);
        }

        if ($current !== null && (int) $current->id !== $employeeId) {
            // The pivot uniqueness of employees.user_id would fail otherwise.
            $current->forceFill(['user_id' => null])->save();
        }

        $employee->forceFill(['user_id' => $user->id])->save();

        return $employee->id;
    }

    private function guardSelfAction(User $user, ?User $actor, string $action): void
    {
        if ($actor !== null && $actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => "You cannot $action your own account.",
            ]);
        }
    }

    private function resolveRole(string $name): Role
    {
        if (RoleEnum::tryFrom($name) === null) {
            throw ValidationException::withMessages([
                'role' => 'Unknown role. Valid roles: '.implode(', ', array_column(RoleEnum::cases(), 'value')),
            ]);
        }

        return Role::query()->where('name', $name)->firstOrFail();
    }

    /**
     * Resolve a role from a possibly-null stored name (used for audit diffs).
     */
    private function resolveRoleByName(?string $name): ?Role
    {
        if ($name === null || $name === '') {
            return null;
        }

        return Role::query()->where('name', $name)->first();
    }

    private function statusValue(User $user): ?string
    {
        return $this->statusOf($user)?->value;
    }

    /**
     * Normalise the UserStatus-cast attribute into an enum instance.
     */
    private function statusOf(User $user): ?UserStatus
    {
        return $user->status instanceof UserStatus
            ? $user->status
            : UserStatus::tryFrom((string) $user->status);
    }

    /* ------------------------------------------------------------------ */
    /* Row serialisation */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        $status = $this->statusOf($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'status' => $status?->value,
            'status_label' => $status?->name,
            'can_authenticate' => $status?->canAuthenticate() ?? false,
            'is_active' => $status === UserStatus::Active,
            'role' => $user->role?->name,
            'role_label' => $user->role ? (RoleEnum::tryFrom($user->role->name)?->label() ?? $user->role->name) : null,
            'employee_id' => $user->employee?->id,
            'employee_name' => $user->employee?->full_name,
            'employee_number' => $user->employee?->employee_number,
            'department' => $user->employee?->department?->name,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
