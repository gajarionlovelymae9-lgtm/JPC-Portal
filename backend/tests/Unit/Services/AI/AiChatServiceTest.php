<?php

namespace Tests\Unit\Services\AI;

use App\Enums\AiMessageRole;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Role;
use App\Models\User;
use App\Services\AI\AiChatService;
use App\Services\AI\AiIntentDetector;
use App\Services\AI\AiToolRegistry;
use App\Services\AI\Tools\GetEmployeeProgressReportTool;
use App\Services\AI\Tools\GetLateReportAnalyticsTool;
use App\Services\Reporting\ReportingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Behavior tests for AiChatService — the deterministic, permission-checked
 * intent → tool → report pipeline (Phase 7 / Phase 9).
 */
class AiChatServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeService(): AiChatService
    {
        $registry = new AiToolRegistry;

        // Wire the two realistic tools with a deterministic ReportingService.
        $reporting = $this->mock(ReportingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateEmployeeReport')
                ->andReturn([
                    'period' => now()->format('2026-09'),
                    'employee' => ['id' => 1, 'name' => 'Jane'],
                    'metrics' => ['completion_rate' => 80.0],
                    'source' => 'employee-progress',
                    'has_data' => true,
                ]);
            $mock->shouldReceive('generateLateReport')
                ->andReturn([
                    'period' => now()->format('2026-09'),
                    'summary' => ['total_late' => 2],
                    'comparison' => ['diff' => 0],
                    'by_employee' => [],
                    'by_department' => [],
                    'has_data' => true,
                ]);
        });

        $registry->register(new GetEmployeeProgressReportTool($reporting));
        $registry->register(new GetLateReportAnalyticsTool($reporting));

        $detector = new AiIntentDetector;

        return new AiChatService($registry, $detector);
    }

    private function roleUser(string $role): User
    {
        $roleModel = Role::query()->where('name', $role)->firstOrFail();

        return User::factory()->create(['role_id' => $roleModel->id]);
    }

    public function test_successful_tool_execution_persists_user_and_assistant_messages(): void
    {
        $employee = $this->roleUser('employee');
        $service = $this->makeService();

        $result = $service->handleMessage($employee, null, 'Show my progress');

        $this->assertSame('EMPLOYEE_PERFORMANCE', $result['intent']);
        $this->assertArrayHasKey('matched_tool', $result['results']);
        $this->assertSame('GetEmployeeProgressReportTool', $result['results']['matched_tool']);
        $this->assertInstanceOf(AiChatMessage::class, $result['message']);

        $conversation = AiChatConversation::where('user_id', $employee->id)->first();
        $this->assertNotNull($conversation);

        $messages = $conversation->messages()->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role->value);
        $this->assertSame('assistant', $messages[1]->role->value);
        $this->assertSame('EMPLOYEE_PERFORMANCE', $messages[1]->intent);
    }

    public function test_permission_denied_tool_returns_explanatory_message_without_executing(): void
    {
        // Employees do not hold analytics.view; late-report tool must not run.
        $employee = $this->roleUser('employee');
        $service = $this->makeService();

        $result = $service->handleMessage($employee, null, 'How many late reports?');

        $this->assertSame('LATE_REPORTS', $result['intent']);
        $this->assertSame('permission_denied', $result['results']['error']);
        $this->assertStringContainsString('do not have permission', $result['message']->content);

        $this->assertDatabaseCount('ai_chat_messages', 2);
    }

    public function test_admin_can_execute_late_report_tool(): void
    {
        $admin = $this->roleUser('admin');
        $service = $this->makeService();

        $result = $service->handleMessage($admin, null, 'How many late reports are there?');

        $this->assertSame('LATE_REPORTS', $result['intent']);
        $this->assertSame('GetLateReportAnalyticsTool', $result['results']['matched_tool']);
        $this->assertSame(['total_late' => 2], $result['results']['tool_result']['summary']);
    }

    public function test_unmatched_intent_returns_help_message(): void
    {
        $user = $this->roleUser('employee');
        $service = $this->makeService();

        $result = $service->handleMessage($user, null, 'What is 2 + 2?');

        $this->assertSame('DEFAULT', $result['intent']);
        $this->assertNull($result['results']['matched_tool']);
        $this->assertStringContainsString('I can answer performance questions', $result['message']->content);
    }

    public function test_existing_conversation_reuses_same_thread(): void
    {
        $employee = $this->roleUser('employee');
        $service = $this->makeService();

        $first = $service->handleMessage($employee, null, 'Show my progress');
        $conversationId = $first['message']->conversation_id;

        $second = $service->handleMessage($employee, $conversationId, 'Show my progress again');

        $this->assertSame($conversationId, $second['message']->conversation_id);
        $this->assertSame(4, AiChatMessage::where('conversation_id', $conversationId)->count());
    }

    public function test_system_help_messages_are_never_written_to_db(): void
    {
        $user = $this->roleUser('employee');
        $service = $this->makeService();

        $service->handleMessage($user, null, 'Hello!');

        $this->assertDatabaseMissing('ai_chat_messages', ['role' => AiMessageRole::System->value]);
    }
}
