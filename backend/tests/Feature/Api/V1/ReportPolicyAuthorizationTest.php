<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for ReportPolicy ownership/authorization on
 * policy-guarded AI processing endpoints.
 */
class ReportPolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = RoleModel::query()->where('name', $role)->firstOrFail();

        return User::factory()->create(['role_id' => $roleModel->id]);
    }

    public function test_employee_owner_can_access_own_report_summary(): void
    {
        $user = $this->userWithRole(Role::Employee->value);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $report = Report::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/ai-processing/{$report->id}/ai-summary")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_employee_cannot_access_another_employees_report(): void
    {
        $user = $this->userWithRole(Role::Employee->value);
        Employee::factory()->create(['user_id' => $user->id]);

        $otherEmployee = Employee::factory()->create();
        $report = Report::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/ai-processing/{$report->id}/ai-summary")
            ->assertForbidden();
    }

    public function test_employee_cannot_process_another_employees_report(): void
    {
        $user = $this->userWithRole(Role::Employee->value);
        Employee::factory()->create(['user_id' => $user->id]);

        $otherEmployee = Employee::factory()->create();
        $report = Report::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/ai-processing/{$report->id}/process")
            ->assertForbidden();
    }

    public function test_admin_can_access_non_owned_report(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $report = Report::factory()->create();

        $this->actingAs($admin)
            ->getJson("/api/v1/ai-processing/{$report->id}/ai-summary")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_department_head_reviewer_can_access_non_owned_report(): void
    {
        $head = $this->userWithRole(Role::DepartmentHead->value);
        $report = Report::factory()->create();

        $this->actingAs($head)
            ->getJson("/api/v1/ai-processing/{$report->id}/ai-summary")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_hr_manager_can_access_non_owned_report(): void
    {
        $hr = $this->userWithRole(Role::Hr->value);
        $report = Report::factory()->create();

        $this->actingAs($hr)
            ->getJson("/api/v1/ai-processing/{$report->id}/ai-summary")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
