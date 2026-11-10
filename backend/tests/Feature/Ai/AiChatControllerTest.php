<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 chat endpoint tests.
 */
class AiChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hello'])
            ->assertUnauthorized();
    }

    public function test_message_is_required(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_message_cannot_exceed_max_length(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', ['message' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_conversation_id_must_exist(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', ['message' => 'Hello', 'conversation_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversation_id');
    }

    public function test_send_message_creates_conversation_and_messages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', ['message' => 'What is the weather today?'])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $response->assertJsonStructure([
            'data' => ['message', 'intent', 'period', 'results', 'actions'],
        ]);

        $this->assertSame('DEFAULT', $response->json('data.intent'));
        $this->assertDatabaseCount('ai_chat_conversations', 1);
        $this->assertDatabaseCount('ai_chat_messages', 2);
    }

    public function test_send_message_reuses_existing_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => 'Test']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', [
                'message' => 'Hello again',
                'conversation_id' => $conversation->id,
            ])
            ->assertOk();

        $this->assertSame(2, AiChatMessage::where('conversation_id', $conversation->id)->count());
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_cannot_send_message_into_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $intruder = User::factory()->create();
        $intruder->assignRole('employee');

        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'title' => 'Private']);

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v1/ai/chat', [
                'message' => 'Hello',
                'conversation_id' => $conversation->id,
            ])
            ->assertForbidden();
    }

    public function test_department_head_cannot_access_ai_chat(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('department_head');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', ['message' => 'Show the top performers leaderboard'])
            ->assertForbidden();
    }

    public function test_hr_user_cannot_access_ai_chat(): void
    {
        $user = User::factory()->create();
        $user->assignRole('hr');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/chat', ['message' => 'Show the top performers leaderboard'])
            ->assertForbidden();
    }
}
