<?php

namespace Tests\Feature\Reporting;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 9 — ManagementSummaryService + endpoint tests (spec item 20).
 *
 * The narrative must be deterministic and grounded in the institution
 * report: no data → "Not enough data" summary; data → real narrative +
 * key takeaways; access restricted to admin/hr.
 */
class ManagementSummaryTest extends TestCase
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

    public function test_admin_summary_with_data_reports_real_narrative(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'period_type' => 'monthly',
            'period_start' => Carbon::now()->startOfMonth()->toDateString(),
            'period_end' => Carbon::now()->endOfMonth()->toDateString(),
            'completion_rate' => 90.0,
            'on_time_rate' => 85.0,
            'approval_rate' => 92.0,
            'target_achievement_rate' => 80.0,
            'productivity_score' => 88.0,
            'overall_score' => 88.0,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/management-summary?year='.now()->year.'&month='.now()->month)
            ->assertOk();

        $data = $response->json('data');
        $this->assertTrue($data['has_data']);
        $this->assertStringContainsString('Executive Management Summary', $data['narrative']);
        $this->assertStringContainsString('average productivity score of 88.0%', $data['narrative']);
        $this->assertNotEmpty($data['key_takeaways']);
        $this->assertSame(88.0, (float) $data['metrics']['average_productivity']);
    }

    public function test_admin_summary_without_data_reports_not_enough_data(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/management-summary?year='.now()->year.'&month='.now()->month)
            ->assertOk();

        $data = $response->json('data');
        $this->assertFalse($data['has_data']);
        $this->assertStringContainsString('Not enough data', $data['narrative']);
    }

    public function test_department_head_cannot_view_institution_management_summary(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->create();
        $head->assignRole('department_head');
        Employee::factory()->create(['user_id' => $head->id, 'department_id' => $department->id]);

        Sanctum::actingAs($head);

        $this->getJson('/api/v1/reports/management-summary')
            ->assertStatus(403);
    }

    public function test_employee_cannot_view_management_summary(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('employee');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/management-summary')
            ->assertStatus(403);
    }

    public function test_summary_includes_month_over_month_change_when_previous_month_has_data(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'period_type' => 'monthly',
            'period_start' => Carbon::now()->startOfMonth()->toDateString(),
            'period_end' => Carbon::now()->endOfMonth()->toDateString(),
            'completion_rate' => 90.0,
            'on_time_rate' => 85.0,
            'approval_rate' => 92.0,
            'target_achievement_rate' => 80.0,
            'productivity_score' => 88.0,
            'overall_score' => 88.0,
        ]);

        $previous = Carbon::now()->subMonthNoOverflow();
        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'period_type' => 'monthly',
            'period_start' => $previous->startOfMonth()->toDateString(),
            'period_end' => $previous->endOfMonth()->toDateString(),
            'completion_rate' => 85.0,
            'on_time_rate' => 80.0,
            'approval_rate' => 90.0,
            'target_achievement_rate' => 75.0,
            'productivity_score' => 82.0,
            'overall_score' => 82.0,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/management-summary?year='.now()->year.'&month='.now()->month)
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotNull($data['change']);
        $this->assertSame(6.0, (float) $data['change']['average_productivity_change']);
    }
}