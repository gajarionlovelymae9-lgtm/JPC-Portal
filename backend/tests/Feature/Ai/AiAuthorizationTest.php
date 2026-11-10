<?php

namespace Tests\Feature\Ai;

use App\Models\AiInsight;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 AI authorization matrix: only authenticated users can reach the AI
 * endpoints, and insights are scoped by role.
 */
class AiAuthorizationTest extends TestCase
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

    public function test_unauthenticated_chat_is_rejected(): void
    {
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hello'])->assertUnauthorized();
    }

    public function test_unauthenticated_conversations_are_rejected(): void
    {
        $this->getJson('/api/v1/ai/conversations')->assertUnauthorized();
        $this->postJson('/api/v1/ai/conversations', ['title' => 'T'])->assertUnauthorized();
    }

    public function test_unauthenticated_insights_are_rejected(): void
    {
        $this->getJson('/api/v1/ai/insights')->assertUnauthorized();
        $this->getJson('/api/v1/ai/insights/performance')->assertUnauthorized();
        $this->getJson('/api/v1/ai/insights/reports')->assertUnauthorized();
    }

    public function test_unauthenticated_summarize_is_rejected(): void
    {
        $this->postJson('/api/v1/ai/reports/1/summarize')->assertUnauthorized();
    }

    public function test_admin_sees_all_insights(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        AiInsight::factory()->create(['department_id' => $deptA->id]);
        AiInsight::factory()->create(['department_id' => $deptB->id]);

        $admin = $this->user('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/ai/insights')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_hr_is_denied_institution_ai_insights(): void
    {
        $hr = $this->user('hr');

        $this->actingAs($hr, 'sanctum')
            ->getJson('/api/v1/ai/insights')
            ->assertForbidden();

        $this->actingAs($hr, 'sanctum')
            ->getJson('/api/v1/ai/insights/performance')
            ->assertForbidden();

        $this->actingAs($hr, 'sanctum')
            ->getJson('/api/v1/ai/insights/reports')
            ->assertForbidden();
    }

    public function test_hr_is_denied_ai_assistant_endpoints(): void
    {
        $hr = $this->user('hr');

        $this->actingAs($hr, 'sanctum')
            ->getJson('/api/v1/ai/usage')
            ->assertForbidden();

        $this->actingAs($hr, 'sanctum')
            ->postJson('/api/v1/ai/chat', ['message' => 'Hello'])
            ->assertForbidden();

        $this->actingAs($hr, 'sanctum')
            ->getJson('/api/v1/employee/ai-assistant/usage')
            ->assertForbidden();
    }

    public function test_department_head_is_denied_institution_ai_insights(): void
    {
        $dept = Department::factory()->create();
        $head = $this->user('department_head', $dept);

        $this->actingAs($head, 'sanctum')
            ->getJson('/api/v1/ai/insights')
            ->assertForbidden();
    }

    public function test_employee_must_use_the_self_service_insights_endpoint(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->user('employee', $dept);

        // Institution-wide insights stay Admin-only; employees read their own
        // scoped insights through /employee/ai-insights.
        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/ai/insights')
            ->assertForbidden();
    }

    public function test_performance_and_reports_insight_endpoints_respond(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/ai/insights/performance')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/ai/insights/reports')
            ->assertOk();
    }

    public function test_authenticated_user_can_create_conversation(): void
    {
        $user = $this->user('employee');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/conversations', ['title' => 'Onboarding'])
            ->assertCreated();
    }
}
