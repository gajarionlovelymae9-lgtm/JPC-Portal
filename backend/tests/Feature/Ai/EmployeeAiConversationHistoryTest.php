<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\AiReports\Ai\Contracts\AiCompletion;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\Ai\Exceptions\AiProviderException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Employee JPC AIDE conversation-history regressions.
 *
 * Covers the "conversation does not appear / update in the Conversation
 * History sidebar" defect: conversation creation, first-message titling,
 * in-place history refresh metadata, message persistence on AI failure, and
 * per-user ownership.
 */
class EmployeeAiConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function employee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => Department::factory()->create()->id,
        ]);

        return $user;
    }

    /**
     * Scenarios A, B, C: a new chat with a first message returns everything the
     * sidebar needs to render the entry without a page reload.
     */
    public function test_first_message_creates_conversation_and_returns_history_metadata(): void
    {
        $user = $this->employee();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Please help me draft my weekly accomplishment summary for this week.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $response->assertJsonStructure([
            'data' => [
                'conversation_id',
                'conversation' => ['id', 'title', 'message_count', 'last_message_at', 'created_at', 'updated_at'],
                'message' => ['id', 'conversation_id', 'role', 'content', 'created_at'],
                'user_message' => ['id', 'conversation_id', 'role', 'content'],
                'ok',
            ],
        ]);

        $conversationId = $response->json('data.conversation_id');

        $this->assertSame((int) $conversationId, $response->json('data.conversation.id'));
        $this->assertSame('user', $response->json('data.user_message.role'));
        $this->assertSame('assistant', $response->json('data.message.role'));
        $this->assertNotNull($response->json('data.conversation.updated_at'));

        $this->assertDatabaseCount('ai_chat_conversations', 1);
        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversationId, 'user_id' => $user->id]);
        $this->assertSame(2, AiChatMessage::where('conversation_id', $conversationId)->count());
    }

    /**
     * Scenario C: the freshly created conversation shows up in the history list
     * immediately, with its preview/timestamp populated.
     */
    public function test_new_conversation_appears_in_history_list_immediately(): void
    {
        $user = $this->employee();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Summarize my completed tasks this week for my report.',
            ])
            ->json('data.conversation_id');

        $list = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/employee/ai-assistant/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $this->assertSame($conversationId, $list->json('data.data.0.id'));
        $this->assertNotNull($list->json('data.data.0.last_message_at'));
        $this->assertGreaterThanOrEqual(2, (int) $list->json('data.data.0.message_count'));
    }

    /**
     * Scenarios D, E: continuing a conversation reuses the same record and does
     * not spawn a duplicate conversation.
     */
    public function test_second_message_reuses_conversation_without_duplicates(): void
    {
        $user = $this->employee();

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Summarize my completed tasks this week for my report.',
            ])
            ->json('data');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'conversation_id' => $first['conversation_id'],
                'message' => 'Now also include the challenges I encountered and my next plans.',
            ])
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $first['conversation_id']);

        $this->assertDatabaseCount('ai_chat_conversations', 1);
        $this->assertSame(4, AiChatMessage::where('conversation_id', $first['conversation_id'])->count());
    }

    /**
     * Scenario C: an empty "New Chat" placeholder is re-titled from the first
     * message so the history preview is meaningful.
     */
    public function test_empty_new_chat_title_is_replaced_by_first_message(): void
    {
        $user = $this->employee();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/conversations', ['title' => 'New Chat'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New Chat')
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'conversation_id' => $conversationId,
                'message' => 'Draft my daily accomplishment report entry.',
            ])
            ->assertOk()
            ->assertJsonPath('data.conversation.title', 'Draft my daily accomplishment report entry.');

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversationId,
            'title' => 'Draft my daily accomplishment report entry.',
        ]);
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    /**
     * A user-chosen title must never be overwritten by the first message.
     */
    public function test_user_chosen_title_is_not_overwritten_by_first_message(): void
    {
        $user = $this->employee();
        $conversation = AiChatConversation::create([
            'user_id' => $user->id,
            'title' => 'Monthly curriculum notes',
            'is_archived' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'conversation_id' => $conversation->id,
                'message' => 'Summarize my weekly report accomplishments.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => 'Monthly curriculum notes',
        ]);
    }

    /**
     * Scenarios F, G, H: messages persist server-side and are returned in
     * chronological order when a conversation is (re)loaded.
     */
    public function test_messages_are_returned_in_chronological_order(): void
    {
        $user = $this->employee();

        $conversationId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Summarize my completed tasks this week for my report.',
            ])
            ->json('data.conversation_id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'conversation_id' => $conversationId,
                'message' => 'Now also include the challenges I encountered and my next plans.',
            ])
            ->assertOk();

        $messages = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/employee/ai-assistant/conversations/{$conversationId}/messages")
            ->assertOk()
            ->json('data');

        $this->assertSame(['user', 'assistant', 'user', 'assistant'], array_column($messages, 'role'));
    }

    /**
     * Scenarios I: a failing AI provider must not lose the user's message.
     */
    public function test_failed_ai_generation_still_saves_user_message(): void
    {
        $user = $this->employee();
        $this->useFailingProvider();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Summarize my completed tasks for my weekly report.',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.user_message.role', 'user');

        $conversationId = $response->json('data.conversation_id');

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'Summarize my completed tasks for my weekly report.',
        ]);

        $this->assertSame(1, AiChatMessage::where('conversation_id', $conversationId)->where('role', 'user')->count());
        $this->assertSame(2, AiChatMessage::where('conversation_id', $conversationId)->count());
    }

    /**
     * Scenario J: re-sending after a failure keeps every user message exactly
     * once (the failed attempt is never deleted or double-saved) and reuses the
     * same conversation.
     */
    public function test_failure_then_follow_up_does_not_duplicate_user_messages(): void
    {
        $user = $this->employee();
        $this->useFlakyProvider();

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'message' => 'Summarize my weekly report accomplishments and completed tasks.',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', false);

        $conversationId = $first->json('data.conversation_id');
        $this->assertSame(1, AiChatMessage::where('conversation_id', $conversationId)->where('role', 'user')->count());

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'conversation_id' => $conversationId,
                'message' => 'Summarize my weekly report accomplishments and completed tasks.',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.message.role', 'assistant');

        $this->assertDatabaseCount('ai_chat_conversations', 1);
        $this->assertSame(2, AiChatMessage::where('conversation_id', $conversationId)->where('role', 'user')->count());
    }

    /**
     * Scenario K: search filters by title within the user's own history.
     */
    public function test_search_returns_only_matching_conversations(): void
    {
        $user = $this->employee();
        AiChatConversation::create(['user_id' => $user->id, 'title' => 'Weekly curriculum mapping', 'is_archived' => false]);
        AiChatConversation::create(['user_id' => $user->id, 'title' => 'Monthly budget notes', 'is_archived' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/employee/ai-assistant/conversations?q=curriculum')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Weekly curriculum mapping');
    }

    /**
     * Scenario G/H: the history list only ever returns conversations owned by
     * the authenticated employee.
     */
    public function test_history_list_excludes_other_users_conversations(): void
    {
        $owner = $this->employee();
        $other = $this->employee();
        AiChatConversation::create(['user_id' => $owner->id, 'title' => 'Mine', 'is_archived' => false]);
        AiChatConversation::create(['user_id' => $other->id, 'title' => 'Theirs', 'is_archived' => false]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/employee/ai-assistant/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Mine');
    }

    /**
     * Security: an employee cannot post into another employee's conversation.
     */
    public function test_employee_cannot_send_into_another_users_conversation(): void
    {
        $owner = $this->employee();
        $intruder = $this->employee();
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'title' => 'Owner only', 'is_archived' => false]);

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v1/employee/ai-assistant/chat', [
                'conversation_id' => $conversation->id,
                'message' => 'Summarize my weekly report accomplishments.',
            ])
            ->assertForbidden();

        $this->assertSame(0, AiChatMessage::where('conversation_id', $conversation->id)->count());
    }

    /**
     * Scenario L: delete archives only the authorized conversation.
     */
    public function test_delete_archives_only_the_owners_conversation(): void
    {
        $owner = $this->employee();
        $intruder = $this->employee();
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'title' => 'Private notes', 'is_archived' => false]);

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/v1/employee/ai-assistant/conversations/{$conversation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id, 'is_archived' => false]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/employee/ai-assistant/conversations/{$conversation->id}")
            ->assertOk();

        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id, 'is_archived' => true]);
    }

    private function useFailingProvider(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            public function name(): string
            {
                return 'failing';
            }

            public function complete(string $system, string $user, AiRequest $request): AiCompletion
            {
                throw new AiProviderException('Simulated AI provider outage.');
            }
        });
    }

    private function useFlakyProvider(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            private int $failuresRemaining = 1;

            public function name(): string
            {
                return 'flaky';
            }

            public function complete(string $system, string $user, AiRequest $request): AiCompletion
            {
                if ($this->failuresRemaining > 0) {
                    $this->failuresRemaining--;

                    throw new AiProviderException('Simulated AI provider outage.');
                }

                return new AiCompletion(
                    text: 'Here is a grounded draft based only on the details you shared.',
                    provider: 'flaky',
                    model: 'test',
                );
            }
        });
    }
}
