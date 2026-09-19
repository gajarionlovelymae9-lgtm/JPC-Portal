<?php

namespace Tests\Feature\Api\Reports;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

abstract class ReportTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
        return User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
    }

    protected function hrUser(): User
    {
        return User::factory()->create(['role_id' => $this->roleId(Role::Hr)]);
    }

    protected function employeeUser(?Department $department = null, bool $withEmployeeProfile = true): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        if ($withEmployeeProfile) {
            $this->employee($department, $user);
        }

        return $user;
    }

    /**
     * Create an employee (optionally linked to a user) in the department.
     */
    protected function employee(?Department $department = null, ?User $user = null): Employee
    {
        return Employee::factory()->create(array_filter([
            'department_id' => $department?->id,
            'user_id' => $user?->id,
        ]));
    }

    /**
     * Employee-role user plus their linked employee record.
     *
     * @return array{0: User, 1: Employee}
     */
    protected function employeeAndUser(Department $department): array
    {
        $user = $this->employeeUser($department);

        return [$user, $user->employee];
    }

    protected function departmentHeadUser(Department $department): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $this->employee($department, $user);

        return $user;
    }

    protected function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    protected function taskIn(Department $department, array $attributes = []): Task
    {
        return Task::factory()->create(array_merge(['department_id' => $department->id], $attributes));
    }

    protected function completedAssignment(
        Employee $employee,
        Department $department,
        Carbon $completedAt,
        Carbon $dueDate,
    ): TaskAssignment {
        $task = $this->taskIn($department, [
            'status' => 'completed',
            'due_date' => $dueDate,
            'completed_at' => $completedAt,
        ]);

        return TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'completed',
            'assigned_at' => $completedAt->copy()->subDays(5),
            'due_date' => $dueDate,
            'completed_at' => $completedAt,
        ]);
    }

    /**
     * Assignment that has not completed yet (status forwarded to the task).
     */
    protected function openAssignment(
        Employee $employee,
        Department $department,
        string $status,
        Carbon $dueDate,
    ): TaskAssignment {
        $task = $this->taskIn($department, ['status' => $status, 'due_date' => $dueDate, 'completed_at' => null]);

        return TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => $status,
            'assigned_at' => now()->subDays(3),
            'due_date' => $dueDate,
            'completed_at' => null,
        ]);
    }

    /**
     * Floating-point tolerance assertion for server-rounded percentages.
     */
    protected function assertScore(float $expected, mixed $actual, float $delta = 0.001): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, $delta);
    }

    /**
     * A timestamp safely inside the current calendar month (day 10, 12:00).
     */
    protected function thisMonth(): Carbon
    {
        return Carbon::create(now()->year, now()->month, 10, 12, 0, 0);
    }
}