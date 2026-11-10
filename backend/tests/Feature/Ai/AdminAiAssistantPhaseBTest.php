<?php

namespace Tests\Feature\Ai;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use App\Services\AI\AiToolRegistry;
use App\Services\AiReports\Ai\Contracts\AiCompletion;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B (mocked/config tests, no real LLM): NLP report summarization,
 * progress analysis, insights generation, evidence/source refs, missing info,
 * and real-provider guard (fake provider never treated as success).
 */
class AdminAiAssistantPhaseBTest extends TestCase
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

    private function bindMockProvider(string $text = 'Grounded advisory response.'): void
    {
        config([
            'ai_reports.ai.provider' => 'openai',
            'ai_reports.ai.model' => 'test-model',
            'ai_reports.ai.http.endpoint' => 'https://example.test/v1/chat/completions',
            'ai_reports.ai.http.api_key' => 'test-key-not-real',
        ]);

        $this->app->instance(AiProvider::class, new class($text) implements AiProvider
        {
            public function __construct(private string $text) {}

            public function name(): string
            {
                return 'mock-openai-compatible';
            }

            public function complete(string $system, string $user, AiRequest $request): AiCompletion
            {
                return new AiCompletion($this->text, $this->name(), $request->model, ['mock' => true]);
            }
        });
    }

    public function test_insight_generation_rejects_unconfigured_fake_provider(): void
    {
        config([
            'ai_reports.ai.provider' => 'fake',
            'ai_reports.ai.http.endpoint' => '',
            'ai_reports.ai.http.api_key' => '',
        ]);

        $admin = $this->adminUser();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-insights/generate', [
            'employee_id' => $employee->id,
            'type' => 'PERFORMANCE_INSIGHT',
            'period' => now()->format('Y-m'),
        ]);

        $response->assertStatus(503)->assertJsonPath('success', false);
        $this->assertStringContainsString('not configured', strtolower((string) $response->json('message')));
    }

    public function test_insight_generation_requires_employee_or_report_scope(): void
    {
        config(['ai_reports.ai.provider' => 'fake']);
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-insights/generate', [
            'type' => 'PERFORMANCE_INSIGHT',
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_generate_insights(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $employee = Employee::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/ai-insights/generate', [
            'employee_id' => $employee->id,
        ])->assertForbidden();
    }

    public function test_admin_chat_summarizes_actual_report_with_mocked_real_provider_and_source_refs(): void
    {
        $this->bindMockProvider('Summary of Report #REPLACE: The employee completed assigned work. Source: Report #REPLACE. Missing information: no challenges field provided.');

        $admin = $this->adminUser();
        $employee = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $report = Report::factory()->create([
            'employee_id' => $employee->id,
            'title' => 'Weekly Accomplishment Report',
            'description' => 'Completed onboarding documentation and assisted with records.',
            'content' => 'Prepared three internal forms and coordinated with HR.',
            'accomplishments' => 'Delivered requested outputs.',
            'status' => 'submitted',
        ]);

        // Refresh bound mock with actual report ID in text.
        $this->app->instance(AiProvider::class, new class($report->id) implements AiProvider
        {
            public function __construct(private int $reportId) {}

            public function name(): string
            {
                return 'mock-openai-compatible';
            }

            public function complete(string $system, string $user, AiRequest $request): AiCompletion
            {
                $this->assertStringContainsString('Authorized evidence', $user);
                $this->assertStringContainsString((string) $this->reportId, $user);

                return new AiCompletion(
                    'Report #'.$this->reportId.' (Weekly Accomplishment Report) summary: completed onboarding documentation, prepared three internal forms, and coordinated with HR. Source reference: Report #'.$this->reportId.'. Missing or incomplete information: challenges_encountered and next_plans were not provided.',
                    $this->name(),
                    $request->model,
                    ['mock' => true]
                );
            }

            private function assertStringContainsString(string $needle, string $haystack): void
            {
                if (! str_contains($haystack, $needle)) {
                    throw new \RuntimeException('Expected evidence prompt to contain: '.$needle);
                }
            }
        });

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-assistant/chat', [
            'message' => 'Please summarize report #'.$report->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.ok', true);
        $message = (string) data_get($response->json(), 'data.message.content');
        $this->assertStringContainsString('Report #'.$report->id, $message);
        $this->assertStringContainsString('Source', $message);
        $this->assertStringContainsString('Missing', $message);
        $toolResults = data_get($response->json(), 'data.message.tool_results');
        $this->assertIsArray($toolResults);
        $this->assertNotEmpty($toolResults['source_records'] ?? $toolResults['evidence_sources'] ?? []);
    }

    public function test_admin_chat_identifies_missing_report_record_with_mocked_real_provider(): void
    {
        $this->bindMockProvider('Report #999999 was not found in authorized records. Missing or incomplete information: the requested report ID does not exist or is not accessible. Source references: none for that report.');

        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-assistant/chat', [
            'message' => 'Summarize report #999999',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.ok', true);
        $message = (string) data_get($response->json(), 'data.message.content');
        $this->assertStringContainsString('999999', $message);
        $this->assertStringContainsStringIgnoringCase('missing', $message);
    }

    public function test_admin_chat_progress_analysis_for_employee_uses_actual_records_with_mocked_real_provider(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create(['first_name' => 'Ben', 'last_name' => 'Santos']);
        Report::factory()->create([
            'employee_id' => $employee->id,
            'title' => 'Monthly Progress',
            'description' => 'Submitted monthly progress update.',
            'content' => 'Completed assigned tasks for the month.',
            'status' => 'submitted',
        ]);

        $this->app->instance(AiProvider::class, new class($employee->id) implements AiProvider
        {
            public function __construct(private int $employeeId) {}

            public function name(): string
            {
                return 'mock-openai-compatible';
            }

            public function complete(string $system, string $user, AiRequest $request): AiCompletion
            {
                if (! str_contains($user, 'employee_progress') && ! str_contains($user, 'Employee #'.$this->employeeId) && ! str_contains($user, (string) $this->employeeId)) {
                    throw new \RuntimeException('Expected employee progress evidence in prompt.');
                }

                return new AiCompletion(
                    'Employee #'.$this->employeeId.' progress analysis (authorized records): recent report activity exists (Monthly Progress). Source references: employee:'.$this->employeeId.', report records. Missing or incomplete information: some period metrics may be unavailable if performance snapshots are not recorded; verify current records.',
                    $this->name(),
                    $request->model,
                    ['mock' => true]
                );
            }
        });
        config([
            'ai_reports.ai.provider' => 'openai',
            'ai_reports.ai.model' => 'test-model',
            'ai_reports.ai.http.endpoint' => 'https://example.test/v1/chat/completions',
            'ai_reports.ai.http.api_key' => 'test-key-not-real',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-assistant/chat', [
            'message' => 'Analyze employee progress for employee #'.$employee->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.ok', true);
        $content = (string) data_get($response->json(), 'data.message.content');
        $this->assertStringContainsString((string) $employee->id, $content);
        $this->assertStringContainsStringIgnoringCase('source', $content);
        $this->assertStringContainsStringIgnoringCase('missing', $content);
    }

    public function test_admin_chat_content_excludes_internal_advisory_metadata(): void
    {
        $this->bindMockProvider("Here is the department activity summary.\n\n- Three reports were approved this month.\n- Two tasks remain overdue.");

        $admin = $this->adminUser();
        $department = Department::factory()->create();
        Employee::factory()->create(['user_id' => $admin->id, 'department_id' => $department->id]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-assistant/chat', [
            'message' => 'Give me the department performance report for this month',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.ok', true);

        $content = (string) data_get($response->json(), 'data.message.content');

        // The user-facing answer is the natural-language reply only.
        $this->assertStringContainsString('department activity summary', $content);
        $this->assertStringNotContainsString('Phase B', $content);
        $this->assertStringNotContainsString('advisory response', $content);
        $this->assertStringNotContainsString('Period context', $content);
        $this->assertStringNotContainsString('Source references', $content);
        $this->assertStringNotContainsString('analytics_tool', $content);
        $this->assertStringNotContainsString('GetDepartmentReportTool', $content);
        $this->assertStringNotContainsString('No future performance predictions', $content);

        // Internal metadata is still stored separately for audit/provenance.
        $toolResults = data_get($response->json(), 'data.message.tool_results');
        $this->assertIsArray($toolResults);
        $this->assertSame('mock-openai-compatible', $toolResults['provider']);
        $this->assertTrue($toolResults['advisory']['ai_generated']);
        $this->assertTrue($toolResults['advisory']['human_verification_required']);
        $this->assertFalse($toolResults['advisory']['future_performance_predictions']);
        $this->assertFalse($toolResults['advisory']['automatic_employment_decisions']);
        $this->assertNotEmpty($toolResults['period_label']);
        $this->assertNotEmpty($toolResults['source_records']);
        $this->assertContains('Department performance report', $toolResults['source_labels']);
    }

    public function test_get_department_report_tool_executes_and_preserves_analytics(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);
        Report::factory()->create([
            'employee_id' => $employee->id,
            'title' => 'Department Monthly Report',
            'status' => 'approved',
        ]);

        $tool = $this->app->make(AiToolRegistry::class)->find('GetDepartmentReportTool');
        $this->assertNotNull($tool);

        $result = $tool->execute([
            'department_id' => $department->id,
            'year' => now()->year,
            'month' => now()->month,
        ], $admin);

        $this->assertSame('GetDepartmentReportTool', $result['tool_name']);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('department', $result);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('has_data', $result);
        $this->assertSame($department->id, (int) $result['department']['id']);
    }

    public function test_admin_chat_history_keeps_metadata_out_of_stored_content(): void
    {
        $this->bindMockProvider('A short, natural answer about authorized records.');

        $admin = $this->adminUser();

        $first = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-assistant/chat', [
            'message' => 'Summarize recent department activity',
        ])->assertOk();

        $conversationId = (int) $first->json('data.conversation_id');
        $this->assertGreaterThan(0, $conversationId);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/ai-assistant/chat', [
            'message' => 'Any updates for this month?',
            'conversation_id' => $conversationId,
        ])->assertOk();

        $messages = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/ai-assistant/conversations/'.$conversationId.'/messages')
            ->assertOk()
            ->json('data');

        $assistantMessages = array_values(array_filter($messages, fn ($m) => ($m['role'] ?? null) === 'assistant'));
        $this->assertCount(2, $assistantMessages);

        foreach ($assistantMessages as $message) {
            $content = (string) ($message['content'] ?? '');
            $this->assertStringNotContainsString('Phase B', $content);
            $this->assertStringNotContainsString('Period context', $content);
            $this->assertIsArray($message['tool_results'] ?? null);
            $this->assertTrue($message['tool_results']['advisory']['human_verification_required']);
        }
    }
}
