<?php

namespace Tests\Feature\Api\ActivityLogs;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
    }

    private function employeeUser(): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function departmentHeadUser(Department $department): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    private function departmentEmployee(Department $department): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }

    private function logFor(User $user, array $attributes = []): ActivityLog
    {
        return ActivityLog::factory()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    // ── ACCESS CONTROL ─────────────────────────────────────────

    public function test_unauthenticated_cannot_access_activity_logs(): void
    {
        $this->getJson('/api/v1/activity-logs')->assertUnauthorized();
    }

    public function test_employee_cannot_access_activity_logs(): void
    {
        $this->login($this->employeeUser());

        $this->getJson('/api/v1/activity-logs')->assertForbidden();
    }

    public function test_admin_can_list_activity_logs(): void
    {
        $admin = $this->adminUser();
        $this->logFor($admin, ['activity_type' => 'task.created']);
        $this->login($admin);

        $this->getJson('/api/v1/activity-logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [[
                'id', 'activity_type', 'action', 'description', 'user_id', 'user_name',
                'subject_type', 'subject_id', 'metadata', 'task_code', 'created_at',
            ]]]);
    }

    // ── DEPARTMENT-HEAD SCOPING ────────────────────────────────

    public function test_department_head_sees_only_own_department_activity(): void
    {
        $deptA = Department::factory()->create(['name' => 'Dept A']);
        $deptB = Department::factory()->create(['name' => 'Dept B']);
        $admin = $this->adminUser();
        $head = $this->departmentHeadUser($deptA);
        $empA = $this->departmentEmployee($deptA);
        $empB = $this->departmentEmployee($deptB);

        $taskA = Task::factory()->create(['department_id' => $deptA->id, 'due_date' => now()->addDays(5)]);
        $taskB = Task::factory()->create(['department_id' => $deptB->id, 'due_date' => now()->addDays(5)]);

        $this->logFor($admin, ['activity_type' => 'task.reviewed', 'subject_type' => Task::class, 'subject_id' => $taskA->id]);
        $this->logFor($admin, ['activity_type' => 'task.reviewed', 'subject_type' => Task::class, 'subject_id' => $taskB->id]);
        $this->logFor($empA, ['activity_type' => 'progress.updated']);
        $this->logFor($empB, ['activity_type' => 'progress.updated']);

        $this->login($head);

        $ids = collect($this->getJson('/api/v1/activity-logs')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertCount(2, $ids);
    }

    public function test_department_head_cannot_view_out_of_scope_log(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $other = $this->departmentEmployee($deptB);
        $log = $this->logFor($other, ['activity_type' => 'progress.updated']);

        $this->login($this->departmentHeadUser($deptA));

        $this->getJson("/api/v1/activity-logs/{$log->id}")->assertForbidden();
    }

    // ── SHOW / IMMUTABILITY ────────────────────────────────────

    public function test_show_returns_single_log(): void
    {
        $admin = $this->adminUser();
        $log = $this->logFor($admin, ['activity_type' => 'auth.login']);
        $this->login($admin);

        $this->getJson("/api/v1/activity-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.activity_type', 'auth.login')
            ->assertJsonPath('data.user_name', $admin->name);
    }

    public function test_activity_logs_are_immutable(): void
    {
        $admin = $this->adminUser();
        $log = $this->logFor($admin);
        $this->login($admin);

        $this->putJson("/api/v1/activity-logs/{$log->id}", ['description' => 'tampered'])->assertForbidden();
        $this->postJson("/api/v1/activity-logs/{$log->id}", ['description' => 'tampered'])->assertForbidden();
        $this->deleteJson("/api/v1/activity-logs/{$log->id}")->assertForbidden();
        $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'description' => $log->description]);
    }

    // ── FILTERS ────────────────────────────────────────────────

    public function test_filters_action_search_and_subject(): void
    {
        $admin = $this->adminUser();
        $task = Task::factory()->create(['task_code' => 'TASK-2026-0001', 'due_date' => now()->addDays(3)]);

        $this->logFor($admin, ['activity_type' => 'task.reviewed', 'description' => 'Approved TASK-2026-0001.', 'subject_type' => Task::class, 'subject_id' => $task->id, 'metadata' => ['task_code' => 'TASK-2026-0001']]);
        $this->logFor($admin, ['activity_type' => 'auth.login', 'description' => 'Signed in to the portal.', 'subject_type' => null, 'subject_id' => null]);

        $this->login($admin);

        $this->getJson('/api/v1/activity-logs?action=task.reviewed')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/activity-logs?search=TASK-2026-0001')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/activity-logs?subject=Task')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/activity-logs?subject=Task&subject_id='.$task->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/activity-logs?search='.urlencode('Signed in'))->assertJsonCount(1, 'data');
    }
}