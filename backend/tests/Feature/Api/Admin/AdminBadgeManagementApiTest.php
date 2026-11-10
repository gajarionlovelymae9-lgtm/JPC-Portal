<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeTarget;
use App\Models\MonthlyAchievement;
use App\Models\PerformancePeriod;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the Admin Achievement & Badges Management module.
 *
 * These cover the guarantees the UI depends on and that are easy to break
 * silently:
 *  1. every new route is admin-only (401 unauthenticated, 403 for other roles);
 *  2. badge CRUD validates server-side and writes an audit entry;
 *  3. duplicate awards are blocked by the unique index / service guard;
 *  4. verification is the only path from 'pending' to official, and rejection
 *     requires a reason;
 *  5. summaries and evaluation only ever report real employee_badges /
 *     monthly_achievements rows â€” an empty table stays empty.
 */
class AdminBadgeManagementApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    private Department $department;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
        $this->admin = $this->adminUser();
        $this->department = Department::factory()->create();
        $this->employee = $this->employeeUser($this->department);
        $this->academicYear = AcademicYear::factory()->create();
        $this->loginAs($this->admin);
    }

    /* ------------------------------------------------------------------ */
    /* Authorization */
    /* ------------------------------------------------------------------ */

    public function test_guest_cannot_reach_badge_management_endpoints(): void
    {
        // Drop the authenticated guards setUp installed so these requests
        // actually hit the routes as a guest.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/admin/achievements/badge-definitions')->assertStatus(401);
        $this->postJson('/api/v1/admin/achievements/badge-definitions', [])->assertStatus(401);
        $this->getJson('/api/v1/admin/achievements/summaries')->assertStatus(401);
        $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 5])->assertStatus(401);
    }

    public function test_employee_role_cannot_manage_badges(): void
    {
        $employeeUser = $this->roleUser(Role::Employee);
        $this->loginAs($employeeUser);

        $this->getJson('/api/v1/admin/achievements/badge-definitions')->assertStatus(403);
        $this->postJson('/api/v1/admin/achievements/badge-definitions', [
            'name' => 'Sneaky',
            'code' => 'SNEAKY',
        ])->assertStatus(403);
        $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 5])->assertStatus(403);
    }

    /* ------------------------------------------------------------------ */
    /* Badge definition CRUD */
    /* ------------------------------------------------------------------ */

    public function test_admin_creates_a_badge_definition_with_measurable_criteria(): void
    {
        $this->postJson('/api/v1/admin/achievements/badge-definitions', [
            'name' => 'Safety Star',
            'code' => 'SAFETY_STAR',
            'description' => 'Zero safety incidents for the month.',
            'icon' => 'shield',
            'category' => 'Performance',
            'criteria_type' => 'safety_incidents',
            'criteria_value' => ['threshold' => 0, 'period' => 'monthly'],
            'is_active' => true,
            'allow_repeats' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Safety Star')
            ->assertJsonPath('data.code', 'SAFETY_STAR')
            ->assertJsonPath('data.category', 'Performance')
            ->assertJsonPath('data.allow_repeats', false)
            ->assertJsonPath('data.earned_count', 0);

        $this->assertDatabaseHas('badges', ['code' => 'SAFETY_STAR', 'is_active' => true]);
        $this->assertTrue(ActivityLog::query()->where('activity_type', 'admin.badge.create')->exists());
    }

    public function test_badge_creation_preserves_custom_criteria_properties(): void
    {
        $this->postJson('/api/v1/admin/achievements/badge-definitions', [
            'name' => 'Consistency Check',
            'code' => 'CONSISTENCY_CHECK',
            'criteria_type' => 'overall_score',
            'criteria_value' => [
                'period' => 'monthly',
                'threshold' => 90,
                'min_score' => 85,
                'lookback_months' => 3,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.criteria_value.period', 'monthly')
            ->assertJsonPath('data.criteria_value.threshold', 90)
            ->assertJsonPath('data.criteria_value.min_score', 85)
            // Unknown keys are stored untouched for forward compatibility.
            ->assertJsonPath('data.criteria_value.lookback_months', 3);
    }

    public function test_badge_creation_validates_known_criteria_fields(): void
    {
        $this->postJson('/api/v1/admin/achievements/badge-definitions', [
            'name' => 'Invalid Threshold',
            'code' => 'INVALID_THRESHOLD',
            'criteria_value' => ['threshold' => -5],
        ])->assertStatus(422)->assertJsonValidationErrors(['criteria_value.threshold']);

        $this->postJson('/api/v1/admin/achievements/badge-definitions', [
            'name' => 'Invalid Period',
            'code' => 'INVALID_PERIOD',
            'criteria_value' => ['period' => ['monthly']],
        ])->assertStatus(422)->assertJsonValidationErrors(['criteria_value.period']);
    }

    public function test_badge_creation_rejects_duplicate_codes_and_names(): void
    {
        Badge::create(['name' => 'Top Performer', 'code' => 'TOP_PERFORMER']);

        $this->postJson('/api/v1/admin/achievements/badge-definitions', [
            'name' => 'Another Name',
            'code' => 'TOP_PERFORMER',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);

        $this->postJson('/api/v1/admin/achievements/badge-definitions', [
            'name' => 'Top Performer',
            'code' => 'SOMETHING_ELSE',
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_admin_edits_a_badge_definition_and_the_change_is_audited(): void
    {
        $badge = Badge::create([
            'name' => 'Old Name',
            'code' => 'OLD_NAME',
            'category' => 'Progress',
            'is_active' => true,
        ]);

        $this->putJson("/api/v1/admin/achievements/badge-definitions/{$badge->id}", [
            'name' => 'New Name',
            'category' => 'Goals',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.category', 'Goals')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('badges', ['id' => $badge->id, 'name' => 'New Name', 'is_active' => false]);
        $this->assertTrue(ActivityLog::query()->where('activity_type', 'admin.badge.update')->exists());
    }

    public function test_badge_with_award_history_is_deactivated_not_deleted(): void
    {
        $badge = Badge::create(['name' => 'History Badge', 'code' => 'HISTORY']);
        EmployeeBadge::forceCreate([
            'employee_id' => $this->employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'awarded_at' => now(),
        ]);

        $this->deleteJson("/api/v1/admin/achievements/badge-definitions/{$badge->id}")
            ->assertOk()
            ->assertJsonPath('data.action', 'deactivated');

        $this->assertDatabaseHas('badges', ['id' => $badge->id, 'is_active' => false, 'deleted_at' => null]);
        $this->assertDatabaseCount('employee_badges', 1);
    }

    public function test_never_awarded_badge_is_soft_deleted(): void
    {
        $badge = Badge::create(['name' => 'Unused Badge', 'code' => 'UNUSED']);

        $this->deleteJson("/api/v1/admin/achievements/badge-definitions/{$badge->id}")
            ->assertOk()
            ->assertJsonPath('data.action', 'deleted');

        $this->assertSoftDeleted('badges', ['id' => $badge->id]);
    }

    /* ------------------------------------------------------------------ */
    /* Duplicate-award prevention */
    /* ------------------------------------------------------------------ */

    public function test_duplicate_awards_for_same_period_are_blocked_by_the_unique_index(): void
    {
        $badge = Badge::create(['name' => 'Dedup Badge', 'code' => 'DEDUP']);

        $payload = [
            'employee_id' => $this->employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ];

        EmployeeBadge::forceCreate($payload);

        $this->expectException(QueryException::class);
        EmployeeBadge::forceCreate($payload);
    }

    public function test_evaluation_run_is_idempotent_for_the_same_month(): void
    {
        $period = PerformancePeriod::findOrCreateMonthly(2026, 5);

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $this->employee->id,
            'academic_year_id' => $this->academicYear->id,
            'performance_period_id' => $period->id,
            'period_type' => 'monthly',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'productivity_score' => 97.5,
            'completion_rate' => 100,
            'on_time_rate' => 100,
            'metadata' => ['completed_tasks' => 12, 'approved_reports' => 7, 'submitted_reports' => 7],
        ]);

        $first = $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 5])
            ->assertOk()
            ->assertJsonPath('data.total_evaluated', 1);

        $countAfterFirst = EmployeeBadge::query()->count();

        $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 5])
            ->assertOk()
            ->assertJsonPath('data.total_evaluated', 1);

        $this->assertSame($countAfterFirst, EmployeeBadge::query()->count());
        $this->assertGreaterThan(0, $countAfterFirst);
        $this->assertTrue(ActivityLog::query()->where('activity_type', 'admin.achievement.evaluate')->exists());
    }

    public function test_evaluation_awards_goal_achiever_from_real_target_records(): void
    {
        $period = PerformancePeriod::findOrCreateMonthly(2026, 6);

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $this->employee->id,
            'academic_year_id' => $this->academicYear->id,
            'performance_period_id' => $period->id,
            'period_type' => 'monthly',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'productivity_score' => 80,
            'completion_rate' => 80,
            'on_time_rate' => 80,
            'metadata' => ['completed_tasks' => 2, 'approved_reports' => 1, 'submitted_reports' => 1],
        ]);

        EmployeeTarget::factory()->create([
            'employee_id' => $this->employee->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => 'completed',
        ]);

        $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 6])->assertOk();

        $this->assertDatabaseHas('badges', ['code' => 'GOAL_ACHIEVER']);
        $this->assertTrue(
            EmployeeBadge::query()
                ->whereHas('badge', fn ($q) => $q->where('code', 'GOAL_ACHIEVER'))
                ->where('employee_id', $this->employee->id)
                ->exists()
        );
    }

    public function test_evaluation_does_not_award_goal_achiever_with_incomplete_targets(): void
    {
        $period = PerformancePeriod::findOrCreateMonthly(2026, 7);

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $this->employee->id,
            'academic_year_id' => $this->academicYear->id,
            'performance_period_id' => $period->id,
            'period_type' => 'monthly',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'productivity_score' => 80,
            'completion_rate' => 80,
            'on_time_rate' => 80,
            'metadata' => ['completed_tasks' => 2, 'approved_reports' => 1, 'submitted_reports' => 1],
        ]);

        EmployeeTarget::factory()->create([
            'employee_id' => $this->employee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 7])->assertOk();

        $this->assertFalse(
            EmployeeBadge::query()
                ->whereHas('badge', fn ($q) => $q->where('code', 'GOAL_ACHIEVER'))
                ->where('employee_id', $this->employee->id)
                ->exists()
        );
    }

    public function test_consistent_progress_needs_three_consecutive_scored_months(): void
    {
        // Two good months only -> no badge.
        foreach ([['2026-03-01', '2026-03-31', 3, 2026], ['2026-04-01', '2026-04-30', 4, 2026]] as [$start, $end, $month, $year]) {
            EmployeeBadge::forceCreate([
                'employee_id' => $this->employee->id,
                'badge_id' => Badge::create(['name' => 'Placeholder '.$month, 'code' => 'PLACEHOLDER_'.$month])->id,
                'academic_year_id' => $this->academicYear->id,
                'period_start' => $start,
                'period_end' => $end,
            ]);
            $period = PerformancePeriod::findOrCreateMonthly($year, $month);
            MonthlyAchievement::forceCreate([
                'employee_id' => $this->employee->id,
                'academic_year_id' => $this->academicYear->id,
                'month' => $month,
                'year' => $year,
                'completion_rate' => 90,
                'on_time_rate' => 90,
                'overall_score' => 90,
            ]);
        }

        $period = PerformancePeriod::findOrCreateMonthly(2026, 5);
        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $this->employee->id,
            'academic_year_id' => $this->academicYear->id,
            'performance_period_id' => $period->id,
            'period_type' => 'monthly',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'productivity_score' => 90,
            'completion_rate' => 90,
            'on_time_rate' => 90,
            'metadata' => ['completed_tasks' => 3, 'approved_reports' => 2, 'submitted_reports' => 2],
        ]);
        // May's rollup is written by evaluateMonthlyPeriod, completing 3 months.
        $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 5])->assertOk();

        $this->assertTrue(
            EmployeeBadge::query()
                ->whereHas('badge', fn ($q) => $q->where('code', 'CONSISTENT_PROGRESS'))
                ->where('employee_id', $this->employee->id)
                ->exists()
        );
    }

    /* ------------------------------------------------------------------ */
    /* Verification workflow */
    /* ------------------------------------------------------------------ */

    private function pendingAward(): EmployeeBadge
    {
        $badge = Badge::create(['name' => 'Pending Badge', 'code' => 'PENDING']);

        return EmployeeBadge::forceCreate([
            'employee_id' => $this->employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'awarded_at' => now(),
            'verification_status' => 'pending',
        ]);
    }

    public function test_admin_verifies_a_pending_award_and_reviewer_is_recorded(): void
    {
        $award = $this->pendingAward();

        $this->postJson("/api/v1/admin/achievements/awards/{$award->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.verified_by', $this->admin->name)
            ->assertJsonPath('data.verified_at', fn ($v) => $v !== null);

        $this->assertDatabaseHas('employee_badges', [
            'id' => $award->id,
            'verification_status' => 'verified',
            'verified_by' => $this->admin->id,
        ]);
        $this->assertTrue(ActivityLog::query()->where('activity_type', 'admin.achievement.verify')->exists());
    }

    public function test_rejecting_an_award_requires_a_reason(): void
    {
        $award = $this->pendingAward();

        $this->postJson("/api/v1/admin/achievements/awards/{$award->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/admin/achievements/awards/{$award->id}/reject", ['reason' => 'Evidence did not match the criteria.'])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Evidence did not match the criteria.');

        $this->assertDatabaseHas('employee_badges', ['id' => $award->id, 'verification_status' => 'rejected']);
    }

    public function test_already_reviewed_award_cannot_be_re_reviewed(): void
    {
        $award = $this->pendingAward();
        $this->postJson("/api/v1/admin/achievements/awards/{$award->id}/verify")->assertOk();

        $this->postJson("/api/v1/admin/achievements/awards/{$award->id}/reject", ['reason' => 'changed my mind'])
            ->assertStatus(422);

        $this->postJson("/api/v1/admin/achievements/awards/{$award->id}/verify")->assertStatus(422);
    }

    public function test_award_detail_exposes_evidence_snapshot(): void
    {
        $badge = Badge::create(['name' => 'Evidence Badge', 'code' => 'EVIDENCE']);
        $award = EmployeeBadge::forceCreate([
            'employee_id' => $this->employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'awarded_at' => now(),
            'criteria_snapshot' => ['score' => 96.5],
        ]);

        $this->getJson("/api/v1/admin/achievements/awards/{$award->id}")
            ->assertOk()
            ->assertJsonPath('data.evidence.snapshot.score', 96.5)
            ->assertJsonPath('data.employee_id', $this->employee->id);
    }

    /* ------------------------------------------------------------------ */
    /* Filters and summaries */
    /* ------------------------------------------------------------------ */

    public function test_award_filters_by_category_status_and_department(): void
    {
        $badge = Badge::create(['name' => 'Filter Badge', 'code' => 'FILTER', 'category' => 'Punctuality']);
        EmployeeBadge::forceCreate([
            'employee_id' => $this->employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'awarded_at' => now(),
            'verification_status' => 'pending',
        ]);

        $this->getJson('/api/v1/admin/achievements/badges?category=Punctuality')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.badge_category', 'Punctuality')
            ->assertJsonPath('data.items.0.verification_status', 'pending');

        $this->getJson('/api/v1/admin/achievements/badges?category=Goals')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/admin/achievements/badges?status=verified')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson("/api/v1/admin/achievements/badges?department_id={$this->department->id}")->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson("/api/v1/admin/achievements/badges?academic_year_id={$this->academicYear->id}")->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_summaries_report_only_verified_awards_and_handle_ties(): void
    {
        $badge = Badge::create(['name' => 'Summary Badge', 'code' => 'SUMMARY', 'category' => 'Progress']);

        // Two employees with one verified award each (tie), one with two.
        $other = $this->employeeUser($this->department);
        $third = $this->employeeUser($this->department);

        $grants = [
            [$this->employee, '2026-05-01', '2026-05-31'],
            [$this->employee, '2026-06-01', '2026-06-30'],
            [$other, '2026-05-01', '2026-05-31'],
        ];
        foreach ($grants as [$emp, $start, $end]) {
            EmployeeBadge::forceCreate([
                'employee_id' => $emp->id,
                'badge_id' => $badge->id,
                'academic_year_id' => $this->academicYear->id,
                'period_start' => $start,
                'period_end' => $end,
                'awarded_at' => now(),
                'verification_status' => 'verified',
            ]);
        }
        // A pending award for a third employee must not count toward leaders.
        EmployeeBadge::forceCreate([
            'employee_id' => $third->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'awarded_at' => now(),
            'verification_status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/admin/achievements/summaries')->assertOk();

        $response->assertJsonPath('data.cards.total_active_badges', 1)
            ->assertJsonPath('data.cards.total_achievements_awarded', 4)
            ->assertJsonPath('data.cards.pending_reviews', 1)
            ->assertJsonPath('data.cards.employees_recognized', 2);

        $leaders = $response->json('data.top_employees');
        $this->assertSame(1, $leaders[0]['rank']);
        $this->assertSame($this->employee->id, $leaders[0]['employee_id']);
        $this->assertSame(2, $leaders[0]['total']);
        $this->assertSame(2, $leaders[1]['rank']);
        $this->assertSame($other->id, $leaders[1]['employee_id']);
        $this->assertCount(2, $leaders);

        $this->assertSame('Progress', $response->json('data.badge_frequency.0.category'));
        $this->assertNotEmpty($response->json('data.department_distribution'));
        $this->assertNotEmpty($response->json('data.monthly_counts'));
    }

    public function test_summaries_respect_category_and_department_filters(): void
    {
        $badge = Badge::create(['name' => 'Scoped Badge', 'code' => 'SCOPED', 'category' => 'Goals']);
        EmployeeBadge::forceCreate([
            'employee_id' => $this->employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'awarded_at' => now(),
            'verification_status' => 'verified',
        ]);

        $this->getJson('/api/v1/admin/achievements/summaries?category=Goals')
            ->assertOk()
            ->assertJsonPath('data.cards.total_achievements_awarded', 1);

        $this->getJson('/api/v1/admin/achievements/summaries?category=Punctuality')
            ->assertOk()
            ->assertJsonPath('data.cards.total_achievements_awarded', 0);

        $otherDepartment = Department::factory()->create();
        $this->getJson("/api/v1/admin/achievements/summaries?department_id={$otherDepartment->id}")
            ->assertOk()
            ->assertJsonPath('data.cards.employees_recognized', 0);
    }

    public function test_empty_tables_report_zero_not_fake_statistics(): void
    {
        $this->getJson('/api/v1/admin/achievements/summaries')
            ->assertOk()
            ->assertJsonPath('data.cards.total_achievements_awarded', 0)
            ->assertJsonPath('data.cards.pending_reviews', 0)
            ->assertJsonPath('data.cards.employees_recognized', 0)
            ->assertJsonPath('data.top_employees', [])
            ->assertJsonPath('data.badge_frequency', []);

        $this->postJson('/api/v1/admin/achievements/evaluate', ['year' => 2026, 'month' => 1])
            ->assertOk()
            ->assertJsonPath('data.total_evaluated', 0);
    }

    public function test_awards_list_never_returns_rejected_evidence_as_verified(): void
    {
        $award = $this->pendingAward();
        $this->postJson("/api/v1/admin/achievements/awards/{$award->id}/reject", ['reason' => 'No evidence.'])->assertOk();

        $this->getJson('/api/v1/admin/achievements/badges?status=rejected')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.verification_status', 'rejected')
            ->assertJsonPath('data.items.0.rejection_reason', 'No evidence.');

        $this->getJson('/api/v1/admin/achievements/badges?status=verified')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_badge_definitions_endpoint_reports_real_earned_counts(): void
    {
        $badge = Badge::create(['name' => 'Counted Badge', 'code' => 'COUNTED']);
        EmployeeBadge::forceCreate([
            'employee_id' => $this->employee->id,
            'badge_id' => $badge->id,
            'academic_year_id' => $this->academicYear->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'awarded_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/achievements/badge-definitions?search=Counted')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.earned_count', 1);

        $this->getJson('/api/v1/admin/achievements/badge-definitions?category=Goals')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }
}
