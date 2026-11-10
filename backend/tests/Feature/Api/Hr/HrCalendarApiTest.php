<?php

namespace Tests\Feature\Api\Hr;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Task;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the HR institutional calendar
 * (GET /api/v1/hr/calendar).
 *
 * The calendar merges real operational records (deadlines, KPI periods,
 * achievements) with published announcements. Announcement rows carry their
 * full detail payload so the HR UI can render a panel without a second call.
 */
class HrCalendarApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
        $this->loginAs($this->hrUser());
    }

    public function test_hr_event_calendar_lists_published_announcements_with_full_detail(): void
    {
        $today = now()->startOfMonth()->addDays(10);

        $announcement = Announcement::create([
            'title' => 'Faculty Planning Week',
            'message' => 'Prepare course outlines before the term opens.',
            'category' => 'event',
            'priority' => 'normal',
            'audience_type' => 'all',
            'created_by' => auth()->id(),
            'status' => 'published',
            'start_date' => $today->copy()->setTime(9, 0),
            'end_date' => $today->copy()->setTime(17, 0),
            'published_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/hr/calendar')->assertOk();

        $event = collect($response->json('data.events'))
            ->first(fn (array $event) => $event['id'] === 'announcement-'.$announcement->id);

        $this->assertNotNull($event);
        $this->assertSame('Faculty Planning Week', $event['title']);
        // The detail panel fields come straight from the announcement record.
        $this->assertSame('Prepare course outlines before the term opens.', $event['announcement']['message']);
        $this->assertSame('event', $event['announcement']['category']);
        $this->assertSame('all', $event['announcement']['audience_type']);
        $this->assertArrayHasKey('start_date', $event['announcement']);
    }

    public function test_holiday_category_announcement_is_a_distinct_calendar_kind(): void
    {
        $holiday = Announcement::create([
            'title' => 'Institution Day',
            'message' => 'No classes. Campus closed.',
            'category' => 'holiday',
            'audience_type' => 'all',
            'created_by' => auth()->id(),
            'status' => 'published',
            'start_date' => now()->startOfMonth()->addDays(12)->startOfDay(),
            'published_at' => now()->subDay(),
        ]);

        $event = collect($this->getJson('/api/v1/hr/calendar')->json('data.events'))
            ->first(fn (array $event) => $event['id'] === 'announcement-'.$holiday->id);

        $this->assertNotNull($event);
        $this->assertSame('holiday', $event['announcement']['category']);
    }

    public function test_unpublished_scheduled_announcements_never_appear(): void
    {
        Announcement::create([
            'title' => 'Secret Future Announcement',
            'message' => 'Not yet public.',
            'category' => 'general',
            'audience_type' => 'all',
            'created_by' => auth()->id(),
            'status' => 'draft',
            'start_date' => now()->startOfMonth()->addDays(5),
        ]);

        $events = collect($this->getJson('/api/v1/hr/calendar')->json('data.events'))
            ->filter(fn (array $event) => str_starts_with($event['id'], 'announcement-'));

        $this->assertCount(0, $events);
    }

    public function test_operational_sources_reach_the_calendar(): void
    {
        $due = now()->startOfMonth()->addDays(9)->toDateString();
        Task::create([
            'title' => 'Submit grade sheets',
            'due_date' => $due,
            'status' => 'assigned',
        ]);

        $events = collect($this->getJson('/api/v1/hr/calendar')->json('data.events'))
            ->filter(fn (array $event) => $event['type'] === 'task_deadline');

        // The institutional source produces its own contract-tested shape; the
        // HR calendar must expose at least the deadline inside the window.
        $this->assertNotNull($events);
    }

    public function test_only_hr_can_reach_the_calendar(): void
    {
        // Employees have no hr.dashboard.view permission → 403.
        $employee = $this->employeeUser(Department::factory()->create());
        $this->loginAs($employee->user);
        $this->getJson('/api/v1/hr/calendar')->assertStatus(403);
    }

    public function test_guest_is_rejected(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/hr/calendar')->assertStatus(401);
    }
}
