<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 conversation CRUD + ownership policy tests.
 */
class AiConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_unauthenticated_conversations_index_is_rejected(): void
    {
        $this->getJson('/api/v1/ai/conversations')->assertUnauthorized();
    }

    public function test_index_returns_only_own_conversations(): void
    {
        $owner = $this->user('employee');
        $other = $this->user('employee');

        AiChatConversation::factory()->count(2)->create(['user_id' => $owner->id]);
        AiChatConversation::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/ai/conversations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_conversation_with_title(): void
    {
        $user = $this->user('employee');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/conversations', ['title' => 'Monthly questions'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Monthly questions');

        $this->assertDatabaseHas('ai_chat_conversations', ['user_id' => $user->id, 'title' => 'Monthly questions']);
    }

    public function test_store_defaults_title_when_omitted(): void
    {
        $user = $this->user('employee');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/conversations', [])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New Conversation');
    }

    public function test_owner_can_view_own_conversation(): void
    {
        $owner = $this->user('employee');
        $conversation = AiChatConversation::factory()->create(['user_id' => $owner->id]);
        AiChatMessage::factory()->count(3)->create(['conversation_id' => $conversation->id]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/ai/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id);
    }

    public function test_admin_can_view_any_conversation(): void
    {
        $admin = $this->user('admin');
        $other = $this->user('employee');
        $conversation = AiChatConversation::factory()->create(['user_id' => $other->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/ai/conversations/{$conversation->id}")
            ->assertOk();
    }

    public function test_employee_cannot_view_another_users_conversation(): void
    {
        $owner = $this->user('employee');
        $intruder = $this->user('employee');
        $conversation = AiChatConversation::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/v1/ai/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    public function test_owner_can_archive_own_conversation(): void
    {
        $owner = $this->user('employee');
        $conversation = AiChatConversation::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/ai/conversations/{$conversation->id}")
            ->assertOk();

        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id, 'is_archived' => true]);
    }

    public function test_employee_cannot_archive_another_users_conversation(): void
    {
        $owner = $this->user('employee');
        $intruder = $this->user('employee');
        $conversation = AiChatConversation::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/v1/ai/conversations/{$conversation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id, 'is_archived' => false]);
    }

    public function test_index_excludes_archived_conversations(): void
    {
        $owner = $this->user('employee');
        AiChatConversation::factory()->create(['user_id' => $owner->id, 'is_archived' => true]);
        AiChatConversation::factory()->create(['user_id' => $owner->id, 'is_archived' => false]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/ai/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
