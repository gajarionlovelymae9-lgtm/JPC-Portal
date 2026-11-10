<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\NotificationEvent;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the Admin Announcements lifecycle (create, edit, publish,
 * schedule, archive) and its calendar integration:
 *  1. the admin announcement surface stays admin-only;
 *  2. the category column validates against the documented list;
 *  3. a scheduled announcement is invisible until its publication time;
 *  4. archive preserves the row (history) while removing visibility;
 *  5. the scheduled publisher command promotes due rows and notifies;
 *  6. the admin calendar lists published announcement events only.
 */
class AdminAnnouncementLifecycleApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private User $admin;

    private Department $department;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
        $this->admin = $this->adminUser();
        $this->department = Department::factory()->create();
        $this->employee = $this->employeeUser($this->department);
        $this->loginAs($this->admin);
    }

    /* ------------------------------------------------------------------ */
    /* Authorization */
    /* ------------------------------------------------------------------ */

    public function test_guest_cannot_reach_admin_announcement_endpoints(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/admin/announcements')->assertStatus(401);
        $this->postJson('/api/v1/admin/announcements', [])->assertStatus(401);
        $this->postJson('/api/v1/admin/announcements/1/schedule', [
            'published_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(401);
        $this->postJson('/api/v1/admin/announcements/1/archive')->assertStatus(401);
    }

    public function test_employee_role_cannot_manage_announcements(): void
    {
        // A real row so implicit binding resolves and the role guard is what
        // rejects the request (binding otherwise short-circuits with a 404).
        $announcement = $this->createAnnouncement();

        $employeeUser = $this->roleUser(Role::Employee);
        $this->loginAs($employeeUser);

        $this->getJson('/api/v1/admin/announcements')->assertStatus(403);
        $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Sneaky',
            'message' => 'Should not be created.',
        ])->assertStatus(403);
        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/archive")->assertStatus(403);
    }

    /* ------------------------------------------------------------------ */
    /* Category */
    /* ------------------------------------------------------------------ */

    public function test_admin_can_create_an_announcement_with_a_category(): void
    {
        $response = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Midterm submission window',
            'message' => 'Midterm submissions open next Monday.',
            'category' => 'academic',
            'priority' => 'high',
            'audience_type' => 'all',
        ])->assertStatus(201);

        $this->assertSame('academic', $response->json('data.category'));
        $this->assertDatabaseHas('announcements', [
            'title' => 'Midterm submission window',
            'category' => 'academic',
            'status' => 'draft',
        ]);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Bad category',
            'message' => 'Body',
            'category' => 'not-a-category',
        ])->assertStatus(422);
    }

    public function test_category_filter_narrows_the_admin_list(): void
    {
        $this->createAnnouncement(['title' => 'Academic notice', 'category' => 'academic']);
        $this->createAnnouncement(['title' => 'Policy notice', 'category' => 'policy']);

        $response = $this->getJson('/api/v1/admin/announcements?category=policy')->assertOk();

        $rows = collect($response->json('data.data') ?? $response->json('data'));
        $this->assertSame(['Policy notice'], $rows->pluck('title')->all());
    }

    /* ------------------------------------------------------------------ */
    /* Scheduling */
    /* ------------------------------------------------------------------ */

    public function test_draft_can_be_scheduled_and_stays_hidden_until_published(): void
    {
        $announcement = $this->createAnnouncement(['title' => 'Scheduled notice']);

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/schedule", [
            'published_at' => now()->addHours(2)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->assertNotNull($announcement->fresh()->published_at);
        $this->assertSame('scheduled', $announcement->fresh()->status);

        // The active feed must not expose it before its publication time.
        $this->loginAs($this->employee->user);
        $active = $this->getJson('/api/v1/announcements/active')->assertOk();
        $titles = collect($active->json('data'))->pluck('title');
        $this->assertFalse($titles->contains('Scheduled notice'));
    }

    public function test_scheduling_in_the_past_is_rejected(): void
    {
        $announcement = $this->createAnnouncement();

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/schedule", [
            'published_at' => now()->subHour()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_unschedule_returns_the_announcement_to_draft(): void
    {
        $announcement = $this->createAnnouncement();

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/schedule", [
            'published_at' => now()->addDay()->toIso8601String(),
        ])->assertOk();

        $this->deleteJson("/api/v1/admin/announcements/{$announcement->id}/schedule")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertNull($announcement->fresh()->published_at);
    }

    /* ------------------------------------------------------------------ */
    /* Archive */
    /* ------------------------------------------------------------------ */

    public function test_published_announcement_can_be_archived_and_history_is_preserved(): void
    {
        $announcement = $this->createAnnouncement([
            'title' => 'Archived notice',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        // The row survives as history and still appears in the admin list...
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'status' => 'expired',
        ]);
        $list = $this->getJson('/api/v1/admin/announcements?status=expired')->assertOk();
        $titles = collect($list->json('data.data') ?? $list->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Archived notice'));

        // ...but it is no longer visible in the active feed.
        $this->loginAs($this->employee->user);
        $active = $this->getJson('/api/v1/announcements/active')->assertOk();
        $activeTitles = collect($active->json('data'))->pluck('title');
        $this->assertFalse($activeTitles->contains('Archived notice'));
    }

    public function test_archiving_a_draft_is_rejected(): void
    {
        $announcement = $this->createAnnouncement(['status' => 'draft']);

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/archive")
            ->assertStatus(422);
    }

    /* ------------------------------------------------------------------ */
    /* Scheduled publisher */
    /* ------------------------------------------------------------------ */

    public function test_publish_due_command_promotes_due_announcements_and_notifies(): void
    {
        $due = $this->createAnnouncement([
            'title' => 'Due announcement',
            'status' => 'scheduled',
            'published_at' => now()->subMinute(),
        ]);
        $notDue = $this->createAnnouncement([
            'title' => 'Future announcement',
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $this->artisan('announcements:publish-due')->assertSuccessful();

        $this->assertSame('published', $due->fresh()->status);
        $this->assertSame('scheduled', $notDue->fresh()->status);

        $this->assertDatabaseHas('notifications', [
            'type' => NotificationEvent::AnnouncementPublished->value,
            'title' => '📢 Due announcement',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Publication-time visibility */
    /* ------------------------------------------------------------------ */

    public function test_published_announcement_is_visible_from_publication_time_not_event_start(): void
    {
        $this->createAnnouncement([
            'title' => 'Future event notice',
            'status' => 'published',
            'published_at' => now(),
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(8),
        ]);

        $this->loginAs($this->employee->user);
        $active = $this->getJson('/api/v1/announcements/active')->assertOk();
        $titles = collect($active->json('data'))->pluck('title');

        $this->assertTrue(
            $titles->contains('Future event notice'),
            'A published announcement must be visible once published, not only after its event window opens.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Calendar integration */
    /* ------------------------------------------------------------------ */

    public function test_calendar_lists_published_announcement_events_and_hides_scheduled_ones(): void
    {
        $published = $this->createAnnouncement([
            'title' => 'Founders Week',
            'category' => 'event',
            'status' => 'published',
            'published_at' => now(),
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(3),
        ]);
        $scheduled = $this->createAnnouncement([
            'title' => 'Secret scheduled event',
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
            'start_date' => now()->addDays(2),
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDays(10)->toDateString();

        $response = $this->getJson("/api/v1/admin/calendar?from={$from}&to={$to}")->assertOk();

        $events = collect($response->json('data.events') ?? $response->json('data'));
        $announcementEvents = $events->where('type', 'announcement');

        $this->assertTrue(
            $announcementEvents->contains('id', 'announcement-'.$published->id),
            'A published announcement with an event window must appear on the calendar.',
        );
        $this->assertFalse(
            $announcementEvents->contains('id', 'announcement-'.$scheduled->id),
            'A scheduled announcement must not appear on the calendar before publication.',
        );

        $sources = collect($response->json('data.sources') ?? []);
        $source = $sources->firstWhere('type', 'announcement');
        $this->assertNotNull($source, 'The calendar must disclose the announcement source.');
        $this->assertTrue($source['supported']);
    }

    /* ------------------------------------------------------------------ */

    private function createAnnouncement(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'Sample announcement',
            'message' => 'Body text used by the announcement lifecycle tests.',
            'priority' => 'normal',
            'category' => 'general',
            'audience_type' => 'all',
            'audience_id' => null,
            'status' => 'draft',
            'start_date' => null,
            'end_date' => null,
            'created_by' => $this->admin->id,
            'published_at' => null,
        ], $overrides));
    }
}
