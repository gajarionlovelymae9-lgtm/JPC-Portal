<?php

namespace Tests\Feature\Api\Dashboard;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 14 release-critical dashboard defects:
 *   1. Department-head dashboard 500 (phantom task columns).
 *   2. Employee dashboard 500 (NotificationService::forUser() misuse).
 *   3. HR dashboard 403 (missing hr.dashboard.view permission) + missing
 *      HrDashboardController::dashboard() handler.
 */
class DashboardRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    private function hrUser(): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Hr)]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function departmentHeadUser(): User
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        $task = Task::factory()->create(['department_id' => $department->id, 'status' => 'in_progress']);
        $emp = Employee::factory()->create(['department_id' => $department->id]);
        TaskAssignment::factory()->create([
            'employee_id' => $emp->id,
            'task_id' => $task->id,
            'progress_percentage' => 40,
            'due_date' => now()->addDays(5),
            'status' => 'in_progress',
        ]);

        return $user;
    }

    private function employeeUser(): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    public function test_hr_user_can_load_hr_dashboard(): void
    {
        $this->login($this->hrUser());

        $this->getJson('/api/v1/hr/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'total_employees', 'active_employees', 'departments',
            ]]);
    }

    public function test_employee_cannot_access_hr_dashboard(): void
    {
        $this->login($this->employeeUser());

        $this->getJson('/api/v1/hr/dashboard')->assertForbidden();
    }

    public function test_department_head_can_load_department_dashboard(): void
    {
        $this->login($this->departmentHeadUser());

        $this->getJson('/api/v1/department-head/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_employee_can_load_employee_dashboard(): void
    {
        $this->login($this->employeeUser());

        $this->getJson('/api/v1/employee/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}