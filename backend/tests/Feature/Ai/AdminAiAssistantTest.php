<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2+3 foundation: Admin-only JPC AIDE assistant.
 * Uses no real LLM/provider calls; verifies auth, access control, and
 * config-not-configured handling (no fake-provider success response).
 */
class AdminAiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config([
            'ai_reports.ai.provider' => 'fake',
            'ai_reports.ai.model' => '',
            'ai_reports.ai.http.endpoint' => '',
            'ai_reports.ai.http.api_key' => '',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_unauthenticated_admin_assistant_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/ai-assistant/usage')->assertUnauthorized();
        $this->postJson('/api/v1/admin/ai-assistant/chat', ['message' => 'Hello'])->assertUnauthorized();
        $this->getJson('/api/v1/admin/ai-assistant/conversations')->assertUnauthorized();
    }

    public function test_non_admin_roles_cannot_access_admin_assistant(): void
    {
        foreach (['employee', 'hr', 'department_head'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/ai-assistant/usage')->assertForbidden();
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/ai-assistant/chat', ['message' => 'Hello'])->assertForbidden();
        }
    }

    public function test_admin_can_read_usage_status(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/ai-assistant/usage')
            ->assertOk()
            ->assertJsonPath('data.allowed', true);
    }

    public function test_admin_chat_rejects_fake_or_unconfigured_provider_without_consuming_quota(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/ai-assistant/chat', ['message' => 'Summarize recent reports']);

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('not configured', strtolower((string) $response->json('message')));

        $usage = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/ai-assistant/usage');
        $usage->assertOk();
        $this->assertSame(0, (int) $usage->json('data.used'));
    }

    public function test_admin_conversation_lifecycle_is_scoped_to_owner(): void
    {
        $admin = $this->userWithRole('admin');

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/ai-assistant/conversations', ['title' => 'Admin Test'])
            ->assertCreated();
        $id = $create->json('data.id');
        $this->assertNotNull($id);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/ai-assistant/conversations')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Admin Test']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/ai-assistant/conversations/{$id}/messages")
            ->assertOk();

        $other = $this->userWithRole('admin');
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/admin/ai-assistant/conversations/{$id}/messages")
            ->assertForbidden();
    }
}
