<?php

namespace Tests\Feature\Api\V1\Employee;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Employee workspace: GET /api/v1/employee/dashboard.
 *
 * The dashboard is the primary entry point for the employee workspace, so it
 * owns the rules that matter most:
 *  - only the authenticated employee's own records are ever returned;
 *  - rates are null when there is nothing to measure (the UI renders "no data"
 *    rather than a fabricated 0%);
 *  - auth is enforced and profiles without a linked employee record are
 *    rejected without leaking anything.
 */
class EmployeePortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
        Cache::flush();

        // Every relative date below ("assigned yesterday", "completed 12 days
        // ago") has to land inside the month the dashboard reports on. On the
        // first day of a month "yesterday" falls in the previous month, so the
        // monthly rollup would legitimately read zero and the assertions would
        // depend on the day the suite happens to run.
        $this->travelTo(Carbon::now()->startOfMonth()->addDays(14)->setTime(10, 0));
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    private function employeeUser(): array
    {
        $employee = Employee::factory()->create(['user_id' => null]);
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        $employee->update(['user_id' => $user->id]);

        return [$user, $employee->fresh()];
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    /**
     * Minimal, deterministic dataset for employee 1:
     * thorough assignment + reports, exactly as a real employee would have.
     */
    private function seedWorkload(Employee $employee, Department $department): void
    {
        $taskCompleted = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'completed',
            'due_date' => now()->subDays(10),
            'completed_at' => now()->subDays(12),
        ]);
        $taskInProgress = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'in_progress',
            'due_date' => now()->addDays(3),
            'completed_at' => null,
        ]);
        $taskOverdue = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'assigned',
            'due_date' => now()->subDays(2),
            'completed_at' => null,
        ]);

        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $taskCompleted->id,
            'status' => 'completed',
            'assigned_at' => now()->subDay(),
            'due_date' => now()->subDays(10),
            'completed_at' => now()->subDays(12),
        ]);
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $taskInProgress->id,
            'status' => 'in_progress',
            'assigned_at' => now()->subDay(),
            'due_date' => now()->addDays(3),
            'completed_at' => null,
        ]);
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $taskOverdue->id,
            'status' => 'assigned',
            'assigned_at' => now()->subDay(),
            'due_date' => Carbon::now()->subDay()->endOfDay(),
            'completed_at' => null,
        ]);

        Report::factory()->approved()->create([
            'employee_id' => $employee->id,
            'task_id' => $taskCompleted->id,
            'deadline' => Carbon::now()->subDays(8)->toDateString(),
            'submitted_at' => now()->subDays(10),
            'approved_at' => now()->subDays(9),
        ]);
        Report::factory()->draft()->create([
            'employee_id' => $employee->id,
            'task_id' => $taskInProgress->id,
            'deadline' => now()->addDays(3)->toDateString(),
            'submitted_at' => null,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');

        $this->getJson('/api/v1/employee/dashboard')->assertUnauthorized();
    }

    public function test_account_without_employee_profile_is_forbidden(): void
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
        $this->login($user);

        $this->getJson('/api/v1/employee/dashboard')->assertForbidden();
    }

    public function test_dashboard_returns_only_the_authenticated_employees_data(): void
    {
        [$user, $employee] = $this->employeeUser();
        $department = Department::factory()->create();

        $otherEmployee = Employee::factory()->create(['user_id' => null]);
        $otherTask = Task::factory()->create([
            'department_id' => $department->id,
            'title' => 'OTHER EMPLOYEES TASK',
            'due_date' => now()->addDays(5),
        ]);
        TaskAssignment::factory()->create([
            'employee_id' => $otherEmployee->id,
            'task_id' => $otherTask->id,
        ]);

        $this->seedWorkload($employee, $department);
        $this->login($user);

        $response = $this->getJson('/api/v1/employee/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.id', $employee->id);

        $tasks = $response->json('data.tasks');
        $titles = array_column($tasks, 'title');

        $this->assertNotContains('OTHER EMPLOYEES TASK', $titles);
        $response->assertJsonPath('data.kpis.total_tasks', 3);
    }

    public function test_kpis_reflect_the_seeded_work(): void
    {
        [$user, $employee] = $this->employeeUser();
        $this->seedWorkload($employee, Department::factory()->create());
        $this->login($user);

        $this->getJson('/api/v1/employee/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.total_tasks', 3)
            ->assertJsonPath('data.kpis.completed_tasks', 1)
            ->assertJsonPath('data.kpis.in_progress_tasks', 1)
            ->assertJsonPath('data.kpis.overdue_tasks', 1)
            ->assertJsonPath('data.kpis.total_reports', 2)
            ->assertJsonPath('data.kpis.approved_reports', 1)
            ->assertJsonPath('data.caught_up', false)
            ->assertJsonPath('data.monthly_progress.tasks_assigned', 3)
            ->assertJsonPath('data.monthly_progress.tasks_completed', 1);
    }

    public function test_empty_employee_gets_null_rates_not_zero(): void
    {
        [$user, $employee] = $this->employeeUser();
        $this->login($user);

        $response = $this->getJson('/api/v1/employee/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.kpis.total_tasks', 0)
            ->assertJsonPath('data.kpis.completion_rate', null)
            ->assertJsonPath('data.kpis.on_time_rate', null)
            ->assertJsonPath('data.progress.percent', null)
            ->assertJsonPath('data.kpis.total_reports', 0)
            ->assertJsonCount(0, 'data.tasks')
            ->assertJsonCount(0, 'data.reports')
            ->assertJsonCount(0, 'data.deadlines')
            ->assertJsonPath('data.caught_up', true);

        // Rates must never be faked as 0% when there is nothing to measure.
        $this->assertNull($response->json('data.kpis.completion_rate'));
        $this->assertNull($response->json('data.kpis.on_time_rate'));
    }

    public function test_calendar_accepts_year_and_month_queries(): void
    {
        [$user, $employee] = $this->employeeUser();
        $this->seedWorkload($employee, Department::factory()->create());
        $this->login($user);

        $response = $this->getJson('/api/v1/employee/calendar?year=2026&month=9')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertArrayHasKey('events', $response->json('data') ?? []);
    }

    public function test_future_month_is_accepted_and_endpoint_scopes_to_it(): void
    {
        [$user] = $this->employeeUser();
        $this->login($user);

        $this->getJson('/api/v1/employee/dashboard?year='.(now()->year + 1).'&month=3')
            ->assertOk()
            ->assertJsonPath('data.monthly_progress.year', now()->year + 1)
            ->assertJsonPath('data.monthly_progress.month', 3);
    }
}
