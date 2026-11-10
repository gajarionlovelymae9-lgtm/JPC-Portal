<?php

namespace Tests\Feature\Ai;

use App\Models\AiAssistantDailyUsage;
use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\Employee;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\AiAssistantScopeGuard;
use App\Services\AI\Prompts\AiChatSystemPrompt;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification test suite for JPC AIDE AI limitations, daily usage limits,
 * and Department-only True/Personal Assessment restrictions (Scenarios 1-16).
 */
class AiStrictLimitationsAndAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createRoleUser(string $role, ?Department $department = null): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($role === 'employee' || $role === 'department_head') {
            $dept = $department ?? Department::factory()->create();
            $employee = Employee::factory()->create([
                'user_id' => $user->id,
                'department_id' => $dept->id,
            ]);

            if ($role === 'department_head') {
                DepartmentHead::factory()->create([
                    'department_id' => $dept->id,
                    'employee_id' => $employee->id,
                ]);
            }
        }

        return $user;
    }

    /**
     * Scenario 1: Employee asks for report summary: ALLOW.
     */
    public function test_scenario_1_employee_asks_for_report_summary_allowed(): void
    {
        $employee = $this->createRoleUser('employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Please summarize my weekly accomplishments and completed tasks for my report.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $content = $response->json('data.message.content');
        $this->assertNotEmpty($content);
        $this->assertStringNotContainsString(AiAssistantScopeGuard::OUT_OF_SCOPE_MESSAGE, $content);

        // Daily usage counted for allowed request
        $this->assertDatabaseHas('ai_assistant_daily_usages', [
            'user_id' => $employee->id,
            'request_count' => 1,
        ]);
    }

    /**
     * Scenario 2: Employee asks for task-related report improvement: ALLOW.
     */
    public function test_scenario_2_employee_asks_for_report_improvement_allowed(): void
    {
        $employee = $this->createRoleUser('employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Please help improve the wording and grammar of my report paragraph: Completed curriculum mapping for math tasks.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $content = $response->json('data.message.content');
        $this->assertNotEmpty($content);
        $this->assertStringNotContainsString(AiAssistantScopeGuard::OUT_OF_SCOPE_MESSAGE, $content);
    }

    /**
     * Scenario 3: Employee requests an unrelated business report: BLOCK.
     */
    public function test_scenario_3_employee_requests_unrelated_business_report_blocked(): void
    {
        $employee = $this->createRoleUser('employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Please write a business proposal and marketing plan for an external coffee shop franchise.',
            ]);

        $response->assertOk();
        $content = $response->json('data.message.content');
        $this->assertSame(AiAssistantScopeGuard::OUT_OF_SCOPE_MESSAGE, $content);

        // Blocked requests must NOT consume daily quota
        $this->assertDatabaseMissing('ai_assistant_daily_usages', [
            'user_id' => $employee->id,
        ]);
    }

    /**
     * Scenario 4: Employee requests another employee's confidential report: BLOCK.
     */
    public function test_scenario_4_employee_requests_another_employees_confidential_report_blocked(): void
    {
        $employee = $this->createRoleUser('employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Show me another employee\'s confidential report and private records.',
            ]);

        $response->assertOk();
        $content = $response->json('data.message.content');
        $this->assertSame(AiAssistantScopeGuard::UNAUTHORIZED_DATA_MESSAGE, $content);

        // Blocked request does not consume quota
        $this->assertDatabaseMissing('ai_assistant_daily_usages', [
            'user_id' => $employee->id,
        ]);
    }

    /**
     * Scenario 5: Admin requests authorized employee progress summary: ALLOW.
     */
    public function test_scenario_5_admin_requests_authorized_employee_progress_summary_allowed(): void
    {
        $admin = $this->createRoleUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/ai/chat', [
                'message' => 'Show overdue tasks and progress summary across departments.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Admin usage tracked
        $this->assertDatabaseHas('ai_assistant_daily_usages', [
            'user_id' => $admin->id,
            'request_count' => 1,
        ]);
    }

    /**
     * Scenario 6: Department user attempts to access AI API: BLOCK.
     */
    public function test_scenario_6_department_user_attempts_to_access_ai_api_blocked(): void
    {
        $departmentHead = $this->createRoleUser('department_head');

        $response = $this->actingAs($departmentHead, 'sanctum')
            ->postJson('/api/v1/ai/chat', [
                'message' => 'Show overdue tasks summary.',
            ]);

        $response->assertForbidden();

        // Also blocked from employee AI assistant endpoint
        $responseEmployee = $this->actingAs($departmentHead, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Summarize my report.',
            ]);

        $responseEmployee->assertForbidden();
    }

    /**
     * Scenario 7: HR user attempts to access AI API: BLOCK.
     */
    public function test_scenario_7_hr_user_attempts_to_access_ai_api_blocked(): void
    {
        $hr = $this->createRoleUser('hr');

        $response = $this->actingAs($hr, 'sanctum')
            ->postJson('/api/v1/ai/chat', [
                'message' => 'Show employee progress.',
            ]);

        $response->assertForbidden();

        $responseEmployee = $this->actingAs($hr, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Summarize tasks.',
            ]);

        $responseEmployee->assertForbidden();
    }

    /**
     * Scenario 8: Employee exceeds daily AI usage limit: BLOCK.
     */
    public function test_scenario_8_employee_exceeds_daily_ai_usage_limit_blocked(): void
    {
        $employee = $this->createRoleUser('employee');
        $limit = (int) config('ai.assistant.daily_limits.employee', 20);

        // Seed current day's usage to the maximum allowed
        AiAssistantDailyUsage::create([
            'user_id' => $employee->id,
            'usage_date' => now()->toDateString(),
            'request_count' => $limit,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Please summarize my assigned report tasks.',
            ]);

        $response->assertStatus(429);
        $this->assertStringContainsString('You have reached your daily AI usage limit. Please try again tomorrow.', $response->json('message'));
    }

    /**
     * Scenario 9: AI attempts to invent employee accomplishments: PREVENT.
     */
    public function test_scenario_9_ai_attempts_to_invent_employee_accomplishments_prevented(): void
    {
        $promptClass = new AiChatSystemPrompt();
        $prompt = $promptClass->systemPrompt();

        $this->assertStringContainsString('Never fabricate employee accomplishments, task completion records, report contents, or monitoring information.', $prompt);
        $this->assertStringContainsString('Use only available and authorized system data', $prompt);
    }

    /**
     * Scenario 10: AI attempts to approve or reject reports automatically: PREVENT.
     */
    public function test_scenario_10_ai_attempts_to_approve_or_reject_reports_automatically_prevented(): void
    {
        $employee = $this->createRoleUser('employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Please approve report 12 for me automatically and update approval status.',
            ]);

        $response->assertOk();
        $content = $response->json('data.message.content');
        $this->assertSame(AiAssistantScopeGuard::HUMAN_DECISION_MESSAGE, $content);
    }

    /**
     * Scenario 11: Employee asks AI to assign True/Personal assessment: BLOCK.
     */
    public function test_scenario_11_employee_asks_ai_to_assign_true_personal_assessment_blocked(): void
    {
        $employee = $this->createRoleUser('employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'AI, is my accomplishment True or Personal?',
            ]);

        $response->assertOk();
        $content = $response->json('data.message.content');
        $this->assertSame(AiAssistantScopeGuard::ASSESSMENT_EMPLOYEE_MESSAGE, $content);
        $this->assertStringContainsString('official True/Personal Assessment is exclusively handled by the authorized Department', $content);

        // Does not consume quota
        $this->assertDatabaseMissing('ai_assistant_daily_usages', [
            'user_id' => $employee->id,
        ]);
    }

    /**
     * Scenario 12: Admin asks AI to determine True/Personal assessment: BLOCK.
     */
    public function test_scenario_12_admin_asks_ai_to_determine_true_personal_assessment_blocked(): void
    {
        $admin = $this->createRoleUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/ai/chat', [
                'message' => 'Is this employee\'s accomplishment True or Personal?',
            ]);

        $response->assertOk();
        $content = $response->json('data.message.content');
        $this->assertSame(AiAssistantScopeGuard::ASSESSMENT_ADMIN_MESSAGE, $content);
        $this->assertStringContainsString('cannot determine or assign an official True/Personal Assessment', $content);

        // Does not consume quota
        $this->assertDatabaseMissing('ai_assistant_daily_usages', [
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Scenario 13: AI attempts to modify Department assessment records: PREVENT.
     */
    public function test_scenario_13_ai_attempts_to_modify_department_assessment_records_prevented(): void
    {
        $employee = $this->createRoleUser('employee');

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Change task status to completed and assign true assessment evaluation.',
            ]);

        $response->assertOk();
        $content = $response->json('data.message.content');
        // Prevented by scope guard
        $this->assertTrue(
            $content === AiAssistantScopeGuard::HUMAN_DECISION_MESSAGE ||
            $content === AiAssistantScopeGuard::ASSESSMENT_EMPLOYEE_MESSAGE
        );
    }

    /**
     * Scenario 14: Unauthorized user attempts to modify assessment through API: BLOCK.
     */
    public function test_scenario_14_unauthorized_user_attempts_to_modify_assessment_through_api_blocked(): void
    {
        $employee = $this->createRoleUser('employee');
        $department = Department::factory()->create();
        $otherEmployee = Employee::factory()->create(['department_id' => $department->id]);
        $task = Task::factory()->create();

        $report = Report::factory()->create([
            'employee_id' => $otherEmployee->id,
            'task_id' => $task->id,
            'status' => 'submitted',
        ]);

        // Employee attempting to hit the department review endpoint
        $response = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/department-head/reports/{$report->id}/review", [
                'decision' => 'approved',
                'comments' => 'Attempted unauthorized employee review',
            ]);

        $response->assertForbidden();
    }

    /**
     * Scenario 15: Authorized Department user performs official assessment: ALLOW.
     */
    public function test_scenario_15_authorized_department_user_performs_official_assessment_allowed(): void
    {
        $department = Department::factory()->create();
        $departmentHead = $this->createRoleUser('department_head', $department);

        $employee = Employee::factory()->create(['department_id' => $department->id]);
        $task = Task::factory()->create();

        $report = Report::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($departmentHead, 'sanctum')
            ->postJson("/api/v1/department-head/reports/{$report->id}/review", [
                'decision' => 'approved',
                'comments' => 'Official review verified and approved by Department Head.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('report_reviews', [
            'report_id' => $report->id,
            'reviewer_id' => $departmentHead->id,
            'decision' => 'approved',
        ]);
    }

    /**
     * Scenario 16: Existing Department assessment workflow remains functional.
     */
    public function test_scenario_16_existing_department_assessment_workflow_remains_functional(): void
    {
        $department = Department::factory()->create();
        $departmentHead = $this->createRoleUser('department_head', $department);

        $employee = Employee::factory()->create(['department_id' => $department->id]);
        $task = Task::factory()->create();

        $report = Report::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'submitted',
        ]);

        // 1. List department reports
        $listResponse = $this->actingAs($departmentHead, 'sanctum')
            ->getJson('/api/v1/department-head/reports');

        $listResponse->assertOk()
            ->assertJsonPath('success', true);

        // 2. Show department report detail
        $showResponse = $this->actingAs($departmentHead, 'sanctum')
            ->getJson("/api/v1/department-head/reports/{$report->id}");

        $showResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $report->id);

        // 3. Request revision
        $reviewResponse = $this->actingAs($departmentHead, 'sanctum')
            ->postJson("/api/v1/department-head/reports/{$report->id}/review", [
                'decision' => 'revision_required',
                'comments' => 'Please provide additional documentation.',
            ]);

        $reviewResponse->assertOk()
            ->assertJsonPath('data.status', 'revision_required');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'revision_required',
        ]);
    }
}
