<?php

namespace Tests\Feature\Api\Admin;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\AiAnalysis;
use App\Models\AiInsight;
use App\Models\AiValidationResult;
use App\Models\Badge;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeeTarget;
use App\Models\MonthlyAchievement;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the full admin dashboard surface.
 *
 * These assert three things that the UI depends on and that are easy to break
 * silently:
 *  1. every admin route is admin-only (401 unauthenticated, 403 for other roles);
 *  2. every list endpoint returns `items` + `meta`, never a bare array;
 *  3. the honesty guarantees hold — an empty `ai_insights` table is reported as
 *     unavailable instead of being filled in, calendar categories the schema
 *     cannot supply are marked unsupported, and KPI actuals are null rather than
 *     estimated when no linked task data exists.
 */
class AdminDashboardApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
        $this->admin = $this->adminUser();
        $this->employee = $this->employeeUser();
        $this->loginAs($this->admin);
    }

    /**
     * Replace the `{user}` / `{employee}` placeholders with ids that exist, so
     * route model binding resolves and the auth middleware is what decides the
     * response.
     */
    private function resolve(string $template): string
    {
        return str_replace(
            ['{user}', '{employee}'],
            [(string) $this->admin->id, (string) $this->employee->id],
            $template,
        );
    }

    /**
     * Every route the new admin pages call.
     *
     * `{user}` and `{employee}` are placeholders resolved to real ids inside the
     * test, because Laravel substitutes route model bindings *before* the auth
     * middleware runs. Asserting 401/403 against a non-existent id would
     * therefore only prove the binding failed.
     *
     * @return list<array{string}>
     */
    public static function adminEndpointProvider(): array
    {
        $paths = [
            '/admin/executive-dashboard',
            '/admin/platform-status',
            '/admin/performance-overview',
            '/admin/kpi-targets',
            '/admin/departments',
            '/admin/employee-progress',
            '/admin/employee-progress/summary',
            '/admin/employee-progress/options',
            '/admin/employee-progress/{employee}',
            '/admin/achievements',
            '/admin/achievements/badges',
            '/admin/achievements/options',
            '/admin/task-monitoring',
            '/admin/report-monitoring',
            '/admin/monitoring-options',
            '/admin/calendar',
            '/admin/notifications',
            '/admin/audit-trail',
            '/admin/governance-summary',
            '/admin/governance-options',
            '/admin/ai-insights',
            '/admin/ai-insight-options',
            '/admin/users',
            '/admin/users/{user}',
        ];

        return array_map(fn (string $path) => [$path], $paths);
    }

    #[DataProvider('adminEndpointProvider')]
    public function test_admin_endpoint_is_reachable_for_an_admin(string $template): void
    {
        $this->getJson('/api/v1'.$this->resolve($template))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    #[DataProvider('adminEndpointProvider')]
    public function test_admin_endpoint_requires_authentication(string $template): void
    {
        $path = $this->resolve($template);

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1'.$path)->assertStatus(401);
    }

    #[DataProvider('adminEndpointProvider')]
    public function test_admin_endpoint_forbids_non_admin_roles(string $template): void
    {
        $path = $this->resolve($template);

        $this->app['auth']->forgetGuards();
        $this->loginAs($this->employee->user);

        $this->getJson('/api/v1'.$path)->assertStatus(403);
    }

    public function test_employee_progress_summary_counts_real_assignments(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeUser($department);

        TaskAssignment::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'status' => 'completed',
        ]);
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'in_progress',
        ]);

        // setUp() already created one employee with no assignments.
        $expectedEmployees = Employee::query()->count();

        $this->getJson('/api/v1/admin/employee-progress/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['total_employees', 'assigned_tasks', 'completed_tasks', 'pending_tasks', 'completion_rate'],
            ])
            ->assertJsonPath('data.total_employees', $expectedEmployees)
            ->assertJsonPath('data.assigned_tasks', 3)
            ->assertJsonPath('data.completed_tasks', 2);
    }

    public function test_employee_progress_list_is_paginated_with_items_and_meta(): void
    {
        $department = Department::factory()->create();

        Employee::factory()->count(3)->create(['department_id' => $department->id]);

        $response = $this->getJson('/api/v1/admin/employee-progress')->assertOk();

        $response->assertJsonStructure(['data' => ['items', 'meta' => ['current_page', 'last_page', 'total']]]);
        $this->assertCount(Employee::query()->count(), $response->json('data.items'));
        $this->assertSame(Employee::query()->count(), $response->json('data.meta.total'));
    }

    public function test_employee_progress_detail_404s_for_an_unknown_employee(): void
    {
        $this->getJson('/api/v1/admin/employee-progress/999999')->assertStatus(404);
    }

    public function test_achievements_report_totals_and_recognition(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeUser($department);

        // `monthly_achievements` has no factory, and the model deliberately
        // guards its verdict fields so only internal services can write them.
        MonthlyAchievement::query()->forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'month' => 5,
            'year' => 2026,
            'completion_rate' => 92,
            'on_time_rate' => 88,
            'approved_reports' => 4,
            'completed_tasks' => 9,
            'overall_score' => 90,
            'rank' => 1,
            'is_employee_of_month' => true,
        ]);

        $this->getJson('/api/v1/admin/achievements')
            ->assertOk()
            ->assertJsonStructure(['data' => ['items', 'meta', 'summary', 'leaders']])
            ->assertJsonPath('data.summary.employee_of_the_month_count', 1)
            ->assertJsonPath('data.items.0.employee_of_the_month', true)
            ->assertJsonPath('data.items.0.overall_score', 90);
    }

    public function test_badge_awards_are_listed_from_real_grants(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeUser($department);

        $badge = Badge::query()->create([
            'name' => 'Top Performer',
            'code' => 'TOP-PERFORMER',
            'description' => 'Highest overall score for the period.',
        ]);

        EmployeeBadge::query()->create([
            'employee_id' => $employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'awarded_at' => now(),
            'awarded_by' => $this->admin->id,
        ]);

        $this->getJson('/api/v1/admin/achievements/badges')
            ->assertOk()
            ->assertJsonStructure(['data' => ['items', 'meta']])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.badge', 'Top Performer')
            ->assertJsonPath('data.items.0.badge_code', 'TOP-PERFORMER');
    }

    public function test_kpi_actual_is_null_when_no_task_data_is_linked(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeUser($department);

        // `employee_targets` has no `actual_value` column at all, so the factory
        // cannot supply one; the API must derive it (or report null).
        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 10,
        ]);

        $this->getJson('/api/v1/admin/kpi-targets')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $target->id)
            // Nothing is linked, so the derived actual must be null, not 0 or a guess.
            ->assertJsonPath('data.items.0.actual', null)
            ->assertJsonPath('data.items.0.achievement_pct', null);
    }

    public function test_kpi_actual_is_derived_from_linked_completed_tasks(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeUser($department);

        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 10,
        ]);

        // `EmployeeTarget::tasks()` is a hasMany via `tasks.employee_target_id`,
        // which is what the KPI actual/achievement is derived from.
        Task::factory()->count(2)->create([
            'department_id' => $department->id,
            'employee_target_id' => $target->id,
            'status' => 'completed',
        ]);
        Task::factory()->create([
            'department_id' => $department->id,
            'employee_target_id' => $target->id,
            'status' => 'in_progress',
        ]);

        $row = $this->getJson('/api/v1/admin/kpi-targets')->assertOk()->json('data.items.0');

        $this->assertSame($target->id, $row['id']);
        $this->assertSame(3, $row['tasks_total']);
        $this->assertSame(2, $row['tasks_completed']);
        $this->assertSame(2, $row['actual']);
        $this->assertEqualsWithDelta(66.67, $row['achievement_pct'], 0.01);
    }

    public function test_calendar_marks_categories_the_schema_cannot_supply(): void
    {
        $response = $this->getJson('/api/v1/admin/calendar')->assertOk();

        $response->assertJsonStructure(['data' => ['events', 'sources', 'range' => ['from', 'to']]]);

        $sources = collect($response->json('data.sources'))->keyBy('type');

        $this->assertTrue($sources['task_deadline']['supported']);
        $this->assertFalse($sources['meeting']['supported']);
        $this->assertFalse($sources['institutional_event']['supported']);
        $this->assertFalse($sources['holiday']['supported']);
        $this->assertNotNull($sources['meeting']['note']);
    }

    public function test_calendar_falls_back_to_the_task_due_date_when_the_assignment_has_none(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeUser($department);

        // Mid-month so the date is unambiguously inside the queried window.
        $task = Task::factory()->create([
            'department_id' => $department->id,
            'due_date' => now()->startOfMonth()->addDays(10)->toDateString(),
        ]);

        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'due_date' => null,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $events = $this->getJson("/api/v1/admin/calendar?from={$from}&to={$to}")
            ->assertOk()
            ->json('data.events');

        $this->assertNotEmpty(
            $events,
            'The assignment has no due_date of its own, so the parent task due_date fallback must surface it.',
        );
    }

    public function test_audit_trail_unions_both_log_tables_and_labels_the_source(): void
    {
        // `audit_logs` has no factory and the model is not mass-assignable, so
        // the row is written through the query builder.
        DB::table('audit_logs')->insert([
            'user_id' => $this->admin->id,
            'action' => 'admin.user.update',
            'entity_type' => User::class,
            'entity_id' => $this->admin->id,
            'old_values' => json_encode(['status' => 'active']),
            'new_values' => json_encode(['status' => 'inactive']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLog::factory()->create([
            'user_id' => $this->admin->id,
            'activity_type' => 'admin.user.create',
        ]);

        $response = $this->getJson('/api/v1/admin/audit-trail')->assertOk();

        $response->assertJsonStructure(['data' => ['items', 'meta']]);
        $response->assertJsonPath('data.meta.total', 2);

        $sources = collect($response->json('data.items'))->pluck('source')->unique()->sort()->values()->all();
        $this->assertSame(['activity_log', 'audit_log'], $sources);

        // The audit branch must expose a field-level diff; the activity branch
        // has no before/after snapshot and must return an empty list.
        $auditRow = collect($response->json('data.items'))->firstWhere('source', 'audit_log');
        $this->assertSame([['field' => 'status', 'old' => 'active', 'new' => 'inactive']], $auditRow['changes']);

        $activityRow = collect($response->json('data.items'))->firstWhere('source', 'activity_log');
        $this->assertSame([], $activityRow['changes']);
    }

    public function test_audit_trail_source_filter_narrows_to_one_table(): void
    {
        foreach (range(1, 2) as $index) {
            DB::table('audit_logs')->insert([
                'user_id' => $this->admin->id,
                'action' => 'admin.user.update.'.$index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        ActivityLog::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $this->getJson('/api/v1/admin/audit-trail?source=audit_log')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_governance_summary_is_nested_not_flat(): void
    {
        $this->getJson('/api/v1/admin/governance-summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'notifications' => ['total', 'unread', 'high_priority', 'last_24h'],
                    'audit_logs' => ['total', 'last_7_days'],
                    'activity_logs' => ['total', 'last_7_days', 'failed_logins_7d'],
                ],
            ]);
    }

    public function test_ai_insights_reports_unavailable_when_the_table_is_empty(): void
    {
        $this->assertSame(0, AiInsight::query()->count());

        $this->getJson('/api/v1/admin/ai-insights')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.generated', false)
            ->assertJsonPath('data.message', 'No AI insight available')
            ->assertJsonCount(0, 'data.insights')
            ->assertJsonPath('data.counts.total', 0)
            ->assertJsonPath('data.reports_analyzed', 0)
            ->assertJsonPath('data.validation.total', 0)
            ->assertJsonPath('data.validation.avg_score', null)
            ->assertJsonPath('data.validation.passed', 0)
            ->assertJsonPath('data.validation.warning', 0)
            ->assertJsonPath('data.validation.failed', 0);
    }

    public function test_ai_insights_excludes_sample_validations_from_live_kpis(): void
    {
        $report = Report::factory()->create(['employee_id' => $this->employee->id]);
        $sampleAnalysis = AiAnalysis::factory()->create([
            'report_id' => $report->id,
            'provider' => 'jpc.sample',
            'model' => 'development-sample',
            'status' => 'completed',
        ]);
        AiValidationResult::factory()->create([
            'report_id' => $report->id,
            'ai_analysis_id' => $sampleAnalysis->id,
            'overall_score' => 0.915,
            'result' => 'pass',
        ]);

        $response = $this->getJson('/api/v1/admin/ai-insights')->assertOk();

        // Live KPIs must be empty; the seeded row is reported separately.
        $response
            ->assertJsonPath('data.validation.total', 0)
            ->assertJsonPath('data.validation.avg_score', null)
            ->assertJsonPath('data.validation.passed', 0)
            ->assertJsonPath('data.validation.warning', 0)
            ->assertJsonPath('data.validation.failed', 0)
            ->assertJsonPath('data.validation.sample.total', 1)
            ->assertJsonPath('data.validation.sample.passed', 1)
            ->assertJsonPath('data.validation.sample.present', true)
            ->assertJsonPath('data.reports_analyzed', 0);

        $sampleAvg = (float) $response->json('data.validation.sample.avg_score');
        $this->assertSame(91.5, $sampleAvg);
    }

    public function test_ai_insights_counts_live_validations_separately_from_sample_rows(): void
    {
        $report = Report::factory()->create(['employee_id' => $this->employee->id]);

        $liveAnalysis = AiAnalysis::factory()->create([
            'report_id' => $report->id,
            'provider' => 'groq',
            'model' => 'openai/gpt-oss-120b',
            'status' => 'completed',
        ]);
        AiValidationResult::factory()->create([
            'report_id' => $report->id,
            'ai_analysis_id' => $liveAnalysis->id,
            'overall_score' => 0.8,
            'result' => 'pass',
        ]);

        $sampleAnalysis = AiAnalysis::factory()->create([
            'report_id' => $report->id,
            'provider' => 'jpc.sample',
            'model' => 'development-sample',
            'status' => 'completed',
        ]);
        AiValidationResult::factory()->create([
            'report_id' => $report->id,
            'ai_analysis_id' => $sampleAnalysis->id,
            'overall_score' => 0.1,
            'result' => 'fail',
        ]);

        $this->getJson('/api/v1/admin/ai-insights')
            ->assertOk()
            ->assertJsonPath('data.validation.total', 1)
            ->assertJsonPath('data.validation.passed', 1)
            ->assertJsonPath('data.validation.warning', 0)
            ->assertJsonPath('data.validation.failed', 0)
            ->assertJsonPath('data.validation.avg_score', 80)
            ->assertJsonPath('data.validation.sample.total', 1)
            ->assertJsonPath('data.validation.sample.failed', 1)
            ->assertJsonPath('data.reports_analyzed', 1);
    }

    public function test_ai_insights_provider_status_requires_a_recorded_live_request(): void
    {
        config([
            'ai_reports.ai.provider' => 'groq',
            'ai_reports.ai.model' => 'openai/gpt-oss-120b',
            'ai_reports.ai.http.endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
            'ai_reports.ai.http.api_key' => 'test-key-not-real',
        ]);

        // Configured but nothing recorded yet: not connected.
        $this->getJson('/api/v1/admin/ai-insights')
            ->assertOk()
            ->assertJsonPath('data.provider_status.configured', true)
            ->assertJsonPath('data.provider_status.connected', false);

        AiInsight::factory()->create([
            'metadata' => [
                'provenance' => [
                    'provider' => 'groq',
                    'model' => 'openai/gpt-oss-120b',
                ],
            ],
        ]);

        // Connected only because a real (non-sample) insight record exists.
        $this->getJson('/api/v1/admin/ai-insights')
            ->assertOk()
            ->assertJsonPath('data.provider_status.connected', true)
            ->assertJsonPath('data.provider_status.data_source', 'live_database')
            ->assertJsonPath('data.provider_status.last_successful_ai_request', fn (?string $value) => $value !== null);
    }

    public function test_ai_insights_flags_seeded_validation_rows_as_sample_data(): void
    {
        $report = Report::factory()->create(['employee_id' => $this->employee->id]);

        AiValidationResult::factory()->create(['report_id' => $report->id]);

        $this->getJson('/api/v1/admin/ai-insights')
            ->assertOk()
            // The configured provider is the `fake` driver, so stored scores must
            // never be presented as live AI output.
            ->assertJsonPath('data.validation.is_sample_data', true)
            ->assertJsonPath('data.validation.provenance_note', fn (?string $note) => $note !== null)
            ->assertJsonPath('data.provider_status.live', false);
    }

    public function test_user_management_accepts_a_role_key_and_deactivates_without_deleting(): void
    {
        $department = Department::factory()->create();
        // The new account links an employee record that does not yet have a
        // login (employeeUser() always links one), so build it directly.
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $created = $this->postJson('/api/v1/admin/users', [
            'name' => 'New Officer',
            'email' => 'new.officer@jpc.local',
            'username' => 'new.officer',
            'password' => 'password123',
            // A role key string, not a role_id.
            'role' => 'hr',
            'employee_id' => $employee->id,
        ])->assertCreated();

        $id = $created->json('data.id');
        $this->assertSame('hr', $created->json('data.role'));
        $this->assertSame($employee->id, $created->json('data.employee_id'));
        $this->assertSame($department->name, $created->json('data.department'));

        $deactivated = $this->deleteJson("/api/v1/admin/users/{$id}")->assertOk();
        $this->assertFalse($deactivated->json('data.is_active'));
        $this->assertFalse($deactivated->json('data.can_authenticate'));

        // The row survives so historical audit entries and foreign keys resolve.
        $this->assertDatabaseHas('users', ['id' => $id, 'status' => 'inactive']);

        $this->postJson("/api/v1/admin/users/{$id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_user_management_rejects_a_role_id_instead_of_a_role_key(): void
    {
        $this->postJson('/api/v1/admin/users', [
            'name' => 'Bad Role',
            'email' => 'bad.role@jpc.local',
            'username' => 'bad.role',
            'password' => 'password123',
            'role' => '1',
        ])->assertStatus(422);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $this->deleteJson("/api/v1/admin/users/{$this->admin->id}")->assertStatus(422);
    }

    public function test_user_list_embeds_its_filter_and_form_options(): void
    {
        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'meta',
                    'summary' => ['total_users', 'active_users', 'unlinked_employees', 'by_role'],
                    'options' => ['roles', 'statuses', 'unlinked_employees', 'employees'],
                ],
            ]);
    }

    public function test_user_account_requires_a_unique_username(): void
    {
        $this->postJson('/api/v1/admin/users', [
            'name' => 'No Username',
            'email' => 'no.username@jpc.local',
            'password' => 'password123',
            'role' => 'employee',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Taken Username',
            'email' => 'taken.username@jpc.local',
            'username' => $this->admin->username,
            'password' => 'password123',
            'role' => 'employee',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_user_account_cannot_link_an_employee_that_already_has_one(): void
    {
        $this->postJson('/api/v1/admin/users', [
            'name' => 'Duplicate Link',
            'email' => 'duplicate.link@jpc.local',
            'username' => 'duplicate.link',
            'password' => 'password123',
            'role' => 'employee',
            'employee_id' => $this->employee->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id'])
            ->assertJsonPath(
                'errors.employee_id.0',
                'That employee already has a login account. Open the existing account instead.',
            );
    }

    public function test_user_update_rejects_an_employee_owned_by_another_account(): void
    {
        $other = $this->employeeUser();

        $this->putJson("/api/v1/admin/users/{$this->employee->user_id}", [
            'employee_id' => $other->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id'])
            ->assertJsonPath(
                'errors.employee_id.0',
                'That employee is already linked to another account.',
            );
    }

    public function test_user_options_mark_employees_that_already_have_an_account(): void
    {
        $response = $this->getJson('/api/v1/admin/users')->assertOk();

        $row = collect($response->json('data.options.employees'))
            ->firstWhere('id', $this->employee->id);

        $this->assertNotNull($row);
        $this->assertSame($this->employee->user->email, $row['account']['email']);
        $this->assertSame($this->employee->user->username, $row['account']['username']);
    }

    /**
     * The admin export actions reuse the Phase 9 export endpoints rather than
     * a second export pipeline, so the routes the pages call are covered here.
     *
     * @return array<string, array{0: string}>
     */
    public static function adminExportProvider(): array
    {
        return [
            'employee performance' => ['/api/v1/reports/export/employee-performance'],
            'department performance' => ['/api/v1/reports/export/department-performance'],
            'task completion' => ['/api/v1/reports/export/task-completion'],
            'achievements' => ['/api/v1/reports/export/achievements'],
        ];
    }

    #[DataProvider('adminExportProvider')]
    public function test_admin_export_returns_a_real_file(string $path): void
    {
        $response = $this->get($path.'?format=csv')->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));

        // A real CSV export: UTF-8 BOM (Excel) followed by a header row.
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertNotSame('', trim(substr($body, 3)));
    }

    #[DataProvider('adminExportProvider')]
    public function test_admin_export_requires_authentication(string $path): void
    {
        // setUp logs the admin in, so the guard has to be dropped for this one.
        $this->app['auth']->forgetGuards();

        $this->get($path.'?format=csv')->assertStatus(401);
    }

    /**
     * Exports that expose institution-wide rows are off limits to an employee.
     *
     * `export/achievements` is deliberately excluded: the endpoint scopes an
     * employee to their own record, so 200 is the documented behaviour there.
     *
     * @return array<string, array{0: string}>
     */
    public static function scopedAdminExportProvider(): array
    {
        return [
            'employee performance' => ['/api/v1/reports/export/employee-performance'],
            'department performance' => ['/api/v1/reports/export/department-performance'],
            'task completion' => ['/api/v1/reports/export/task-completion'],
        ];
    }

    #[DataProvider('scopedAdminExportProvider')]
    public function test_admin_export_forbids_a_plain_employee(string $path): void
    {
        $this->app['auth']->forgetGuards();
        $this->loginAs($this->employee->user);

        $this->get($path.'?format=csv')->assertStatus(403);
    }

    public function test_achievements_export_scopes_an_employee_to_their_own_record(): void
    {
        $this->app['auth']->forgetGuards();
        $this->loginAs($this->employee->user);

        $response = $this->get('/api/v1/reports/export/achievements?format=csv')->assertOk();

        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_admin_export_rejects_an_unsupported_format(): void
    {
        $this->get('/api/v1/reports/export/employee-performance?format=docx')->assertStatus(422);
    }
}
