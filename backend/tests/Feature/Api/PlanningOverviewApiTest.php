<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\DepartmentPlan;
use App\Models\EmployeeTarget;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the read-only planning overview (GET /api/v1/planning):
 *  1. guests are rejected and employees are forbidden;
 *  2. Admin and HR (the roles granted planning.view) receive the payload;
 *  3. the payload exposes the academic-year → annual-plan → department-plan
 *     → employee-target chain with summary counts;
 *  4. the endpoint stays read-only (no mutating verb is registered).
 */
class PlanningOverviewApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
    }

    public function test_guest_cannot_view_the_planning_overview(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/planning')->assertStatus(401);
    }

    public function test_employee_role_is_forbidden_from_the_planning_overview(): void
    {
        $employee = $this->roleUser(Role::Employee);
        $this->loginAs($employee);

        $this->getJson('/api/v1/planning')->assertStatus(403);
    }

    public function test_hr_can_view_the_planning_overview(): void
    {
        $hr = $this->roleUser(Role::Hr);
        $this->loginAs($hr);

        $this->getJson('/api/v1/planning')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'academic_years',
                        'annual_plans',
                        'annual_plans_by_status',
                        'department_plans',
                        'employee_targets',
                        'employee_targets_by_status',
                    ],
                    'academic_years',
                    'annual_plans',
                    'department_plans',
                    'employee_targets',
                ],
            ]);
    }

    public function test_admin_overview_exposes_the_full_planning_chain(): void
    {
        $academicYear = AcademicYear::factory()->create(['name' => '2026-2027']);
        $annualPlan = AnnualPlan::factory()->create([
            'academic_year_id' => $academicYear->id,
            'title' => 'Faculty Performance and Academic Reporting Plan',
            'status' => 'approved',
        ]);
        $departmentPlan = DepartmentPlan::factory()->create([
            'annual_plan_id' => $annualPlan->id,
            'title' => 'Departmental Reporting and Compliance Plan',
        ]);
        EmployeeTarget::factory()->create([
            'annual_plan_id' => $annualPlan->id,
            'title' => 'Submit quarterly progress reports',
        ]);

        $admin = $this->adminUser();
        $this->loginAs($admin);

        $data = $this->getJson('/api/v1/planning')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.academic_years', 1)
            ->assertJsonPath('data.summary.annual_plans', 1)
            ->assertJsonPath('data.summary.department_plans', 1)
            ->assertJsonPath('data.summary.employee_targets', 1)
            ->json('data');

        $this->assertSame('2026-2027', $data['academic_years'][0]['name']);

        $plan = collect($data['annual_plans'])->firstWhere('id', $annualPlan->id);
        $this->assertNotNull($plan);
        $this->assertSame('Faculty Performance and Academic Reporting Plan', $plan['title']);
        $this->assertSame('approved', $plan['status']);
        // Eloquent serializes relation keys in snake_case.
        $this->assertSame('2026-2027', $plan['academic_year']['name']);
        $this->assertSame(1, $plan['department_plans_count']);
        $this->assertSame(1, $plan['employee_targets_count']);

        $departmentPlanRow = collect($data['department_plans'])->firstWhere('id', $departmentPlan->id);
        $this->assertNotNull($departmentPlanRow);
        $this->assertSame('Departmental Reporting and Compliance Plan', $departmentPlanRow['title']);

        $target = $data['employee_targets'][0];
        $this->assertSame('Submit quarterly progress reports', $target['title']);
        $this->assertNotEmpty($target['employee_name']);
    }

    public function test_planning_overview_exposes_no_mutating_verbs(): void
    {
        $admin = $this->adminUser();
        $this->loginAs($admin);

        $this->postJson('/api/v1/planning', [])->assertStatus(405);
        $this->putJson('/api/v1/planning', [])->assertStatus(405);
        $this->deleteJson('/api/v1/planning')->assertStatus(405);
    }
}
