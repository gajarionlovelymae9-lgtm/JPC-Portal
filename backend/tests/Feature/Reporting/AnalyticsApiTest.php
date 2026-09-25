<?php

namespace Tests\Feature\Reporting;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 9 — AnalyticsService + AnalyticsController HTTP tests (spec items
 * 14, 16-18): trends, comparisons, rankings, rating distribution and their
 * role scoping.
 */
class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function performanceRecord(Employee $employee, Carbon $month, array $overrides = []): EmployeePerformanceRecord
    {
        return EmployeePerformanceRecord::forceCreate(array_merge([
            'employee_id' => $employee->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'period_type' => 'monthly',
            'period_start' => $month->copy()->startOfMonth()->toDateString(),
            'period_end' => $month->copy()->endOfMonth()->toDateString(),
            'completion_rate' => 85.0,
            'report_completion_rate' => 80.0,
            'on_time_rate' => 78.0,
            'approval_rate' => 90.0,
            'quality_score' => 82.0,
            'target_achievement_rate' => 70.0,
            'productivity_score' => 83.5,
            'overall_score' => 83.5,
        ], $overrides));
    }

    public function test_employee_cannot_access_analytics_endpoints(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('employee');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/analytics/rankings')->assertStatus(403);
        $this->getJson('/api/v1/analytics/performance-trend')->assertStatus(403);
        $this->getJson('/api/v1/analytics/performance-distribution')->assertStatus(403);
    }

    public function test_admin_generates_performance_trend(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $this->performanceRecord($employee, Carbon::now()->startOfMonth());
        $this->performanceRecord($employee, Carbon::now()->subMonth()->startOfMonth());

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/analytics/performance-trend?months=3')
            ->assertOk();

        $this->assertCount(3, $response->json('data'));

        foreach ($response->json('data') as $point) {
            $this->assertArrayHasKey('period', $point);
            $this->assertArrayHasKey('records_count', $point);
            $this->assertArrayHasKey('productivity_score', $point);
        }

        $withData = collect($response->json('data'))->filter(fn ($p) => $p['records_count'] > 0);
        $this->assertGreaterThan(0, $withData->count());
    }

    public function test_admin_generates_completion_and_submission_trends(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $this->performanceRecord($employee, Carbon::now()->startOfMonth());

        \App\Models\Report::factory()->create([
            'employee_id' => $employee->id,
            'is_late' => true,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $completion = $this->getJson('/api/v1/analytics/completion-trend?months=2')->assertOk();
        $this->assertCount(2, $completion->json('data'));

        $submission = $this->getJson('/api/v1/analytics/submission-trend?months=2')->assertOk();
        $submissionCurrent = collect($submission->json('data'))
            ->first(fn ($p) => (int) $p['month'] === (int) now()->month);
        $this->assertSame(1, (int) $submissionCurrent['total_reports']);
        $this->assertSame(1, (int) $submissionCurrent['late_reports']);
    }

    public function test_department_head_rankings_are_limited_to_own_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $empA1 = Employee::factory()->create(['department_id' => $deptA->id]);
        $empA2 = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        $month = Carbon::now()->startOfMonth();
        $this->performanceRecord($empA1, $month, ['productivity_score' => 95.0, 'overall_score' => 95.0]);
        $this->performanceRecord($empA2, $month, ['productivity_score' => 80.0, 'overall_score' => 80.0]);
        $this->performanceRecord($empB, $month, ['productivity_score' => 99.0, 'overall_score' => 99.0]);

        $head = User::factory()->create();
        $head->assignRole('department_head');
        Employee::factory()->create(['user_id' => $head->id, 'department_id' => $deptA->id]);

        Sanctum::actingAs($head);

        $response = $this->getJson('/api/v1/analytics/rankings?year='.$month->year.'&month='.$month->month)
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(2, $rows);
        $this->assertNotContains($empB->id, array_column($rows, 'employee_id'));
    }

    public function test_admin_department_comparison_aggregates_records(): void
    {
        $admin = $this->adminUser();
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $empA = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        $month = Carbon::now()->startOfMonth();
        $this->performanceRecord($empA, $month);
        $this->performanceRecord($empB, $month);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/analytics/department-comparison', [
            'comparison_type' => 'department',
            'ids' => [$deptA->id, $deptB->id],
            'year' => $month->year,
            'month' => $month->month,
        ])->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_department_head_cannot_compare_another_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $head = User::factory()->create();
        $head->assignRole('department_head');
        Employee::factory()->create(['user_id' => $head->id, 'department_id' => $deptA->id]);

        Sanctum::actingAs($head);

        $this->postJson('/api/v1/analytics/department-comparison', [
            'comparison_type' => 'department',
            'ids' => [$deptA->id, $deptB->id],
        ])->assertStatus(403);
    }

    public function test_period_comparison_compares_two_months(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $this->performanceRecord($employee, Carbon::create(2026, 1, 1));
        $this->performanceRecord($employee, Carbon::create(2026, 2, 1));

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/analytics/period-comparison', [
            'comparison_type' => 'period',
            'period_a' => '2026-01',
            'period_b' => '2026-02',
        ])->assertOk();

        $data = $response->json('data');
        $this->assertSame('Jan 2026', $data['period_a']['period']);
        $this->assertSame('Feb 2026', $data['period_b']['period']);
        $this->assertArrayHasKey('productivity_diff', $data['delta']);
    }

    public function test_performance_distribution_counts_records(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();

        $empA = Employee::factory()->create(['department_id' => $department->id]);
        $empB = Employee::factory()->create(['department_id' => $department->id]);

        $month = Carbon::now()->startOfMonth();
        $this->performanceRecord($empA, $month, ['productivity_score' => 96.0, 'overall_score' => 96.0]);
        $this->performanceRecord($empB, $month, ['productivity_score' => 61.0, 'overall_score' => 61.0]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/analytics/performance-distribution?year='.$month->year.'&month='.$month->month)
            ->assertOk()
            ->assertJsonPath('data.total_evaluated', 2);
    }
}