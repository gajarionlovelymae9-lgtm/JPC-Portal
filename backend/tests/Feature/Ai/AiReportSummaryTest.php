<?php

namespace Tests\Feature\Ai;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 AI report summarize authorization tests.
 *
 * The summarize endpoint gates through AiReportPolicy and the
 * GenerateReportSummaryRequest requires the report_id field in the body.
 */
class AiReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role, ?Department $department = null): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($department) {
            Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
        }

        return $user;
    }

    private function summarize(int $reportId, User $user)
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/ai/reports/{$reportId}/summarize", ['report_id' => $reportId]);
    }

    public function test_unauthenticated_summarize_is_rejected(): void
    {
        $this->postJson('/api/v1/ai/reports/1/summarize', ['report_id' => 1])->assertUnauthorized();
    }

    public function test_report_id_is_required(): void
    {
        $admin = $this->user('admin');
        $report = Report::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/ai/reports/{$report->id}/summarize", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('report_id');
    }

    public function test_admin_can_summarize_any_report(): void
    {
        $admin = $this->user('admin');
        $report = Report::factory()->create();

        $this->summarize($report->id, $admin)
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_hr_cannot_use_ai_report_summaries(): void
    {
        $hr = $this->user('hr');
        $report = Report::factory()->create();

        $this->summarize($report->id, $hr)->assertForbidden();
    }

    public function test_department_head_cannot_use_ai_report_summaries(): void
    {
        $dept = Department::factory()->create();
        $head = $this->user('department_head', $dept);

        $employee = Employee::factory()->create(['department_id' => $dept->id]);
        $report = Report::factory()->create(['employee_id' => $employee->id]);

        $this->summarize($report->id, $head)->assertForbidden();
    }

    public function test_employee_can_summarize_own_report(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->user('employee', $dept);
        $report = Report::factory()->create(['employee_id' => $employee->employee->id]);

        $this->summarize($report->id, $employee)->assertOk();
    }

    public function test_employee_cannot_summarize_another_employees_report(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->user('employee', $dept);
        $other = Employee::factory()->create(['department_id' => $dept->id]);
        $report = Report::factory()->create(['employee_id' => $other->id]);

        $this->summarize($report->id, $employee)->assertForbidden();
    }

    public function test_employee_cannot_summarize_without_employee_relationship(): void
    {
        // An employee user without an Employee row must be denied because
        // ownership is derived from the Employee relationship.
        $user = User::factory()->create();
        $user->assignRole('employee');

        $other = Employee::factory()->create();
        $report = Report::factory()->create(['employee_id' => $other->id]);

        $this->summarize($report->id, $user)->assertForbidden();
    }
}
