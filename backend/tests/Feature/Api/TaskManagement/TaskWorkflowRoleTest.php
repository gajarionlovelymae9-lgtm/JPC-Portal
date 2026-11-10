<?php

namespace Tests\Feature\Api\TaskManagement;

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
 * Role-based task creation, assignment, monitoring and authorization.
 */
class TaskWorkflowRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    private function userWithRole(Role $role, ?Department $department = null): User
    {
        $roleModel = RoleModel::query()->where('name', $role->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $roleModel->id]);

        if (in_array($role, [Role::DepartmentHead, Role::Employee], true)) {
            Employee::factory()->create([
                'user_id' => $user->id,
                'department_id' => ($department ?? Department::factory()->create())->id,
                'employment_status' => 'active',
            ]);
        }

        return $user;
    }

    private function employeeIn(Department $department): Employee
    {
        $user = User::factory()->create();

        return Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employment_status' => 'active',
        ]);
    }

    private function validTaskPayload(Department $department, array $employeeIds, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Prepare monthly faculty accomplishment report',
            'description' => 'Consolidate all faculty submissions for the month.',
            'priority' => 'high',
            'department_id' => $department->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'required_output' => 'Signed consolidated PDF report',
            'target_value' => 1,
            'target_unit' => 'report',
            'assigned_employee_ids' => $employeeIds,
        ], $overrides);
    }

    public function test_admin_can_create_task_with_multiple_assignees(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $department = Department::factory()->create();
        $first = $this->employeeIn($department);
        $second = $this->employeeIn($department);

        $this->login($admin);

        $response = $this->postJson('/api/v1/tasks', $this->validTaskPayload(
            $department,
            [$first->id, $second->id],
        ));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonCount(2, 'data.assignments')
            ->assertJsonPath('data.target_value', '1.00');

        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'status' => 'assigned',
            'department_id' => $department->id,
        ]);

        $this->assertDatabaseCount('task_assignments', 2);
        $this->assertDatabaseHas('task_assignments', ['task_id' => $taskId, 'employee_id' => $first->id]);
        $this->assertDatabaseHas('task_assignments', ['task_id' => $taskId, 'employee_id' => $second->id]);

        foreach ([$first, $second] as $employee) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $employee->user_id,
                'type' => 'task_assigned',
                'task_id' => $taskId,
            ]);
        }
    }

    public function test_admin_task_creation_rejects_due_date_before_start_date(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $department = Department::factory()->create();
        $employee = $this->employeeIn($department);

        $this->login($admin);

        $this->postJson('/api/v1/tasks', $this->validTaskPayload($department, [$employee->id], [
            'start_date' => now()->addDays(10)->toDateString(),
            'due_date' => now()->toDateString(),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_department_head_can_create_and_assign_within_their_department(): void
    {
        $department = Department::factory()->create();
        $head = $this->userWithRole(Role::DepartmentHead, $department);
        $faculty = $this->employeeIn($department);

        $this->login($head);

        $response = $this->postJson('/api/v1/department-head/tasks', [
            'title' => 'Submit curriculum monitoring report',
            'description' => 'Department deliverable.',
            'priority' => 'urgent',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addDays(5)->toDateString(),
            'requirements' => 'Use the CHED template',
            'target_value' => 1,
            'target_unit' => 'report',
            'assigned_employee_ids' => [$faculty->id],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.assignments_count', 1);

        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'department_id' => $department->id,
            'status' => 'assigned',
            'priority' => 'urgent',
        ]);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $taskId,
            'employee_id' => $faculty->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $faculty->user_id,
            'type' => 'task_assigned',
            'task_id' => $taskId,
        ]);
    }

    public function test_department_head_cannot_assign_employee_outside_their_department(): void
    {
        $ownDepartment = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $head = $this->userWithRole(Role::DepartmentHead, $ownDepartment);
        $outsider = $this->employeeIn($otherDepartment);

        $this->login($head);

        $this->postJson('/api/v1/department-head/tasks', [
            'title' => 'Cross-department assignment attempt',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addDays(5)->toDateString(),
            'assigned_employee_ids' => [$outsider->id],
        ])->assertForbidden();

        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('task_assignments', 0);
    }

    public function test_department_head_task_creation_requires_an_assignee(): void
    {
        $department = Department::factory()->create();
        $head = $this->userWithRole(Role::DepartmentHead, $department);

        $this->login($head);

        $this->postJson('/api/v1/department-head/tasks', [
            'title' => 'Task with no assignee',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addDays(5)->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_employee_ids']);
    }

    public function test_hr_cannot_create_or_assign_tasks(): void
    {
        $hr = $this->userWithRole(Role::Hr);
        $department = Department::factory()->create();
        $employee = $this->employeeIn($department);

        $this->login($hr);

        $this->postJson('/api/v1/tasks', $this->validTaskPayload($department, [$employee->id]))
            ->assertForbidden();

        $this->postJson('/api/v1/department-head/tasks', [
            'title' => 'HR task attempt',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addDays(5)->toDateString(),
            'assigned_employee_ids' => [$employee->id],
        ])->assertForbidden();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_employee_cannot_create_tasks(): void
    {
        $department = Department::factory()->create();
        $employeeUser = $this->userWithRole(Role::Employee, $department);
        $colleague = $this->employeeIn($department);

        $this->login($employeeUser);

        $this->postJson('/api/v1/tasks', $this->validTaskPayload($department, [$colleague->id]))
            ->assertForbidden();
    }

    public function test_admin_task_monitoring_returns_summary_and_supports_overdue_filter(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $department = Department::factory()->create();
        $employee = $this->employeeIn($department);

        TaskAssignment::factory()->completed()->create(['employee_id' => $employee->id]);
        TaskAssignment::factory()->inProgress()->create(['employee_id' => $employee->id]);
        TaskAssignment::factory()->assigned()->create([
            'employee_id' => $employee->id,
            'due_date' => now()->addDays(5),
        ]);
        $overdue = TaskAssignment::factory()->overdue()->create(['employee_id' => $employee->id]);

        $this->login($admin);

        $response = $this->getJson('/api/v1/admin/task-monitoring?per_page=50');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total', 4)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.in_progress', 1)
            ->assertJsonPath('data.summary.not_started', 1)
            ->assertJsonPath('data.summary.overdue', 1);

        $this->getJson('/api/v1/admin/task-monitoring?overdue=1&per_page=50')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.assignment_id', $overdue->id)
            ->assertJsonPath('data.items.0.status', 'overdue');
    }

    public function test_task_monitoring_options_include_employee_department(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $department = Department::factory()->create(['name' => 'College of Education']);
        $employee = $this->employeeIn($department);

        $this->login($admin);

        $this->getJson('/api/v1/admin/monitoring-options')
            ->assertOk()
            ->assertJsonPath('data.employees.0.department_id', $department->id)
            ->assertJsonPath('data.employees.0.employment_status', 'active')
            ->assertJsonFragment(['id' => $employee->id]);
    }

    public function test_task_record_persists_after_creation(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $department = Department::factory()->create();
        $employee = $this->employeeIn($department);

        $this->login($admin);

        $taskId = $this->postJson('/api/v1/tasks', $this->validTaskPayload($department, [$employee->id]))
            ->assertCreated()
            ->json('data.id');

        $task = Task::query()->find($taskId);

        $this->assertNotNull($task);
        $this->assertSame('assigned', $task->status);
        $this->assertSame($department->id, $task->department_id);
        $this->assertSame(1, $task->assignments()->count());
    }
}
