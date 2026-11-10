<?php

namespace Tests\Support;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Reusable helpers for creating role-bound users and linked employee
 * profiles inside feature tests.
 */
trait CreatesUsers
{
    protected function seedRoles(): void
    {
        $this->seed([RolePermissionSeeder::class]);
    }

    protected function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    protected function roleUser(Role $role): User
    {
        return User::factory()->create(['role_id' => $this->roleId($role)]);
    }

    protected function adminUser(): User
    {
        return $this->roleUser(Role::Admin);
    }

    protected function hrUser(): User
    {
        return $this->roleUser(Role::Hr);
    }

    protected function employeeUser(?Department $department = null, ?User $user = null): Employee
    {
        $manager = $user ?? $this->roleUser(Role::Employee);

        return Employee::factory()->create(array_filter([
            'department_id' => $department?->id,
            'user_id' => $manager->id,
        ]));
    }

    protected function departmentHeadUser(Department $department): User
    {
        $user = $this->roleUser(Role::DepartmentHead);
        $this->employeeUser($department, $user);

        return $user;
    }

    /**
     * Authenticate a user against the Sanctum stateful guard used by the
     * SPA API (replicates the browser cookie-flow guard).
     */
    protected function loginAs(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }
}
