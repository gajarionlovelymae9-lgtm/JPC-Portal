<?php

namespace Tests\Feature\Api\TaskManagement;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Factories\TaskFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function adminUser(): User
    {
        $adminRole = RoleModel::query()->where('name', Role::Admin->value)->firstOrFail();

        return User::factory()->create(['role_id' => $adminRole->id]);
    }

    private function employeeUser(): User
    {
        $empRole = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $empRole->id]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function assignmentFor(User $user, array $attributes = []): TaskAssignment
    {
        $employee = Employee::query()->where('user_id', $user->id)->firstOrFail();

        return TaskAssignment::factory()->create(
            array_merge(['employee_id' => $employee->id], $attributes)
        );
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    // ── TASK CRUD ──────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_tasks(): void
    {
        $this->getJson('/api/v1/tasks')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_list_tasks(): void
    {
        Task::factory()->count(3)->create();
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_can_create_task(): void
    {
        $this->login($this->adminUser());
        $task = TaskFactory::new()->make([
            'department_id' => Department::factory()->create()->id,
        ]);

        $this->postJson('/api/v1/tasks', $task->toArray())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Task created successfully')
            ->assertJsonStructure(['data' => ['task_code', 'title', 'status']]);
    }

    public function test_admin_can_view_task(): void
    {
        $this->login($this->adminUser());
        $task = Task::factory()->create();

        $this->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_update_task(): void
    {
        $this->login($this->adminUser());
        $task = Task::factory()->create();

        $this->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Updated Title'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_admin_can_delete_task(): void
    {
        $this->login($this->adminUser());
        $task = Task::factory()->create();

        $this->deleteJson("/api/v1/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($task);
    }

    public function test_admin_can_cancel_task(): void
    {
        $this->login($this->adminUser());
        $task = Task::factory()->create(['status' => 'assigned']);

        $this->postJson("/api/v1/tasks/{$task->id}/cancel")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_create_task_without_auth_returns_401(): void
    {
        $this->postJson('/api/v1/tasks', ['title' => 'Test'])
            ->assertUnauthorized();
    }

    public function test_create_task_returns_422_on_invalid_data(): void
    {
        $this->login($this->adminUser());

        $this->postJson('/api/v1/tasks', ['title' => ''])
            ->assertStatus(422);
    }

    // ── FILTERING, SEARCH, SORTING, PAGINATION ─────────────────

    public function test_tasks_can_be_filtered_by_status(): void
    {
        Task::factory()->count(2)->create(['status' => 'completed']);
        Task::factory()->count(1)->create(['status' => 'draft']);
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks?status=completed')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_tasks_can_be_searched(): void
    {
        $task = Task::factory()->create(['title' => 'Monthly Report']);
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks?search=Monthly')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $task->id);
    }

    public function test_tasks_can_be_sorted(): void
    {
        Task::factory()->create(['title' => 'Z Task', 'created_at' => now()->subDays(2)]);
        Task::factory()->create(['title' => 'A Task', 'created_at' => now()->subDays(1)]);
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks?sort=title&direction=asc')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'A Task');
    }

    public function test_tasks_pagination(): void
    {
        Task::factory()->count(20)->create();
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks?per_page=15')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 15);
    }

    // ── ASSIGNMENT ─────────────────────────────────────────────

    public function test_admin_can_assign_employee(): void
    {
        $this->login($this->adminUser());
        $task = Task::factory()->create(['status' => 'draft']);
        $employee = Employee::factory()->create();

        $this->postJson("/api/v1/tasks/{$task->id}/assignments", [
            'employee_id' => $employee->id,
            'due_date' => now()->addDays(7)->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_assign_returns_422_on_invalid_data(): void
    {
        $this->login($this->adminUser());
        $task = Task::factory()->create(['status' => 'draft']);

        $this->postJson("/api/v1/tasks/{$task->id}/assignments", ['employee_id' => 9999])
            ->assertStatus(422);
    }

    // ── PROGRESS ───────────────────────────────────────────────

    public function test_employee_can_update_progress(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user, ['status' => 'in_progress']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/progress", [
            'percentage' => 50,
            'comment' => 'Half done',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_progress_returns_422_for_invalid_percentage(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user, ['status' => 'in_progress']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/progress", [
            'percentage' => 101,
        ])
            ->assertStatus(422);
    }

    public function test_employee_can_view_progress_history(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user, ['status' => 'in_progress']);

        $this->getJson("/api/v1/task-assignments/{$assignment->id}/progress")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // ── REVIEW ─────────────────────────────────────────────────

    public function test_review_returns_422_on_invalid_decision(): void
    {
        $this->login($this->adminUser());
        $assignment = TaskAssignment::factory()->create(['status' => 'submitted']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/review", [
            'decision' => 'invalid',
            'remarks' => 'Test',
        ])
            ->assertStatus(422);
    }

    public function test_unauthenticated_cannot_review(): void
    {
        $assignment = TaskAssignment::factory()->create(['status' => 'submitted']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/review", [
            'decision' => 'approved',
            'remarks' => 'Test',
        ])
            ->assertUnauthorized();
    }

    public function test_admin_can_view_reviews(): void
    {
        $this->login($this->adminUser());
        $assignment = TaskAssignment::factory()->create(['status' => 'submitted']);

        $this->getJson("/api/v1/task-assignments/{$assignment->id}/reviews")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_approve_assignment(): void
    {
        $this->login($this->adminUser());
        $assignment = TaskAssignment::factory()->create(['status' => 'submitted']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/review", [
            'decision' => 'approved',
            'remarks' => 'Looks good',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.decision', 'approved');
    }

    public function test_admin_can_reject_assignment(): void
    {
        $this->login($this->adminUser());
        $assignment = TaskAssignment::factory()->create(['status' => 'submitted']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/review", [
            'decision' => 'rejected',
            'remarks' => 'Incomplete',
        ])
            ->assertOk()
            ->assertJsonPath('data.decision', 'rejected');
    }

    // ── SUBMIT & RESUBMIT ──────────────────────────────────────

    public function test_employee_can_submit_assignment(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user, ['status' => 'in_progress']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'remarks' => 'Done',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_employee_can_resubmit_rejected_assignment(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user, ['status' => 'rejected']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/resubmit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    // ── SECURITY: AUTHORIZATION, 404, IDOR ─────────────────────

    public function test_employee_cannot_access_admin_summary(): void
    {
        $this->login($this->employeeUser());

        $this->getJson('/api/v1/tasks/summary')->assertForbidden();
        $this->getJson('/api/v1/employees/progress')->assertForbidden();
        $this->getJson('/api/v1/departments/progress')->assertForbidden();
        $this->getJson('/api/v1/employees/monthly-achievement')->assertForbidden();
    }

    public function test_task_not_found_returns_404(): void
    {
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks/999999')->assertNotFound();
    }

    public function test_employee_can_view_own_assignment(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user, ['status' => 'in_progress']);

        $this->getJson("/api/v1/task-assignments/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_employee_cannot_view_another_employees_assignment(): void
    {
        $userA = $this->employeeUser();
        $userB = $this->employeeUser();
        $this->login($userA);
        $assignmentB = $this->assignmentFor($userB, ['status' => 'in_progress']);

        $this->getJson("/api/v1/task-assignments/{$assignmentB->id}")
            ->assertForbidden();
    }

    public function test_employee_cannot_view_another_employees_task_list(): void
    {
        $userA = $this->employeeUser();
        $userB = $this->employeeUser();
        $this->login($userA);
        $employeeB = Employee::query()->where('user_id', $userB->id)->firstOrFail();

        $this->getJson("/api/v1/tasks/employees/{$employeeB->id}/tasks")
            ->assertForbidden();
    }

    public function test_admin_can_view_task_summary(): void
    {
        Task::factory()->count(3)->create();
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_tasks', 3);
    }

    public function test_admin_can_view_monthly_progress(): void
    {
        $this->login($this->adminUser());

        $this->getJson('/api/v1/tasks/monthly-progress?year=2026&month=8')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'completed', 'pending', 'overdue', 'on_time', 'late']]);
    }

    public function test_admin_can_view_employee_progress(): void
    {
        Employee::factory()->count(3)->create();
        $this->login($this->adminUser());

        $this->getJson('/api/v1/employees/progress')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_view_monthly_achievement(): void
    {
        Employee::factory()->count(2)->create();
        $this->login($this->adminUser());

        $this->getJson('/api/v1/employees/monthly-achievement?year=2026&month=8')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['rank', 'employee_id', 'completion_rate']]]);
    }

    public function test_admin_can_view_department_progress(): void
    {
        $this->login($this->adminUser());

        $this->getJson('/api/v1/departments/progress')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // ── MY TASKS ───────────────────────────────────────────────

    public function test_employee_can_view_my_tasks(): void
    {
        $user = $this->employeeUser();
        $this->login($user);

        $this->getJson('/api/v1/tasks/me')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_employee_can_view_my_tasks_summary(): void
    {
        $user = $this->employeeUser();
        $this->login($user);

        $this->getJson('/api/v1/tasks/me/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'completed', 'in_progress', 'submitted', 'under_review', 'rejected', 'overdue']]);
    }
}
