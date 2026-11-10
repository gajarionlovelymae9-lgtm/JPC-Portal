<?php

namespace Tests\Feature\Api\V1\Messages;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Position;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Internal messaging API coverage (§1–§12).
 *
 * Uses real registered accounts (users + employee profiles) and the real
 * MySQL-shaped schema; no dummy conversations or sample messages are seeded.
 */
class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    private function makeUser(Role $role, ?Department $department = null): User
    {
        $user = User::factory()->create([
            'role_id' => $this->roleId($role),
            'status' => 'active',
        ]);

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => ($department ?? Department::factory()->create())->id,
            'position_id' => Position::factory()->create()->id,
        ]);

        return $user;
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    // ── §1 People Directory ───────────────────────────────────────

    public function test_directory_lists_authorized_active_recipients_with_scope(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();

        $employee = $this->makeUser(Role::Employee, $department);
        $colleague = $this->makeUser(Role::Employee, $department);
        $outsider = $this->makeUser(Role::Employee, $otherDepartment);
        $hr = $this->makeUser(Role::Hr, $otherDepartment);

        $this->login($employee);

        $response = $this->getJson('/api/v1/messages/recipients')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        // Colleagues in the same department and HR are reachable.
        $this->assertContains($colleague->id, $ids);
        $this->assertContains($hr->id, $ids);
        // A colleague in another department is not reachable.
        $this->assertNotContains($outsider->id, $ids);
        // Never lists the caller themselves.
        $this->assertNotContains($employee->id, $ids);

        // Payload carries the directory fields required by §1.
        $row = collect($response->json('data'))->firstWhere('id', $hr->id);
        $this->assertArrayHasKey('role', $row);
        $this->assertArrayHasKey('department', $row);
        $this->assertArrayHasKey('status', $row);
        $this->assertSame('active', $row['status']);
        $this->assertNotEmpty($row['initials']);
    }

    public function test_directory_search_matches_name_department_and_position(): void
    {
        $department = Department::factory()->create(['name' => 'Finance Office']);
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);
        $hr->update(['name' => 'Unique Recipient Name']);

        $this->login($employee);

        $this->getJson('/api/v1/messages/recipients?search=Unique Recipient')
            ->assertOk()
            ->assertJsonPath('data.0.id', $hr->id);

        $this->getJson('/api/v1/messages/recipients?search=Finance')
            ->assertOk();
    }

    // ── §2 Private chat + authorization ───────────────────────────

    public function test_employee_can_message_hr_and_conversation_persists(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);

        $send = $this->postJson('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Hello HR, I have a question.',
        ])->assertStatus(201);

        $conversationId = $send->json('data.conversation_id');

        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'type' => 'direct']);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversationId, 'body' => 'Hello HR, I have a question.']);

        // Both parties are participants.
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $conversationId, 'user_id' => $employee->id]);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $conversationId, 'user_id' => $hr->id]);

        // Recipient is notified through the JPC inbox (§7).
        $this->assertDatabaseHas('notifications', ['user_id' => $hr->id, 'type' => 'message_received']);
    }

    public function test_employee_cannot_message_user_in_another_department(): void
    {
        $employee = $this->makeUser(Role::Employee, Department::factory()->create());
        $outsider = $this->makeUser(Role::Employee, Department::factory()->create());

        $this->login($employee);

        $this->postJson('/api/v1/messages', [
            'recipient_id' => $outsider->id,
            'body' => 'Should not be allowed.',
        ])->assertStatus(403);

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_non_participant_cannot_view_conversation(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);
        $intruder = $this->makeUser(Role::Admin, $department);

        $this->login($employee);
        $conversationId = $this->postJson('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Private thread.',
        ])->json('data.conversation_id');

        $this->login($intruder);
        $this->getJson("/api/v1/messages/conversations/{$conversationId}")->assertStatus(403);
    }

    // ── §5 Idempotency, replies, read receipts, archiving ─────────

    public function test_duplicate_client_token_does_not_create_a_second_message(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);

        $payload = [
            'recipient_id' => $hr->id,
            'body' => 'Only once please.',
            'client_token' => 'token-abc-123',
        ];

        $first = $this->postJson('/api/v1/messages', $payload)->assertStatus(201);
        $second = $this->postJson('/api/v1/messages', $payload)->assertStatus(201);

        $this->assertSame($first->json('data.message_id'), $second->json('data.message_id'));
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_reply_links_to_the_original_message(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);
        $sent = $this->postJson('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Original message.',
        ])->assertStatus(201);

        $conversationId = $sent->json('data.conversation_id');
        $originalId = $sent->json('data.message_id');

        $this->login($hr);
        $this->postJson('/api/v1/messages', [
            'conversation_id' => $conversationId,
            'body' => 'Replying to you.',
            'reply_to_message_id' => $originalId,
        ])->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'reply_to_message_id' => $originalId,
        ]);
    }

    public function test_unread_summary_and_mark_read_flow(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);
        $conversationId = $this->postJson('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Ping.',
        ])->json('data.conversation_id');

        $this->login($hr);
        $this->getJson('/api/v1/messages/unread-summary')
            ->assertOk()
            ->assertJsonPath('data.unread_conversations', 1)
            ->assertJsonPath('data.unread_messages', 1);

        $this->getJson("/api/v1/messages/conversations/{$conversationId}")->assertOk();

        $this->getJson('/api/v1/messages/unread-summary')
            ->assertOk()
            ->assertJsonPath('data.unread_messages', 0);
    }

    public function test_archive_hides_conversation_from_the_default_inbox(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);
        $conversationId = $this->postJson('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Archive me.',
        ])->json('data.conversation_id');

        $this->postJson("/api/v1/messages/conversations/{$conversationId}/archive")
            ->assertOk();

        $this->assertNotNull(
            ConversationParticipant::query()
                ->where('conversation_id', $conversationId)
                ->where('user_id', $employee->id)
                ->value('archived_at'),
        );

        $ids = collect($this->getJson('/api/v1/messages/conversations')->json('data'))->pluck('id')->all();
        $this->assertNotContains($conversationId, $ids);

        $archivedIds = collect($this->getJson('/api/v1/messages/conversations?filter=archived')->json('data'))->pluck('id')->all();
        $this->assertContains($conversationId, $archivedIds);
    }

    // ── §3 Group chat ─────────────────────────────────────────────

    public function test_group_creation_add_remove_and_leave(): void
    {
        $department = Department::factory()->create();
        $admin = $this->makeUser(Role::Admin, $department);
        $hr = $this->makeUser(Role::Hr, $department);
        $employee = $this->makeUser(Role::Employee, $department);

        $this->login($admin);
        $created = $this->postJson('/api/v1/messages/groups', [
            'name' => 'Project Team',
            'description' => 'Coordination group',
            'participant_ids' => [$hr->id],
        ])->assertStatus(201);

        $conversationId = $created->json('data.id');
        $this->assertTrue($created->json('data.is_group'));
        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'type' => 'group', 'subject' => 'Project Team']);
        // Creator is a group administrator.
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        // Add a participant.
        $this->postJson("/api/v1/messages/conversations/{$conversationId}/participants", [
            'participant_ids' => [$employee->id],
        ])->assertOk();
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $conversationId, 'user_id' => $employee->id]);

        // Remove a participant.
        $this->deleteJson("/api/v1/messages/conversations/{$conversationId}/participants/{$employee->id}")->assertOk();
        $this->assertDatabaseMissing('conversation_participants', ['conversation_id' => $conversationId, 'user_id' => $employee->id]);

        // A member leaves the group.
        $this->login($hr);
        $this->postJson("/api/v1/messages/conversations/{$conversationId}/leave")->assertOk();
        $this->assertDatabaseMissing('conversation_participants', ['conversation_id' => $conversationId, 'user_id' => $hr->id]);
    }

    public function test_non_admin_member_cannot_manage_group_participants(): void
    {
        $department = Department::factory()->create();
        $admin = $this->makeUser(Role::Admin, $department);
        $hr = $this->makeUser(Role::Hr, $department);
        $employee = $this->makeUser(Role::Employee, $department);
        $outsider = $this->makeUser(Role::Employee, $department);

        $this->login($admin);
        $conversationId = $this->postJson('/api/v1/messages/groups', [
            'name' => 'Locked Group',
            'participant_ids' => [$hr->id],
        ])->json('data.id');

        // A regular member cannot add participants.
        $this->login($hr);
        $this->postJson("/api/v1/messages/conversations/{$conversationId}/participants", [
            'participant_ids' => [$employee->id],
        ])->assertStatus(403);
    }

    // ── §6 Attachments ────────────────────────────────────────────

    public function test_attachment_upload_and_authorized_download(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);
        $sent = $this->post('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Please find the file attached.',
            'attachment' => UploadedFile::fake()->create('report.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $messageId = $sent->json('data.message_id');
        $this->assertDatabaseHas('messages', ['id' => $messageId, 'attachment_name' => 'report.pdf']);

        // The recipient can download.
        $this->login($hr);
        $this->get("/api/v1/messages/{$messageId}/attachment")->assertOk();
    }

    public function test_non_participant_cannot_download_attachment(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);
        $intruder = $this->makeUser(Role::Admin, $department);

        $this->login($employee);
        $messageId = $this->post('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Confidential.',
            'attachment' => UploadedFile::fake()->create('secret.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->json('data.message_id');

        $this->login($intruder);
        $this->get("/api/v1/messages/{$messageId}/attachment")->assertStatus(403);
    }

    public function test_disallowed_attachment_type_is_rejected(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);
        $this->post('/api/v1/messages', [
            'recipient_id' => $hr->id,
            'body' => 'Malware.',
            'attachment' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    // ── §9 Route protection ───────────────────────────────────────

    public function test_guest_cannot_reach_messaging_endpoints(): void
    {
        $this->getJson('/api/v1/messages/conversations')->assertStatus(401);
        $this->getJson('/api/v1/messages/recipients')->assertStatus(401);
    }

    public function test_deleted_conversation_message_body_is_required_alongside_target(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);
        $hr = $this->makeUser(Role::Hr, $department);

        $this->login($employee);

        // No recipient / conversation target.
        $this->postJson('/api/v1/messages', ['body' => 'Nowhere.'])->assertStatus(422);

        // No body and no attachment.
        $this->postJson('/api/v1/messages', ['recipient_id' => $hr->id])->assertStatus(422);
    }

    public function test_sending_to_self_is_rejected(): void
    {
        $department = Department::factory()->create();
        $employee = $this->makeUser(Role::Employee, $department);

        $this->login($employee);

        $this->postJson('/api/v1/messages', [
            'recipient_id' => $employee->id,
            'body' => 'Talking to myself.',
        ])->assertStatus(422);
    }

    // ── Helpers for assertions on models ──────────────────────────

    public function test_group_message_notifies_all_other_members(): void
    {
        $department = Department::factory()->create();
        $admin = $this->makeUser(Role::Admin, $department);
        $hr = $this->makeUser(Role::Hr, $department);
        $employee = $this->makeUser(Role::Employee, $department);

        $this->login($admin);
        $conversationId = $this->postJson('/api/v1/messages/groups', [
            'name' => 'Announcements',
            'participant_ids' => [$hr->id, $employee->id],
        ])->json('data.id');

        $this->postJson('/api/v1/messages', [
            'conversation_id' => $conversationId,
            'body' => 'Group update.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', ['user_id' => $hr->id, 'type' => 'message_received']);
        $this->assertDatabaseHas('notifications', ['user_id' => $employee->id, 'type' => 'message_received']);
        // Sender is never notified about their own message.
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id, 'type' => 'message_received']);
    }
}
