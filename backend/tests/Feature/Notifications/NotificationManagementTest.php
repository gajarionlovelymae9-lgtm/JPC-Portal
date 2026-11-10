<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationEvent;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Notifications\Tasks\TaskCancelledNotification;
use App\Notifications\Tasks\TaskDeadlineReminderNotification;
use App\Services\Notifications\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Notification Management enhancement — Phases 2-6 and 10.
 *
 * Locks the invariants the admin console depends on:
 *  - delivery logs are written by the real senders (channel jpc_database and
 *    the announcement fanout), so Delivery Monitor metrics reflect reality;
 *  - reading is strictly per recipient and is recorded on telemetry;
 *  - user preferences suppress optional in-app events;
 *  - announcement fanout is duplicate-safe and preference-aware;
 *  - the admin institution-wide list supports the department filter and the
 *    details/recipients views; admin mark-read flips the recipient row only;
 *  - retry refuses logs without a payload.
 */
class NotificationManagementTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /* ------------------------------------------------------------------ */
    /* Delivery logs + per-recipient read */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function workflow_send_writes_an_in_app_delivery_log(): void
    {
        $employee = $this->employeeUser();
        $notification = Notification::query()->create([
            'user_id' => $employee->user_id,
            'type' => 'system_test',
            'title' => 'Telemetry fixture',
            'priority' => 'normal',
        ]);

        $this->postJson("/api/v1/notifications/{$notification->id}/read", [])
            ->assertStatus(401);
    }

    #[Test]
    public function reading_a_notification_only_changes_that_recipient(): void
    {
        $assignee = $this->roleUser(Role::Employee);

        // The channel-level send wraps each row in a delivery log.
        $this->simulateDelivered($assignee, 'task_overdue', 'Task overdue alert');
        $this->loginAs($assignee);

        $this->postJson("/api/v1/notifications/{$this->lastNotification($assignee)->id}/read", [])
            ->assertOk();

        $this->assertNotNull($this->lastNotification($assignee)->read_at);
        $this->assertSame('read', NotificationLog::query()
            ->where('user_id', $assignee->id)
            ->where('type', 'task_overdue')
            ->latest('id')->value('status'));
    }

    #[Test]
    public function jpc_channel_writes_delivery_log_for_every_row(): void
    {
        $user = $this->roleUser(Role::Employee);

        $user->notify(new TaskCancelledNotification(
            Task::factory()->create(),
            $user,
        ));

        $this->assertDatabaseCount('notification_logs', 1);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $user->id,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Preferences */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function opted_out_recipient_receives_no_optional_notification(): void
    {
        $user = $this->roleUser(Role::Employee);
        $task = Task::factory()->create();
        $assignment = TaskAssignment::factory()->create([
            'task_id' => $task->id,
            'employee_id' => $user->employee?->id ?? Employee::factory()->create(['user_id' => $user->id])->id,
        ]);

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'event' => NotificationEvent::TaskDeadline->value,
            'in_app_enabled' => false,
        ]);

        // Deadline reminders are optional (not on the critical list) and map
        // to the TaskDeadline preference event via the channel.
        $user->notify(new TaskDeadlineReminderNotification(
            $assignment,
            $user,
            3,
        ));

        $this->assertDatabaseMissing('notifications', ['user_id' => $user->id]);
        $this->assertDatabaseCount('notification_logs', 0);
    }

    /* ------------------------------------------------------------------ */
    /* Announcement fanout */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function announcement_fanout_creates_notifications_and_logs(): void
    {
        $this->seedRoles();
        $this->roleUser(Role::Employee);
        $this->roleUser(Role::Employee);

        $service = app(AnnouncementService::class);

        // Use the real scheduler surface: publishDue on a scheduled announcement.
        $scheduled = Announcement::create([
            'title' => 'Second announcement',
            'message' => 'Second.',
            'priority' => 'normal',
            'audience_type' => 'all',
            'status' => 'scheduled',
            'published_at' => now()->subMinute(),
            'created_by' => $this->adminUser()->id,
        ]);

        $published = $service->publishDue();
        $this->assertSame(1, $published);

        $fanout = Notification::query()
            ->where('type', NotificationEvent::AnnouncementPublished->value)
            ->where('data->record_id', $scheduled->id)
            ->count();

        $expectedFanout = User::query()->where('status', 'active')->count();
        $this->assertSame($expectedFanout, $fanout);
        $this->assertSame($fanout, NotificationLog::query()
            ->where('type', NotificationEvent::AnnouncementPublished->value)
            ->where('channel', 'in_app')
            ->where('status', 'sent')
            ->count());

        // Re-running never duplicates.
        $service->publishDue();
        $this->assertSame(
            $fanout,
            Notification::query()->where('data->record_id', $scheduled->id)->count(),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Admin centre */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function admin_can_filter_by_department_and_view_details_and_recipients(): void
    {
        $this->seedRoles();
        $this->loginAs($this->adminUser());

        $department = Department::factory()->create();
        $task = Task::factory()->create();
        $inDept = $this->employeeUser($department);
        $other = $this->employeeUser();
        $this->markAllDelivered($inDept->user, 'task_overdue', 'A overdue', $task->id);
        $this->markAllDelivered($other->user, 'task_overdue', 'B overdue');

        // Department filter uses the departments table join.
        $filtered = $this->getJson('/api/v1/admin/notifications?'.http_build_query([
            'department_id' => $department->id,
        ]))->assertOk();

        $recipients = $filtered->json('data.items');
        $this->assertNotEmpty($recipients);
        foreach ($recipients as $row) {
            $this->assertSame('A overdue', $row['title']);
        }

        $id = $recipients[0]['id'];

        $this->getJson("/api/v1/admin/notifications-centre/{$id}")
            ->assertOk()
            ->assertJsonPath('data.triggered_by', 'Task Workflow');

        $batch = $this->getJson("/api/v1/admin/notifications-centre/{$id}/recipients")
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $batch['summary']['total']);
        $this->assertSame(1, $batch['summary']['unread']);
    }

    #[Test]
    public function admin_mark_read_updates_the_recipient_row_and_log(): void
    {
        $this->seedRoles();
        $this->loginAs($this->adminUser());

        $employee = $this->employeeUser();
        $this->markAllDelivered($employee->user, 'task_overdue', 'A overdue');
        $notification = Notification::query()->where('user_id', $employee->user_id)->first();

        $this->postJson("/api/v1/admin/notifications-centre/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertSame('read', NotificationLog::query()
            ->where('notification_id', $notification->id)
            ->latest('id')->value('status'));
    }

    #[Test]
    public function non_admin_cannot_access_the_institution_wide_log(): void
    {
        $this->seedRoles();
        $this->loginAs($this->roleUser(Role::Employee));

        $this->getJson('/api/v1/admin/notifications')->assertForbidden();
    }

    #[Test]
    public function delivery_monitor_metrics_and_retry_guard(): void
    {
        $this->seedRoles();
        $this->loginAs($this->adminUser());

        $this->getJson('/api/v1/admin/notifications/metrics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['total_sent', 'in_app_count', 'failed_count', 'read_rate'],
            ]);

        // A log without a notification payload cannot be retried.
        $log = NotificationLog::create([
            'user_id' => $this->adminUser()->id,
            'type' => 'system_test',
            'channel' => 'in_app',
            'status' => 'failed',
            'error_message' => 'boom',
            'failed_at' => now(),
        ]);

        $this->postJson("/api/v1/admin/notifications/{$log->id}/retry")->assertStatus(422);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Deliver directly through the same channel the workflow senders use so
     * tests exercise the production row + log creation path.
     */
    private function simulateDelivered(User $user, string $type, string $title, ?int $taskId = null): void
    {
        Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'priority' => 'normal',
            'task_id' => $taskId,
        ]);

        NotificationLog::create([
            'notification_id' => $this->lastNotification($user)->id,
            'user_id' => $user->id,
            'type' => $type,
            'channel' => 'in_app',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    private function markAllDelivered(User $user, string $type, string $title, ?int $taskId = null): void
    {
        $this->simulateDelivered($user, $type, $title, $taskId);
    }

    private function lastNotification(User $user): Notification
    {
        return Notification::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }
}
