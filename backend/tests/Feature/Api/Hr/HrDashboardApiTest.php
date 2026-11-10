<?php

namespace Tests\Feature\Api\Hr;

use App\Enums\ReportStatus;
use App\Models\AcademicYear;
use App\Models\Badge;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\MonthlyAchievement;
use App\Models\Position;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Hr\HrDashboardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the HR portal surface.
 *
 * These assert the three things that are easy to break silently:
 *
 *  1. Authorization — every HR route is behind `hr.dashboard.view`, so
 *     unauthenticated callers get 401 and other roles get 403. HR is not a
 *     proxy for Admin: a plain HR user must not gain Admin settings routes.
 *  2. List shape — every list endpoint returns `summary` + `items` + `meta`
 *     rather than a bare array, so one table component can serve all of them.
 *  3. Honesty — KPI rates are `null` (not 0) when their denominator is zero,
 *     and achievements never attach a badge to an employee who has no grant.
 */
class HrDashboardApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
        $this->hr = $this->hrUser();
        $this->loginAs($this->hr);
    }

    /* ------------------------------------------------------------------ */
    /* Authorization */
    /* ------------------------------------------------------------------ */

    public static function routeProvider(): array
    {
        return [
            'dashboard' => ['/api/v1/hr/dashboard'],
            'options' => ['/api/v1/hr/dashboard/options'],
            'employees' => ['/api/v1/hr/employees'],
            'monitoring' => ['/api/v1/hr/monitoring'],
            'reports' => ['/api/v1/hr/reports'],
            'achievements' => ['/api/v1/hr/achievements'],
        ];
    }

    /**
     * The read-only monitoring surface.
     *
     * `employees` is deliberately absent: employee *management* (create/edit/
     * account status) is now registered under the same prefix, so asserting
     * GET-only on that URI would fail by design. `HrEmployeeManagementApiTest`
     * pins the exact method list instead, which is the stronger assertion.
     */
    public static function readOnlyRouteProvider(): array
    {
        return [
            'dashboard' => ['/api/v1/hr/dashboard'],
            'options' => ['/api/v1/hr/dashboard/options'],
            'monitoring' => ['/api/v1/hr/monitoring'],
            'reports' => ['/api/v1/hr/reports'],
            'achievements' => ['/api/v1/hr/achievements'],
        ];
    }

    /**
     * @dataProvider routeProvider
     */
    public function test_hr_routes_require_authentication(string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($uri)->assertUnauthorized();
    }

    /**
     * @dataProvider routeProvider
     */
    public function test_hr_routes_reject_other_roles(string $uri): void
    {
        $this->app['auth']->forgetGuards();
        $this->loginAs($this->employeeUser()->user);

        $this->getJson($uri)->assertForbidden();
    }

    /**
     * @dataProvider readOnlyRouteProvider
     */
    public function test_hr_routes_are_read_only(string $uri): void
    {
        // Nothing in the HR *monitoring* group may mutate state; if a future
        // edit adds a POST here it should be a deliberate, separately tested
        // decision. Employee management is the one such decision, and it is
        // pinned by its own test file.
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertSame(
            ['GET', 'HEAD'],
            $this->methodsForUri($uri),
            "{$uri} must stay a read-only GET endpoint."
        );
    }

    /**
     * The exact HTTP methods registered for a URI.
     *
     * @return array<int, string>
     */
    private function methodsForUri(string $uri): array
    {
        return collect($this->app['router']->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === ltrim($uri, '/'))
            ->flatMap(fn ($route) => $route->methods())
            ->unique()
            ->values()
            ->sort()
            ->values()
            ->all();
    }

    public function test_hr_cannot_reach_admin_only_surfaces(): void
    {
        $this->app['auth']->forgetGuards();
        $this->loginAs($this->hr);

        // HR is institution-wide but NOT elevated: user management and the
        // settings/RBAC surface stay behind `role:admin` and
        // `permission:settings.manage`, neither of which the HR role holds.
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/admin/rbac')->assertForbidden();
    }

    public function test_hr_may_read_the_shared_read_only_dashboard_endpoints(): void
    {
        $this->app['auth']->forgetGuards();
        $this->loginAs($this->hr);

        // Deliberate, not an oversight: the read-only `/admin/dashboard/*`
        // aggregates are shared with HR and department heads, which is why the
        // HR dashboard had a duplicate of its own. The HR page uses
        // `/api/v1/hr/*` so the two contracts cannot drift.
        $this->getJson('/api/v1/admin/dashboard/departments')->assertOk();
    }

    public function test_admin_may_also_open_the_hr_dashboard(): void
    {
        $this->app['auth']->forgetGuards();
        $this->loginAs($this->adminUser());

        $this->getJson('/api/v1/hr/dashboard')->assertOk();
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard */
    /* ------------------------------------------------------------------ */

    public function test_dashboard_returns_real_counts_rates_and_trends(): void
    {
        $department = Department::factory()->create(['name' => 'College of Education']);
        $employee = Employee::factory()->active()->create(['department_id' => $department->id]);

        $completed = TaskAssignment::factory()->completed()->create(['employee_id' => $employee->id]);
        $open = TaskAssignment::factory()->inProgress()->create(['employee_id' => $employee->id]);
        $late = TaskAssignment::factory()->overdue()->create(['employee_id' => $employee->id]);

        $task = Task::find($completed->task_id);
        Report::factory()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'status' => ReportStatus::Approved,
            'deadline' => now()->addDays(5)->toDateString(),
            'is_late' => false,
        ]);
        Report::factory()->late()->create([
            'task_id' => $open->task_id,
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'deadline' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/hr/dashboard')->assertOk();
        $data = $response->json('data');

        $this->assertSame(1, $data['employees']['total']);
        $this->assertSame(1, $data['employees']['active']);
        $this->assertSame(0, $data['employees']['inactive']);

        $this->assertSame(3, $data['tasks']['total']);
        $this->assertSame(1, $data['tasks']['completed']);
        $this->assertSame(2, $data['tasks']['pending']);
        $this->assertSame(1, $data['tasks']['overdue'], 'the overdue() assignment must be counted.');

        $this->assertSame(2, $data['reports']['total']);
        $this->assertSame(2, $data['reports']['submitted']);
        $this->assertSame(1, $data['reports']['approved']);
        $this->assertSame(1, $data['reports']['late']);

        $this->assertSame(33.33, $data['kpi']['completion_rate']);
        $this->assertSame(50, $data['kpi']['on_time_rate']);
        $this->assertSame(50, $data['kpi']['approval_rate']);

        // The trend series must cover a contiguous run of months so the charts
        // never render a broken line.
        $this->assertCount(6, $data['trends']['reports']);
        $this->assertCount(6, $data['trends']['tasks']);

        $periods = array_column($data['trends']['reports'], 'period');
        $this->assertSame($periods, array_values(array_unique($periods)));
        $this->assertSame(
            $data['trends']['reports'][0]['label'],
            now()->subMonths(5)->format('M Y'),
        );
        $this->assertSame(
            $data['trends']['reports'][5]['label'],
            now()->format('M Y'),
        );

        $this->assertNotNull($late->id);
    }

    public function test_dashboard_rates_are_null_when_there_is_nothing_to_measure(): void
    {
        // The core honesty guarantee: an empty database must not report 0%.
        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        $this->assertSame(0, $data['tasks']['total']);
        $this->assertSame(0, $data['reports']['total']);
        $this->assertNull($data['kpi']['completion_rate']);
        $this->assertNull($data['kpi']['on_time_rate']);
        $this->assertNull($data['kpi']['approval_rate']);
    }

    public function test_dashboard_excludes_soft_deleted_employees(): void
    {
        $employee = Employee::factory()->active()->create();
        $employee->delete();

        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        $this->assertSame(0, $data['employees']['total']);
    }

    /* ------------------------------------------------------------------ */
    /* The four headline cards */
    /* ------------------------------------------------------------------ */

    public function test_dashboard_counts_reports_submitted_in_the_current_month(): void
    {
        $employee = Employee::factory()->active()->create();

        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->startOfMonth()->subDay();

        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'submission_date' => $thisMonth->toDateString(),
            'submitted_at' => $thisMonth,
        ]);
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'submission_date' => $lastMonth->toDateString(),
            'submitted_at' => $lastMonth,
        ]);
        // A draft was never handed in, so it is not a submission.
        Report::factory()->draft()->create([
            'employee_id' => $employee->id,
            'submission_date' => null,
            'submitted_at' => null,
        ]);

        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        $this->assertSame(1, $data['reports']['submitted_this_month']);
        $this->assertSame(2, $data['reports']['submitted'], 'the all-time counter is unchanged.');
        $this->assertSame(now()->format('Y-m'), $data['reports']['month']['period']);
        $this->assertSame(now()->format('F Y'), $data['reports']['month']['label']);
    }

    public function test_dashboard_pending_reports_follow_the_report_status_definitions(): void
    {
        $employee = Employee::factory()->active()->create();

        // One report per status, so the card has to count statuses rather than
        // rows, and a report may only be counted once.
        Report::factory()->draft()->create(['employee_id' => $employee->id]);
        Report::factory()->revisionRequired()->create(['employee_id' => $employee->id]);
        Report::factory()->create(['employee_id' => $employee->id, 'status' => ReportStatus::Submitted]);
        Report::factory()->create(['employee_id' => $employee->id, 'status' => ReportStatus::UnderReview]);
        Report::factory()->approved()->create(['employee_id' => $employee->id]);
        Report::factory()->rejected()->create(['employee_id' => $employee->id]);

        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        // draft + revision_required + submitted + under_review
        $this->assertSame(4, $data['reports']['awaiting_action']);
        $this->assertSame(2, $data['reports']['awaiting_submission']);
        $this->assertSame(2, $data['reports']['pending']);
    }

    public function test_dashboard_pending_reports_never_count_a_report_twice(): void
    {
        $employee = Employee::factory()->active()->create();

        // Two reports in the same status: the card must say 2, not 4.
        Report::factory()->create(['employee_id' => $employee->id, 'status' => ReportStatus::Submitted]);
        Report::factory()->create(['employee_id' => $employee->id, 'status' => ReportStatus::Submitted]);

        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        $this->assertSame(2, $data['reports']['awaiting_action']);
        $this->assertSame(2, $data['reports']['pending']);
        $this->assertSame(2, $data['reports']['total']);
    }

    public function test_dashboard_late_reports_count_submitted_late_and_outstanding_reports(): void
    {
        $employee = Employee::factory()->active()->create();

        // Handed in three days after its deadline.
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'deadline' => now()->subDays(5)->toDateString(),
            'submission_date' => now()->subDays(3)->toDateString(),
            'submitted_at' => now()->subDays(3),
            'is_late' => false,
        ]);

        // Never handed in and the deadline has already passed.
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Draft,
            'deadline' => now()->subDays(2)->toDateString(),
            'submission_date' => null,
            'submitted_at' => null,
            'is_late' => false,
        ]);

        // Handed in before the deadline.
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'deadline' => now()->subDays(5)->toDateString(),
            'submission_date' => now()->subDays(9)->toDateString(),
            'submitted_at' => now()->subDays(9),
            'is_late' => false,
        ]);

        // Approved before the deadline: a closed report is never outstanding.
        Report::factory()->approved()->create([
            'employee_id' => $employee->id,
            'deadline' => now()->subDays(5)->toDateString(),
            'submission_date' => now()->subDays(9)->toDateString(),
            'submitted_at' => now()->subDays(9),
            'is_late' => false,
        ]);

        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        $this->assertSame(2, $data['reports']['late']);
        $this->assertSame(1, $data['reports']['late_submitted']);
        $this->assertSame(1, $data['reports']['late_outstanding']);

        // The on-time rate is about reports that were handed in, so the
        // outstanding draft must not be subtracted from it: 3 handed in, 1 of
        // them late.
        $this->assertSame(3, $data['reports']['submitted']);
        $this->assertSame(66.67, $data['kpi']['on_time_rate']);
    }

    public function test_the_late_card_agrees_with_the_late_reports_table(): void
    {
        $employee = Employee::factory()->active()->create();

        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'deadline' => now()->subDays(5)->toDateString(),
            'submitted_at' => now()->subDays(3),
            'is_late' => false,
        ]);
        Report::factory()->late()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
        ]);
        Report::factory()->approved()->create(['employee_id' => $employee->id]);

        $dashboard = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');
        $rows = $this->getJson('/api/v1/hr/reports')->assertOk()->json('data.items');
        $filtered = $this->getJson('/api/v1/hr/reports?late=1')->assertOk()->json('data');

        $lateRows = count(array_filter($rows, fn (array $row) => $row['is_late']));

        $this->assertSame($lateRows, $dashboard['reports']['late']);
        $this->assertSame($dashboard['reports']['late'], $filtered['meta']['total']);
        $this->assertSame($dashboard['reports']['late'], $filtered['summary']['late']);
    }

    public function test_dashboard_task_completion_rate_covers_the_current_month(): void
    {
        $employee = Employee::factory()->active()->create();

        TaskAssignment::factory()->completed()->create(['employee_id' => $employee->id]);
        TaskAssignment::factory()->inProgress()->create(['employee_id' => $employee->id]);
        // Cancelled work is not applicable, so it must not drag the rate down.
        TaskAssignment::factory()->create(['employee_id' => $employee->id, 'status' => 'cancelled']);
        // Last month: outside the window the card describes.
        TaskAssignment::factory()->completed()->create([
            'employee_id' => $employee->id,
            'created_at' => now()->startOfMonth()->subDay(),
        ]);

        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        $this->assertSame(now()->format('Y-m'), $data['tasks']['month']['period']);
        $this->assertSame(now()->format('F Y'), $data['tasks']['month']['label']);
        $this->assertSame(2, $data['tasks']['month']['total']);
        $this->assertSame(1, $data['tasks']['month']['completed']);
        $this->assertSame(50, $data['tasks']['month']['completion_rate']);
    }

    public function test_dashboard_task_completion_rate_is_null_when_nothing_was_assigned_this_month(): void
    {
        TaskAssignment::factory()->completed()->create([
            'created_at' => now()->startOfMonth()->subDay(),
        ]);

        $data = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');

        $this->assertSame(0, $data['tasks']['month']['total']);
        $this->assertNull($data['tasks']['month']['completion_rate'], 'an empty month is unmeasured, not 0%.');
    }

    public function test_options_expose_every_dashboard_filter_source(): void
    {
        Department::factory()->create(['name' => 'College of Education']);
        Department::factory()->create(['name' => 'College of Engineering']);
        Position::factory()->create(['name' => 'Faculty Member']);

        $data = $this->getJson('/api/v1/hr/dashboard/options')->assertOk()->json('data');

        $this->assertCount(2, $data['departments']);
        $this->assertSame(
            ['College of Education', 'College of Engineering'],
            array_column($data['departments'], 'name'),
            'departments must be name-sorted for a stable dropdown.'
        );
        $this->assertCount(12, $data['months']);
        $this->assertSame('January', $data['months'][0]['label']);
        $this->assertSame('December', $data['months'][11]['label']);
        $this->assertNotEmpty($data['positions']);
        $this->assertNotEmpty($data['report_statuses']);
        $this->assertContains(
            ['value' => ReportStatus::RevisionRequired->value, 'label' => 'Revision Required'],
            $data['report_statuses'],
        );
        $this->assertNotEmpty($data['years']);
    }

    /* ------------------------------------------------------------------ */
    /* Employees */
    /* ------------------------------------------------------------------ */

    public function test_employees_are_paginated_with_summary_items_and_meta(): void
    {
        $education = Department::factory()->create();
        Employee::factory()->count(3)->create(['department_id' => $education->id]);
        Employee::factory()->count(2)->create();

        $data = $this->getJson('/api/v1/hr/employees?per_page=2')->assertOk()->json('data');

        $this->assertCount(2, $data['items']);
        $this->assertSame(2, $data['meta']['per_page']);
        $this->assertSame(1, $data['meta']['current_page']);
        $this->assertSame(3, $data['meta']['last_page']);
        $this->assertSame(5, $data['meta']['total']);
        $this->assertSame(5, $data['summary']['total']);
    }

    public function test_employee_row_aggregates_are_real_and_null_when_unmeasured(): void
    {
        $employee = Employee::factory()->active()->create([
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Santos',
        ]);
        TaskAssignment::factory()->count(2)->completed()->create(['employee_id' => $employee->id]);
        TaskAssignment::factory()->overdue()->create(['employee_id' => $employee->id]);

        $item = $this->getJson('/api/v1/hr/employees')->assertOk()->json('data.items.0');

        $this->assertSame($employee->id, $item['employee_id']);
        $this->assertSame('Ana Santos', $item['name']);
        $this->assertSame(3, $item['assigned_tasks']);
        $this->assertSame(2, $item['completed_tasks']);
        $this->assertSame(1, $item['late_tasks']);
        $this->assertSame(66.67, $item['completion_rate']);
        $this->assertSame('needs_attention', $item['current_status']);

        // No reports at all -> the report rates must be null, not zero.
        $this->assertSame(0, $item['submitted_reports']);
        $this->assertNull($item['on_time_rate']);
        $this->assertNull($item['approval_rate']);
    }

    public function test_employee_row_reports_an_inactive_employee_as_inactive(): void
    {
        Employee::factory()->inactive()->create();

        $item = $this->getJson('/api/v1/hr/employees')->assertOk()->json('data.items.0');

        $this->assertSame('inactive', $item['current_status']);
    }

    public function test_employees_can_be_filtered_by_department_status_and_search(): void
    {
        $education = Department::factory()->create(['name' => 'College of Education']);
        $engineering = Department::factory()->create(['name' => 'College of Engineering']);

        Employee::factory()->active()->create([
            'department_id' => $education->id,
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Santos',
            'employee_number' => 'DEV-0001',
        ]);
        Employee::factory()->inactive()->create([
            'department_id' => $engineering->id,
            'first_name' => 'Ben',
            'middle_name' => null,
            'last_name' => 'Reyes',
            'employee_number' => 'DEV-0002',
        ]);

        $this->assertSame(1, $this->getJson('/api/v1/hr/employees?department_id='.$education->id)
            ->assertOk()->json('data.meta.total'));

        $this->assertSame(1, $this->getJson('/api/v1/hr/employees?status=inactive')
            ->assertOk()->json('data.meta.total'));

        $this->assertSame(1, $this->getJson('/api/v1/hr/employees?search=Santos')
            ->assertOk()->json('data.meta.total'));

        $this->assertSame(1, $this->getJson('/api/v1/hr/employees?search=DEV-0002')
            ->assertOk()->json('data.meta.total'));
    }

    public function test_employee_sort_is_restricted_to_known_columns(): void
    {
        Employee::factory()->create();

        $this->getJson('/api/v1/hr/employees?sort=completion_rate')->assertOk();
        $this->getJson('/api/v1/hr/employees?sort=name')->assertOk();

        // An unknown sort key must not reach the query builder.
        $this->getJson('/api/v1/hr/employees?sort='.urlencode('(select 1)'))->assertOk();
    }

    public function test_employees_rejects_an_unknown_department(): void
    {
        $this->getJson('/api/v1/hr/employees?department_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('department_id');
    }

    /* ------------------------------------------------------------------ */
    /* Task monitoring */
    /* ------------------------------------------------------------------ */

    public function test_monitoring_returns_task_progress_rows_and_summary(): void
    {
        $employee = Employee::factory()->active()->create([
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Santos',
        ]);
        $task = Task::factory()->create(['title' => 'Prepare Faculty Report']);

        TaskAssignment::factory()->inProgress()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);

        $data = $this->getJson('/api/v1/hr/monitoring')->assertOk()->json('data');

        $this->assertSame(1, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['pending']);
        $this->assertCount(1, $data['items']);

        $item = $data['items'][0];
        $this->assertSame('Prepare Faculty Report', $item['task_name']);
        $this->assertSame('Ana Santos', $item['employee_name']);
        $this->assertSame(50, $item['progress_percentage']);
        $this->assertFalse($item['is_overdue']);
    }

    public function test_monitoring_flags_an_overdue_assignment(): void
    {
        $assignment = TaskAssignment::factory()->overdue()->create();

        $item = $this->getJson('/api/v1/hr/monitoring')->assertOk()->json('data.items.0');

        $this->assertTrue($item['is_overdue']);
        $this->assertSame('overdue', $item['status']);
        $this->assertSame('in_progress', $item['raw_status']);

        $this->getJson('/api/v1/hr/monitoring?overdue=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->assertSame(1, $this->getJson('/api/v1/hr/monitoring?status=in_progress')
            ->assertOk()->json('data.meta.total'));

        $this->assertSame(0, $this->getJson('/api/v1/hr/monitoring?status=completed')
            ->assertOk()->json('data.meta.total'));

        $this->assertNotNull($assignment->id);
    }

    public function test_monitoring_falls_back_to_the_task_due_date(): void
    {
        // The assignment has no own due_date; the parent task's due_date is the
        // effective deadline (same rule the overdue scope uses).
        $task = Task::factory()->create(['due_date' => now()->addDays(10)]);

        $assignment = TaskAssignment::factory()->create([
            'task_id' => $task->id,
            'due_date' => null,
            'status' => 'assigned',
        ]);

        $item = $this->getJson('/api/v1/hr/monitoring')->assertOk()->json('data.items.0');

        $this->assertSame($assignment->id, $item['id']);
        $this->assertSame(now()->addDays(10)->toDateString(), $item['deadline']);
        $this->assertFalse($item['is_overdue']);
    }

    public function test_monitoring_counts_an_inherited_past_deadline_as_overdue(): void
    {
        // No assignment due_date, and the parent task is already past its own
        // deadline. The effective deadline is therefore in the past, so the row,
        // the filter, the panel summary, the KPI card and the per-employee
        // `late_tasks` count must ALL agree — a row showing a passed deadline
        // next to a non-overdue status would be a lie in the table.
        $task = Task::factory()->create(['due_date' => now()->subDays(3)]);

        $assignment = TaskAssignment::factory()->create([
            'task_id' => $task->id,
            'due_date' => null,
            'status' => 'in_progress',
        ]);

        $item = $this->getJson('/api/v1/hr/monitoring')->assertOk()->json('data.items.0');

        $this->assertSame($assignment->id, $item['id']);
        $this->assertSame(now()->subDays(3)->toDateString(), $item['deadline']);
        $this->assertTrue($item['is_overdue']);
        $this->assertSame('overdue', $item['status']);
        $this->assertSame('in_progress', $item['raw_status']);

        $this->getJson('/api/v1/hr/monitoring?overdue=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.summary.overdue', 1);

        $this->assertSame(1, $this->getJson('/api/v1/hr/dashboard')
            ->assertOk()->json('data.tasks.overdue'));

        $this->assertSame(1, $this->getJson('/api/v1/hr/employees')
            ->assertOk()->json('data.summary.with_overdue'));

        $employeeId = $assignment->employee_id;

        $items = $this->getJson('/api/v1/hr/employees')->assertOk()->json('data.items');
        $row = collect($items)->firstWhere('employee_id', $employeeId);

        $this->assertNotNull($row, 'The assignment owner must appear in the employee table.');
        $this->assertSame(1, $row['late_tasks']);
        $this->assertSame('needs_attention', $row['current_status']);
    }

    public function test_a_finished_assignment_inheriting_a_past_deadline_is_not_overdue(): void
    {
        // The deadline is in the past but the work is done, so counting it as
        // overdue would penalise an employee who already delivered.
        $task = Task::factory()->create(['due_date' => now()->subDays(3)]);

        $assignment = TaskAssignment::factory()->create([
            'task_id' => $task->id,
            'due_date' => null,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $item = $this->getJson('/api/v1/hr/monitoring')->assertOk()->json('data.items.0');

        $this->assertSame($assignment->id, $item['id']);
        $this->assertFalse($item['is_overdue']);
        $this->assertSame('completed', $item['status']);

        $this->getJson('/api/v1/hr/monitoring?overdue=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.summary.overdue', 0);
    }

    public function test_task_summary_counts_the_same_done_statuses_as_the_kpi_cards(): void
    {
        // `approved` is done work for Admin, so it must be done work here too,
        // and `not_started` is the column's DB default so it must be pending —
        // otherwise the panel and the card above it report different totals.
        TaskAssignment::factory()->create(['status' => 'approved']);
        TaskAssignment::factory()->create(['status' => 'not_started']);

        $dashboard = $this->getJson('/api/v1/hr/dashboard')->assertOk()->json('data');
        $monitoring = $this->getJson('/api/v1/hr/monitoring')->assertOk()->json('data');

        $this->assertSame($dashboard['tasks']['completed'], $monitoring['summary']['completed']);
        $this->assertSame($dashboard['tasks']['pending'], $monitoring['summary']['pending']);
        $this->assertSame(1, $monitoring['summary']['completed']);
        $this->assertSame(1, $monitoring['summary']['pending']);
        $this->assertSame(2, $monitoring['summary']['total']);
    }

    public function test_status_filters_reject_a_value_from_a_different_endpoint(): void
    {
        // `status` is a different column on each endpoint, so an unknown value
        // must be a 422 rather than a silently empty table that reads as
        // "no such data exists".
        $this->getJson('/api/v1/hr/reports?status='.ReportStatus::Approved->value)
            ->assertOk();

        $this->getJson('/api/v1/hr/reports?status=in_progress')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        // `approved` is valid on BOTH endpoints, so it must not be rejected.
        $this->getJson('/api/v1/hr/monitoring?status=approved')
            ->assertOk();

        // `revision_required` exists only on reports.
        $this->getJson('/api/v1/hr/monitoring?status='.ReportStatus::RevisionRequired->value)
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->getJson('/api/v1/hr/employees?status=active')
            ->assertOk();

        $this->getJson('/api/v1/hr/employees?status=in_progress')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_options_offer_a_filter_value_for_every_status_the_aggregates_count(): void
    {
        $data = $this->getJson('/api/v1/hr/dashboard/options')->assertOk()->json('data');
        $values = array_column($data['task_statuses'], 'value');

        $this->assertContains('not_started', $values, 'the column default must be filterable.');
        $this->assertContains('approved', $values, 'a status counted as done must be filterable.');

        // The dropdown must not offer a value the endpoint would reject.
        $this->assertSame([], array_diff($values, array_keys(HrDashboardService::TASK_STATUSES)));
    }

    public function test_monitoring_can_be_filtered_by_department_and_employee(): void
    {
        $education = Department::factory()->create();
        $engineering = Department::factory()->create();

        $ana = Employee::factory()->create(['department_id' => $education->id]);
        $ben = Employee::factory()->create(['department_id' => $engineering->id]);

        TaskAssignment::factory()->create(['employee_id' => $ana->id]);
        TaskAssignment::factory()->create(['employee_id' => $ben->id]);

        $this->assertSame(1, $this->getJson('/api/v1/hr/monitoring?department_id='.$education->id)
            ->assertOk()->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/hr/monitoring?employee_id='.$ana->id)
            ->assertOk()->json('data.meta.total'));
    }

    /* ------------------------------------------------------------------ */
    /* Report monitoring */
    /* ------------------------------------------------------------------ */

    public function test_reports_return_monitoring_rows_with_resolved_status_label(): void
    {
        $employee = Employee::factory()->active()->create();
        $task = Task::factory()->create(['title' => 'Prepare Faculty Report']);

        Report::factory()->approved()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'title' => 'Faculty Accomplishment Report',
            'deadline' => now()->addDays(3)->toDateString(),
        ]);

        $item = $this->getJson('/api/v1/hr/reports')->assertOk()->json('data.items.0');

        $this->assertSame('Faculty Accomplishment Report', $item['title']);
        $this->assertSame('Prepare Faculty Report', $item['task_name']);
        $this->assertSame(ReportStatus::Approved->value, $item['status']);
        $this->assertSame('Approved', $item['status_label']);
        $this->assertFalse($item['is_late']);
        $this->assertFalse($item['needs_revision']);
        $this->assertSame(1, $this->getJson('/api/v1/hr/reports')->json('data.summary.approved'));
    }

    public function test_reports_late_filter_returns_only_late_reports(): void
    {
        $employee = Employee::factory()->active()->create();

        Report::factory()->late()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
        ]);
        Report::factory()->approved()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Approved,
        ]);

        $this->assertSame(2, $this->getJson('/api/v1/hr/reports')->assertOk()->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/hr/reports?late=1')->assertOk()->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/hr/reports?late=1')
            ->assertOk()->json('data.summary.late'));
    }

    public function test_report_lateness_is_derived_from_deadline_when_the_flag_is_missing(): void
    {
        // `is_late` is only written on submission, so a report that is still in
        // review must still surface as late when its own dates prove it.
        $employee = Employee::factory()->active()->create();

        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'deadline' => now()->subDays(5)->toDateString(),
            'submitted_at' => now()->subDays(3),
            'is_late' => false,
        ]);

        $item = $this->getJson('/api/v1/hr/reports')->assertOk()->json('data.items.0');

        $this->assertTrue($item['is_late']);
        // Deadline 5 days ago, handed in 3 days ago => 2 days late.
        $this->assertSame(2, $item['days_late']);
    }

    public function test_report_uses_the_task_due_date_when_it_has_no_deadline(): void
    {
        $employee = Employee::factory()->active()->create();
        $task = Task::factory()->create(['due_date' => now()->addDays(6)]);

        Report::factory()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'deadline' => null,
            'submitted_at' => now(),
        ]);

        $item = $this->getJson('/api/v1/hr/reports')->assertOk()->json('data.items.0');

        $this->assertSame(now()->addDays(6)->toDateString(), $item['deadline']);
        $this->assertFalse($item['is_late']);
    }

    public function test_reports_mark_revision_required_for_follow_up(): void
    {
        $employee = Employee::factory()->active()->create();
        Report::factory()->revisionRequired()->create(['employee_id' => $employee->id]);

        $item = $this->getJson('/api/v1/hr/reports')->assertOk()->json('data.items.0');

        $this->assertTrue($item['needs_revision']);
        $this->assertSame('Revision Required', $item['status_label']);
        $this->assertSame(1, $this->getJson('/api/v1/hr/reports')->json('data.summary.revision_required'));
    }

    public function test_reports_can_be_filtered_by_status_search_and_employee(): void
    {
        $ana = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Santos']);
        $ben = Employee::factory()->create(['first_name' => 'Ben', 'last_name' => 'Reyes']);

        Report::factory()->approved()->create([
            'employee_id' => $ana->id,
            'title' => 'Faculty Report',
        ]);
        Report::factory()->rejected()->create([
            'employee_id' => $ben->id,
            'title' => 'Guidance Report',
        ]);

        $this->assertSame(1, $this->getJson('/api/v1/hr/reports?status=approved')
            ->assertOk()->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/hr/reports?search=Guidance')
            ->assertOk()->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/hr/reports?search=Santos')
            ->assertOk()->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/hr/reports?employee_id='.$ana->id)
            ->assertOk()->json('data.meta.total'));
    }

    public function test_reports_rejects_an_invalid_late_flag(): void
    {
        $this->getJson('/api/v1/hr/reports?late=yes')
            ->assertStatus(422)
            ->assertJsonValidationErrors('late');
    }

    /* ------------------------------------------------------------------ */
    /* Achievements */
    /* ------------------------------------------------------------------ */

    public function test_achievements_return_stored_rollups_with_real_badges(): void
    {
        $year = AcademicYear::factory()->create();
        $department = Department::factory()->create();
        $employee = Employee::factory()->active()->create([
            'department_id' => $department->id,
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Santos',
        ]);

        // The verdict fields are guarded on the model: only the achievement
        // scorer/seeders may write them, so the test does the same.
        MonthlyAchievement::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => $year->id,
            'month' => 9,
            'year' => 2026,
            'completion_rate' => 80,
            'on_time_rate' => 100,
            'approved_reports' => 4,
            'completed_tasks' => 12,
            'overall_score' => 91.5,
            'rank' => 1,
            'is_employee_of_month' => true,
        ]);

        $badge = Badge::create([
            'name' => 'Task Champion',
            'code' => 'TASK_CHAMPION',
            'icon' => 'target',
            'criteria_type' => 'completed_tasks',
            // `criteria_value` is an array-cast column: pass the array, not
            // pre-encoded JSON, or it gets double-encoded.
            'criteria_value' => ['period' => 'monthly', 'threshold' => 10],
            'is_active' => true,
        ]);

        EmployeeBadge::create([
            'employee_id' => $employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $year->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'awarded_at' => now(),
        ]);

        $item = $this->getJson('/api/v1/hr/achievements')->assertOk()->json('data.items.0');

        $this->assertSame('Ana Santos', $item['name']);
        $this->assertSame('2026-09', $item['period']);
        $this->assertSame(91.5, $item['overall_score']);
        $this->assertSame(1, $item['rank']);
        $this->assertTrue($item['employee_of_the_month']);
        $this->assertCount(1, $item['badges']);
        $this->assertSame('Task Champion', $item['badges'][0]['name']);
        $this->assertSame(['period' => 'monthly', 'threshold' => 10], $item['badges'][0]['criteria_value']);
    }

    public function test_achievements_never_invent_a_badge(): void
    {
        $year = AcademicYear::factory()->create();
        $employee = Employee::factory()->active()->create();

        MonthlyAchievement::create([
            'employee_id' => $employee->id,
            'academic_year_id' => $year->id,
            'month' => 9,
            'year' => 2026,
            'overall_score' => 88,
        ]);

        $item = $this->getJson('/api/v1/hr/achievements')->assertOk()->json('data.items.0');

        $this->assertSame([], $item['badges'], 'no grant means no badge, not a default one.');
    }

    public function test_achievements_can_be_filtered_by_period_and_department(): void
    {
        $year = AcademicYear::factory()->create();
        $education = Department::factory()->create();
        $engineering = Department::factory()->create();

        $ana = Employee::factory()->create(['department_id' => $education->id]);
        $ben = Employee::factory()->create(['department_id' => $engineering->id]);

        MonthlyAchievement::create([
            'employee_id' => $ana->id, 'academic_year_id' => $year->id,
            'month' => 9, 'year' => 2026, 'overall_score' => 90,
        ]);
        MonthlyAchievement::create([
            'employee_id' => $ben->id, 'academic_year_id' => $year->id,
            'month' => 8, 'year' => 2026, 'overall_score' => 80,
        ]);

        $this->assertSame(1, $this->getJson('/api/v1/hr/achievements?year=2026&month=9')
            ->assertOk()->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/hr/achievements?department_id='.$education->id)
            ->assertOk()->json('data.meta.total'));
    }

    public function test_achievements_rejects_an_out_of_range_month(): void
    {
        $this->getJson('/api/v1/hr/achievements?month=13')
            ->assertStatus(422)
            ->assertJsonValidationErrors('month');
    }
}
