<?php

namespace Tests\Feature\Api\Notifications;

use App\Enums\Role;
use App\Models\Notification;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function employeeUser(): User
    {
        $role = RoleModel::query()->where('name', Role::Employee->value)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function notificationFor(User $user, array $attributes = []): Notification
    {
        return Notification::factory()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    // ── ACCESS CONTROL ─────────────────────────────────────────

    public function test_unauthenticated_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    }

    public function test_employee_can_access_own_notifications(): void
    {
        $user = $this->employeeUser();
        $this->notificationFor($user, ['type' => 'task_assigned']);
        $this->login($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'task_assigned')
            ->assertJsonStructure(['data' => [['id', 'type', 'title', 'message', 'priority', 'read_at', 'created_at', 'action_url', 'category']]]);
    }

    public function test_user_cannot_see_another_users_notifications(): void
    {
        $owner = $this->employeeUser();
        $other = $this->employeeUser();
        $this->notificationFor($owner);
        $this->login($other);

        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = $this->employeeUser();
        $other = $this->employeeUser();
        $notification = $this->notificationFor($owner);
        $this->login($other);

        $this->getJson("/api/v1/notifications/{$notification->id}")->assertForbidden();
        $this->postJson("/api/v1/notifications/{$notification->id}/read")->assertForbidden();
        $this->deleteJson("/api/v1/notifications/{$notification->id}")->assertForbidden();
    }

    // ── UNREAD COUNT ───────────────────────────────────────────

    public function test_unread_count_counts_only_unread(): void
    {
        $user = $this->employeeUser();
        $this->notificationFor($user, ['type' => 'task_assigned']);
        $this->notificationFor($user, ['type' => 'task_submitted', 'read_at' => now()->subHour()]);
        $this->login($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_unread_endpoint_returns_only_unread(): void
    {
        $user = $this->employeeUser();
        $this->notificationFor($user, ['type' => 'task_assigned']);
        $this->notificationFor($user, ['type' => 'task_submitted', 'read_at' => now()->subHour()]);
        $this->login($user);

        $this->getJson('/api/v1/notifications/unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'task_assigned');
    }

    // ── MARK / DELETE ──────────────────────────────────────────

    public function test_mark_read_sets_timestamp_once(): void
    {
        $user = $this->employeeUser();
        $notification = $this->notificationFor($user);
        $this->login($user);

        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.read_at', null);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', $notification->fresh()->read_at?->toISOString());
    }

    public function test_mark_all_read_marks_everything_for_user(): void
    {
        $user = $this->employeeUser();
        $this->notificationFor($user);
        $this->notificationFor($user);
        $this->login($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 2);

        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 0);
    }

    public function test_delete_removes_notification(): void
    {
        $user = $this->employeeUser();
        $notification = $this->notificationFor($user);
        $this->login($user);

        $this->deleteJson("/api/v1/notifications/{$notification->id}")->assertNoContent();
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    // ── FILTERS ────────────────────────────────────────────────

    public function test_filter_unread_read_and_category(): void
    {
        $user = $this->employeeUser();
        $this->notificationFor($user, ['type' => 'deadline_reminder', 'reminder_days' => 1]);
        $this->notificationFor($user, ['type' => 'task_approved', 'read_at' => now()->subHour()]);
        $this->notificationFor($user, ['type' => 'report_uploaded']);

        $this->login($user);

        $this->getJson('/api/v1/notifications?filter=unread')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/notifications?filter=read')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications?category=deadline')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications?category=system')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications?type=task_approved')->assertJsonCount(1, 'data');
    }
}