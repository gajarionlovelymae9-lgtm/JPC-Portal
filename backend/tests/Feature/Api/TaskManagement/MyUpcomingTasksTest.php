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

class MyUpcomingTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function employeeUser(): User
    {
        $role = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function assignmentFor(User $user, Task $task, array $attributes = []): TaskAssignment
    {
        $employee = Employee::query()->where('user_id', $user->id)->firstOrFail();

        return TaskAssignment::factory()->create(array_merge([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'in_progress',
            'due_date' => now()->addDays(3),
            'progress_percentage' => 40,
        ], $attributes));
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    public function test_unauthenticated_cannot_access_upcoming(): void
    {
        $this->getJson('/api/v1/me/tasks/upcoming')->assertUnauthorized();
    }

    public function test_user_without_employee_profile_is_forbidden(): void
    {
        $adminRole = RoleModel::query()->where('name', Role::Admin->value)->firstOrFail();
        $this->login(User::factory()->create(['role_id' => $adminRole->id]));

        $this->getJson('/api/v1/me/tasks/upcoming')->assertForbidden();
    }

    public function test_returns_only_actionable_future_assignments_ordered_by_due_date(): void
    {
        $user = $this->employeeUser();
        $dept = Department::factory()->create();

        $taskIn7 = Task::factory()->create(['department_id' => $dept->id, 'due_date' => now()->addDays(7)]);
        $taskIn1 = Task::factory()->create(['department_id' => $dept->id, 'due_date' => now()->addDays(1)]);
        $taskToday = Task::factory()->create(['department_id' => $dept->id, 'due_date' => now()]);
        $overdue = Task::factory()->create(['department_id' => $dept->id, 'due_date' => now()->subDay()]);
        $completed = Task::factory()->create(['department_id' => $dept->id, 'due_date' => now()->addDays(2)]);

        $this->assignmentFor($user, $taskIn7);
        $this->assignmentFor($user, $taskIn1, ['progress_percentage' => 60]);
        $this->assignmentFor($user, $taskToday);
        $this->assignmentFor($user, $overdue, ['due_date' => now()->subDay()->startOfDay()]);   // past due -> excluded
        $this->assignmentFor($user, $completed, ['status' => 'completed', 'completed_at' => now()]);
        $this->assignmentFor($user, Task::factory()->create(['department_id' => $dept->id, 'due_date' => now()->addDays(4)]), ['status' => 'submitted']);

        $this->login($user);

        $response = $this->getJson('/api/v1/me/tasks/upcoming')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [[
                'assignment_id', 'task_id', 'task_code', 'title', 'priority', 'due_date', 'progress_percentage', 'status',
            ]]]);

        $dueDates = collect($response->json('data'))->pluck('due_date')->all();
        $this->assertSame($dueDates, collect($dueDates)->sort()->values()->all());
    }
}