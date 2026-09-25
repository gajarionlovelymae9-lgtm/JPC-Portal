<?php

namespace Tests\Feature\Reporting;

use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 9 — ReportingService + ReportingController HTTP tests (spec items
 * 4, 6-13, 15).
 *
 * Covers the RBAC matrix: employee = own records only, department_head =
 * own department, admin/hr = institution-wide, and the self-service report
 * (reports.view) vs management reports (analytics.view) split.
 */
class ReportingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function deptHead(User $head, Department $department): Employee
    {
        return Employee::factory()->create([
            'user_id' => $head->id,
            'department_id' => $department->id,
        ]);
    }

    private function employeeUser(Department $department): array
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
        ]);

        return [$user, $employee];
    }

    public function test_employee_can_view_only_own_progress_report(): void
    {
        $department = Department::factory()->create();
        [$user, $employee] = $this->employeeUser($department);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/employee?employee_id='.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.report_type', 'employee_progress')
            ->assertJsonPath('data.employee.id', $employee->id);
    }

    public function test_employee_cannot_view_another_employees_progress_report(): void
    {
        $department = Department::factory()->create();
        [$user] = $this->employeeUser($department);
        [, $other] = $this->employeeUser($department);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/employee?employee_id='.$other->id)
            ->assertStatus(403);
    }

    public function test_employee_cannot_access_institution_report(): void
    {
        $department = Department::factory()->create();
        [$user] = $this->employeeUser($department);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/institution')
            ->assertStatus(403);
    }

    public function test_employee_cannot_access_monthly_report(): void
    {
        $department = Department::factory()->create();
        [$user] = $this->employeeUser($department);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/monthly')
            ->assertStatus(403);
    }

    public function test_department_head_can_view_own_department_report(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->create();
        $head->assignRole('department_head');
        $this->deptHead($head, $department);

        Sanctum::actingAs($head);

        $this->getJson('/api/v1/reports/department?department_id='.$department->id)
            ->assertOk()
            ->assertJsonPath('data.report_type', 'department_performance')
            ->assertJsonPath('data.department.id', $department->id);
    }

    public function test_department_head_cannot_view_other_department_report(): void
    {
        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $head = User::factory()->create();
        $head->assignRole('department_head');
        $this->deptHead($head, $own);

        Sanctum::actingAs($head);

        $this->getJson('/api/v1/reports/department?department_id='.$other->id)
            ->assertStatus(403);
    }

    public function test_department_head_cannot_view_institution_report(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->create();
        $head->assignRole('department_head');
        $this->deptHead($head, $department);

        Sanctum::actingAs($head);

        $this->getJson('/api/v1/reports/institution')
            ->assertStatus(403);
    }

    public function test_admin_can_view_institution_report(): void
    {
        $user = $this->adminUser();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/institution')
            ->assertOk()
            ->assertJsonPath('data.report_type', 'institution_performance');
    }

    public function test_admin_can_view_monthly_report(): void
    {
        $user = $this->adminUser();

Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/monthly?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonPath('data.report_type', 'monthly_performance')
            ->assertJsonPath('data.scope', 'institution');
    }

    public function test_late_report_is_scoped_to_department_head_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $head = User::factory()->create();
        $head->assignRole('department_head');
        $this->deptHead($head, $deptA);

        $empA = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        Report::factory()->create([
            'employee_id' => $empA->id,
            'status' => ReportStatus::Submitted,
            'is_late' => true,
            'submitted_at' => now(),
        ]);
        Report::factory()->create([
            'employee_id' => $empB->id,
            'status' => ReportStatus::Submitted,
            'is_late' => true,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($head);

        $response = $this->getJson('/api/v1/reports/late')
            ->assertOk()
            ->assertJsonPath('data.summary.late_reports', 1);

        $byEmployee = $response->json('data.by_employee');
        $this->assertCount(1, $byEmployee);
        $this->assertSame($empA->id, $byEmployee[0]['employee_id']);
    }

    public function test_pending_reports_are_scoped_to_department_head_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $head = User::factory()->create();
        $head->assignRole('department_head');
        $this->deptHead($head, $deptA);

        $empA = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        Report::factory()->create([
            'employee_id' => $empA->id,
            'status' => ReportStatus::Submitted,
            'submitted_at' => now(),
        ]);
        Report::factory()->create([
            'employee_id' => $empB->id,
            'status' => ReportStatus::UnderReview,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($head);

        $response = $this->getJson('/api/v1/reports/pending')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_reports', 1);

        $byEmployee = $response->json('data.by_employee');
        $this->assertCount(1, $byEmployee);
        $this->assertSame($empA->id, $byEmployee[0]['employee_id']);
    }

    public function test_department_head_target_report_filters_by_employee(): void
    {
        $deptA = Department::factory()->create();
        $head = User::factory()->create();
        $head->assignRole('department_head');
        Employee::factory()->create(['user_id' => $head->id, 'department_id' => $deptA->id]);
        $employee = Employee::factory()->create(['department_id' => $deptA->id]);

        \App\Models\EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'achieved',
        ]);

        Sanctum::actingAs($head);

        $this->getJson('/api/v1/reports/targets?employee_id='.$employee->id)
            ->assertOk()
            ->assertJsonPath('data.report_type', 'targets_summary')
            ->assertJsonPath('data.total_targets', 1);
    }

    public function test_employee_cannot_access_targets_report(): void
    {
        $department = Department::factory()->create();
        [$user] = $this->employeeUser($department);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/targets?employee_id='.$user->employee->id)
            ->assertStatus(403);
    }

    public function test_department_head_cannot_request_targets_of_other_department_employee(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $head = User::factory()->create();
        $head->assignRole('department_head');
        Employee::factory()->create(['user_id' => $head->id, 'department_id' => $deptA->id]);
        $other = Employee::factory()->create(['department_id' => $deptB->id]);

        Sanctum::actingAs($head);

        $this->getJson('/api/v1/reports/targets?employee_id='.$other->id)
            ->assertStatus(403);
    }

    public function test_achievement_report_accessible_to_admin_with_data(): void
    {
        $user = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        \App\Models\MonthlyAchievement::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'month' => (int) now()->month,
            'year' => (int) now()->year,
            'completion_rate' => 92.0,
            'on_time_rate' => 88.0,
            'overall_score' => 90.0,
            'completed_tasks' => 10,
            'approved_reports' => 8,
            'rank' => 1,
            'is_employee_of_month' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/achievements?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonPath('data.report_type', 'achievement_summary')
            ->assertJsonPath('data.total', 1);
    }
}