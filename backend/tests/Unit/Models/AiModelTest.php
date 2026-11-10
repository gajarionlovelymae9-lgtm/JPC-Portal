<?php

namespace Tests\Unit\Models;

use App\Enums\AiInsightType;
use App\Enums\AiMessageRole;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiInsight;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 18 — AI model regression tests: enum casts, visibility semantics
 * and relationship wiring for the Phase 7 assistant models.
 */
class AiModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function chat_message_role_is_casted_to_enum(): void
    {
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->create(['conversation_id' => $conversation->id]);

        $this->assertInstanceOf(AiMessageRole::class, $message->role);
        $this->assertSame(AiMessageRole::User, $message->role);
    }

    #[Test]
    public function freshly_created_user_message_is_public(): void
    {
        // Regression: previously a newly created message serialized to []
        // because is_visible was null in memory after create().
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->create(['conversation_id' => $conversation->id, 'role' => AiMessageRole::Assistant->value]);

        $this->assertTrue($message->isPublic());
    }

    #[Test]
    public function system_role_message_is_never_public(): void
    {
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => AiMessageRole::System->value,
        ]);

        $this->assertFalse($message->isPublic());
    }

    #[Test]
    public function explicitly_hidden_message_is_not_public(): void
    {
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'is_visible' => false,
        ]);

        $this->assertFalse($message->isPublic());
    }

    #[Test]
    public function message_tool_results_are_json_casted(): void
    {
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'tool_results' => ['foo' => 'bar'],
        ]);

        $this->assertIsArray($message->tool_results);
        $this->assertSame('bar', $message->tool_results['foo']);
    }

    #[Test]
    public function conversation_has_many_messages_and_owner(): void
    {
        $user = User::factory()->create();
        $conversation = AiChatConversation::factory()->create(['user_id' => $user->id]);
        AiChatMessage::factory()->count(2)->create(['conversation_id' => $conversation->id]);
        AiChatMessage::factory()->create(['role' => AiMessageRole::Assistant->value, 'conversation_id' => $conversation->id]);

        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame($user->id, $conversation->user->id);
    }

    #[Test]
    public function archived_conversations_can_be_filtered(): void
    {
        $user = User::factory()->create();
        $active = AiChatConversation::factory()->create(['user_id' => $user->id, 'is_archived' => false]);
        $archived = AiChatConversation::factory()->create(['user_id' => $user->id, 'is_archived' => true]);

        $visible = AiChatConversation::where('user_id', $user->id)
            ->where('is_archived', false)
            ->pluck('id')
            ->all();

        $this->assertSame([$active->id], $visible);
        $this->assertNotContains($archived->id, $visible);
    }

    #[Test]
    public function insight_type_is_casted_and_relationships_wire(): void
    {
        $dept = Department::factory()->create();
        $user = User::factory()->create();
        $emp = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $dept->id]);

        $insight = AiInsight::factory()->create([
            'employee_id' => $emp->id,
            'department_id' => $dept->id,
        ]);

        $this->assertInstanceOf(AiInsightType::class, $insight->type);
        $this->assertSame($emp->id, $insight->employee->id);
        $this->assertSame($dept->id, $insight->department->id);

        $this->assertIsArray($insight->metadata);
    }
}
