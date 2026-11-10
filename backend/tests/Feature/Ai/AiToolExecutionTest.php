<?php

namespace Tests\Feature\Ai;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 — AiChatService tool execution through the chat endpoint:
 * intent resolution, permission gating, and grounded response generation.
 */
class AiToolExecutionTest extends TestCase
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

    private function chat(string $message, User $user)
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', ['message' => $message]);
    }

    public function test_unmatched_message_returns_default_intent_with_help(): void
    {
        $user = $this->user('employee');

        $response = $this->chat('What is the meaning of life?', $user)->assertOk();

        $this->assertSame('DEFAULT', $response->json('data.intent'));
        $this->assertNull($response->json('data.results.matched_tool'));
        $this->assertStringContainsString(
            'I can answer performance questions',
            $response->json('data.message.content'),
        );
    }

    public function test_employee_is_permission_denied_for_analytics_tool(): void
    {
        $dept = Department::factory()->create();
        $user = $this->user('employee', $dept);

        $response = $this->chat('Show me the top performers leaderboard', $user)->assertOk();

        $this->assertSame('TOP_PERFORMERS', $response->json('data.intent'));
        $this->assertSame('GetRankingTool', $response->json('data.results.matched_tool'));
        $this->assertSame('permission_denied', $response->json('data.results.error'));
    }

    public function test_admin_runs_analytics_tool_and_gets_grounded_answer(): void
    {
        $admin = $this->user('admin');

        $response = $this->chat('Show the top performers leaderboard', $admin)->assertOk();

        $this->assertSame('TOP_PERFORMERS', $response->json('data.intent'));
        $this->assertSame('GetRankingTool', $response->json('data.results.matched_tool'));
        $this->assertArrayNotHasKey('error', $response->json('data.results'));
        $this->assertArrayHasKey('tool_result', $response->json('data.results'));
        $this->assertStringContainsString('Get Ranking Tool', $response->json('data.message.content'));
    }

    public function test_employee_runs_own_progress_tool_with_reports_permission(): void
    {
        $dept = Department::factory()->create();
        $user = $this->user('employee', $dept);

        $response = $this->chat('Show my progress this month', $user)->assertOk();

        $this->assertSame('EMPLOYEE_PERFORMANCE', $response->json('data.intent'));
        $this->assertSame('GetEmployeeProgressReportTool', $response->json('data.results.matched_tool'));
        $this->assertArrayNotHasKey('error', $response->json('data.results'));
    }

    public function test_department_head_is_denied_ai_chat_access(): void
    {
        $dept = Department::factory()->create();
        $head = $this->user('department_head', $dept);

        $this->chat('Department performance report for this month', $head)->assertForbidden();
        $this->chat('Give me the management summary', $head)->assertForbidden();
    }

    public function test_two_turns_persist_to_the_same_thread(): void
    {
        $admin = $this->user('admin');

        $first = $this->chat('What is 2 plus 2?', $admin)->assertOk();
        $conversationId = $first->json('data.message.conversation_id');

        $this->postJson('/api/v1/ai/chat', [
            'message' => 'Give me the management summary',
            'conversation_id' => $conversationId,
        ])->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/ai/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('data.message_count', 4);
    }
}
