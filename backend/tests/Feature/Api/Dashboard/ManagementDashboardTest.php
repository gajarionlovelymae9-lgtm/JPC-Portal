<?php

namespace Tests\Feature\Api\Dashboard;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
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

    private function adminUser(): User
    {
        return User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
    }

    private function hrUser(): User
    {
        return User::factory()->create(['role_id' => $this->roleId(Role::Hr)]);
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

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    private function taskIn(Department $department, array $attributes = []): Task
    {
        return Task::factory()->create(array_merge(['department_id' => $department->id], $attributes));
    }

    private function assignmentFor(Employee $employee, Task $task, array $attributes = []): TaskAssignment
    {
        return TaskAssignment::factory()->create(array_merge([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
        ], $attributes));
    }

    // ── ACCESS CONTROL ─────────────────────────────────────────

    public function test_unauthenticated_cannot_access_management_dashboard(): void
    {
        $this->getJson('/api/v1/admin/dashboard/summary')->assertUnauthorized();
    }

    public function test_employee_cannot_access_management_dashboard(): void
    {
        $this->login($this->employeeUser());

        $this->getJson('/api/v1/admin/dashboard/summary')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard/employees')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard/review-queue')->assertForbidden();
    }

    public function test_admin_can_access_summary(): void
    {
        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'total_employees', 'total_tasks', 'completed_tasks', 'pending_tasks',
                'overdue_tasks', 'submitted_tasks', 'under_review_tasks', 'rejected_tasks',
                'cancelled_tasks', 'on_time', 'late', 'completion_rate', 'on_time_rate',
            ]]);
    }

    public function test_hr_can_access_management_dashboard(): void
    {
        $this->login($this->hrUser());

        $this->getJson('/api/v1/admin/dashboard/summary')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/admin/dashboard/employees')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/admin/dashboard/review-queue')->assertOk()->assertJsonPath('success', true);
    }

    public function test_hr_can_view_employee_progress_analytics_endpoint(): void
    {
        $this->login($this->hrUser());

        $this->getJson('/api/v1/employees/progress')->assertOk()->assertJsonPath('success', true);
    }

    // ── DEPARTMENT HEAD SCOPING ────────────────────────────────

    public function test_department_head_sees_only_own_department(): void
    {
        $deptA = Department::factory()->create(['name' => 'Dept A']);
        $deptB = Department::factory()->create(['name' => 'Dept B']);
        $head = $this->departmentHeadUser($deptA);
        $empA = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        $this->taskIn($deptA, ['status' => 'in_progress']);
        $this->taskIn($deptB, ['status' => 'completed', 'completed_at' => now()->subDay(), 'due_date' => now()->addDay()]);

        $this->login($head);

        $this->getJson('/api/v1/admin/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.total_tasks', 1);

        $employeeIds = collect($this->getJson('/api/v1/admin/dashboard/employees')->assertOk()->json('data'))
            ->pluck('employee_id');

        $this->assertContains($empA->id, $employeeIds);
        $this->assertNotContains($empB->id, $employeeIds);
    }

    public function test_department_head_cannot_request_another_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $this->login($this->departmentHeadUser($deptA));

        $this->getJson('/api/v1/admin/dashboard/summary?department_id='.$deptB->id)->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard/employees?department_id='.$deptB->id)->assertForbidden();
    }

    public function test_department_head_cannot_view_employee_from_another_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $other = Employee::factory()->create(['department_id' => $deptB->id]);
        $this->login($this->departmentHeadUser($deptA));

        $this->getJson("/api/v1/admin/dashboard/employees/{$other->id}/progress")->assertForbidden();
    }

    public function test_hr_can_filter_summary_by_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $this->taskIn($deptA, ['status' => 'in_progress']);
        $this->taskIn($deptB, ['status' => 'in_progress']);

        $this->login($this->hrUser());

        $this->getJson('/api/v1/admin/dashboard/summary?department_id='.$deptA->id)
            ->assertOk()
            ->assertJsonPath('data.total_tasks', 1);
    }

    // ── FILTER OPTIONS ─────────────────────────────────────────

    public function test_options_lists_departments_and_academic_years(): void
    {
        Department::factory()->count(2)->create();
        AcademicYear::factory()->create(['name' => '2025-2026']);

        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'departments' => [['id', 'name']],
                'academic_years' => [['id', 'name', 'status']],
            ]])
            ->assertJsonCount(2, 'data.departments')
            ->assertJsonCount(1, 'data.academic_years');
    }

    public function test_department_head_options_are_restricted_to_own_department(): void
    {
        $deptA = Department::factory()->create(['name' => 'Owned Dept']);
        $deptB = Department::factory()->create(['name' => 'Other Dept']);
        $this->login($this->departmentHeadUser($deptA));

        $this->getJson('/api/v1/admin/dashboard/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.departments')
            ->assertJsonPath('data.departments.0.id', $deptA->id);
    }

    public function test_employee_cannot_access_options(): void
    {
        $this->login($this->employeeUser());

        $this->getJson('/api/v1/admin/dashboard/options')->assertForbidden();
    }

    // ── SUMMARY METRICS ────────────────────────────────────────

    public function test_summary_counts_and_rates(): void
    {
        $dept = Department::factory()->create();
        $this->login($this->adminUser());

        $this->taskIn($dept, ['status' => 'in_progress']);

        for ($i = 0; $i < 8; $i++) {
            $this->taskIn($dept, [
                'status' => 'completed',
                'due_date' => now()->addDays(10),
                'completed_at' => now()->subDays(2),
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->taskIn($dept, [
                'status' => 'completed',
                'due_date' => now()->subDays(5),
                'completed_at' => now()->subDay(),
            ]);
        }
        $this->taskIn($dept, ['status' => 'draft']);

        $this->getJson('/api/v1/admin/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.total_tasks', 12)
            ->assertJsonPath('data.completed_tasks', 10)
            ->assertJsonPath('data.completion_rate', 83.33)
            ->assertJsonPath('data.on_time', 8)
            ->assertJsonPath('data.late', 2)
            ->assertJsonPath('data.on_time_rate', 80);
    }

    // ── TABLES ─────────────────────────────────────────────────

    public function test_employees_endpoint_paginates_and_searches(): void
    {
        Employee::factory()->count(2)->create(['last_name' => 'Smith']);
        Employee::factory()->create(['last_name' => 'Taylor']);
        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/employees?search=Smith')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['employee_id', 'employee_name', 'completion_rate']], 'meta' => ['total']]);
    }

    public function test_overdue_endpoint_lists_only_overdue_tasks(): void
    {
        $dept = Department::factory()->create();
        $this->taskIn($dept, ['status' => 'in_progress']);
        $overdue = $this->taskIn($dept, ['status' => 'in_progress', 'due_date' => now()->subDays(3)]);

        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.task_id', $overdue->id)
            ->assertJsonPath('data.0.days_overdue', 3);

        $this->getJson('/api/v1/admin/dashboard/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_review_queue_lists_pending_submissions_only(): void
    {
        $dept = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $dept->id]);

        $submittedTask = $this->taskIn($dept, ['status' => 'submitted']);
        $underReviewTask = $this->taskIn($dept, ['status' => 'under_review']);
        $completedTask = $this->taskIn($dept, [
            'status' => 'completed',
            'completed_at' => now()->subDay(),
            'due_date' => now()->addDay(),
        ]);

        $this->assignmentFor($employee, $submittedTask, ['status' => 'submitted']);
        $this->assignmentFor($employee, $underReviewTask, ['status' => 'under_review']);
        $this->assignmentFor($employee, $completedTask, ['status' => 'completed', 'completed_at' => now()->subDay(), 'due_date' => now()->addDay()]);

        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/review-queue')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['assignment_id', 'task_title', 'employee_name', 'status', 'latest_review']]]);
    }

    // ── MONTHLY METRICS ────────────────────────────────────────

    public function test_monthly_progress_filters_by_month(): void
    {
        $dept = Department::factory()->create();
        $anchor = now()->subMonths(3);

        $this->taskIn($dept, [
            'status' => 'completed',
            'completed_at' => $anchor,
            'due_date' => $anchor->copy()->addDays(5),
        ]);

        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/monthly-progress?year='.$anchor->year.'&month='.$anchor->month)
            ->assertOk()
            ->assertJsonPath('data.year', $anchor->year)
            ->assertJsonPath('data.month', $anchor->month)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.on_time', 1);
    }

    public function test_monthly_achievement_ranks_by_completion(): void
    {
        $dept = Department::factory()->create();
        $empA = Employee::factory()->create(['department_id' => $dept->id]);
        $empB = Employee::factory()->create(['department_id' => $dept->id]);

        $taskA = $this->taskIn($dept, ['status' => 'completed', 'completed_at' => now(), 'due_date' => now()->addDay()]);
        $taskB = $this->taskIn($dept, ['status' => 'in_progress']);
        $this->assignmentFor($empA, $taskA, ['status' => 'completed', 'completed_at' => now(), 'due_date' => now()->addDay()]);
        $this->assignmentFor($empB, $taskB, ['status' => 'in_progress']);

        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/monthly-achievement?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.employee_id', $empA->id)
            ->assertJsonPath('data.1.rank', 2)
            ->assertJsonPath('data.1.employee_id', $empB->id);
    }

    // ── TARGET VS ACTUAL ───────────────────────────────────────

    public function test_target_vs_actual_metrics(): void
    {
        $dept = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $dept->id]);

        $achieved = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);
        EmployeeTarget::factory()->create(['employee_id' => $employee->id]);

        Task::factory()->completed()->create(['employee_target_id' => $achieved->id]);

        $this->login($this->adminUser());

        $this->getJson('/api/v1/admin/dashboard/target-vs-actual?department_id='.$dept->id)
            ->assertOk()
            ->assertJsonPath('data.target', 2)
            ->assertJsonPath('data.achieved', 1)
            ->assertJsonPath('data.remaining', 1)
            ->assertJsonPath('data.achievement_rate', 50);
    }

    // ── EMPLOYEE DETAIL ────────────────────────────────────────

    public function test_admin_can_view_employee_progress_detail(): void
    {
        $dept = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $dept->id]);
        $task = $this->taskIn($dept, ['status' => 'in_progress']);
        $this->assignmentFor($employee, $task, [
            'status' => 'in_progress',
            'progress_percentage' => 40,
            'assigned_at' => now()->startOfMonth()->addDay(),
        ]);

        $this->login($this->adminUser());

        $this->getJson("/api/v1/admin/dashboard/employees/{$employee->id}/progress")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'employee_id', 'employee_name', 'department_name', 'summary', 'months', 'recent_assignments',
            ]])
            ->assertJsonCount(6, 'data.months')
            ->assertJsonCount(1, 'data.recent_assignments')
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.in_progress', 1);
    }
}