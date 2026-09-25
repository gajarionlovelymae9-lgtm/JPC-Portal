<?php

namespace Tests\Feature\Reporting;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\AI\AiChatService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 — Grounded AI chat security (spec item 58).
 *
 * The AiChatService must never execute a reporting tool the caller lacks
 * permission for, and every "report" answer must come from the real
 * Reporting/Analytics services (deterministic intent + permission gate).
 */
class AiToolsSecurityTest extends TestCase
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

    private function employeeUser(): User
    {
        $department = Department::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('employee');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }

    public function test_employee_is_denied_analytics_tool_execution(): void
    {
        $user = $this->employeeUser();
        $service = app(AiChatService::class);

        $result = $service->handleMessage($user, null, 'Show the top performers leaderboard');

        $this->assertSame(\App\Enums\AiIntent::TOP_PERFORMERS->value, $result['intent']);
        $this->assertSame('permission_denied', $result['results']['error']);
        $this->assertSame('GetRankingTool', $result['results']['matched_tool']);
        $this->assertStringContainsString(
            'do not have permission',
            $result['message']->content,
        );
    }

    public function test_employee_is_denied_management_summary_tool(): void
    {
        $user = $this->employeeUser();
        $service = app(AiChatService::class);

        $result = $service->handleMessage($user, null, 'Give me a management summary');

        $this->assertSame(\App\Enums\AiIntent::MANAGEMENT_SUMMARY->value, $result['intent']);
        $this->assertSame('permission_denied', $result['results']['error']);
    }

    public function test_admin_can_execute_ranking_tool_against_real_service(): void
    {
        $admin = $this->adminUser();
        $service = app(AiChatService::class);

        $result = $service->handleMessage($admin, null, 'Top 3 performers ranking');

        $this->assertSame(\App\Enums\AiIntent::TOP_PERFORMERS->value, $result['intent']);
        $this->assertArrayNotHasKey('error', $result['results']);
        $this->assertSame('GetRankingTool', $result['results']['matched_tool']);
        $this->assertArrayHasKey('tool_result', $result['results']);
        $this->assertArrayHasKey('ranking', $result['results']['tool_result']);
        $this->assertIsArray($result['results']['tool_result']['ranking']);
    }

    public function test_unknown_message_returns_help_actions(): void
    {
        $user = $this->employeeUser();
        $service = app(AiChatService::class);

        $result = $service->handleMessage($user, null, 'What is the weather today?');

        $this->assertSame('DEFAULT', $result['intent']);
        $this->assertNull($result['results']['matched_tool']);
        $this->assertStringContainsString('I can answer performance questions', $result['message']->content);
    }

    public function test_department_head_ranking_tool_is_scoped_to_own_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $empA = Employee::factory()->create(['department_id' => $deptA->id]);
        Employee::factory()->create(['department_id' => $deptB->id]);

        $this->createPerformanceRecord($empA, $deptA->id, 90.0);

        $head = User::factory()->create();
        $head->assignRole('department_head');
        Employee::factory()->create(['user_id' => $head->id, 'department_id' => $deptA->id]);

        $service = app(AiChatService::class);
        $result = $service->handleMessage($head, null, 'Show me the current top performers ranking');

        $this->assertSame(\App\Enums\AiIntent::TOP_PERFORMERS->value, $result['intent']);
        $this->assertArrayNotHasKey('error', $result['results']);
        $this->assertCount(1, $result['results']['tool_result']['ranking']);
        $this->assertSame($empA->id, $result['results']['tool_result']['ranking'][0]['employee_id']);
    }

    private function createPerformanceRecord(Employee $employee, int $departmentId, float $score): void
    {
        \App\Models\EmployeePerformanceRecord::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'completion_rate' => $score,
            'on_time_rate' => $score,
            'approval_rate' => $score,
            'target_achievement_rate' => $score,
            'productivity_score' => $score,
            'overall_score' => $score,
        ]);
    }
}