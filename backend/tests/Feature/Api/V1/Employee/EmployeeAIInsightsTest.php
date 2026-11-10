<?php

namespace Tests\Feature\Api\V1\Employee;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\Report;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Employee AI Insights: GET /api/v1/employee/ai-insights.
 *
 * This surface makes derived claims about an employee, so it owns the two rules
 * that matter most:
 *  - the employee is resolved from the authenticated session only. No
 *    employee_id is accepted, and one employee can never read another's data;
 *  - nothing is invented. A metric with no underlying records is reported as
 *    null with a reason, the score drops those metrics and renormalises the
 *    remaining weights, and a projection is refused until enough months exist.
 */
class EmployeeAIInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
        Cache::flush();

        // Pin to mid-month so "last month" and "this month" fixtures both land
        // inside the windows the service aggregates, regardless of when the
        // suite runs.
        $this->travelTo(Carbon::now()->startOfMonth()->addDays(14)->setTime(10, 0));
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    private function employeeUser(?Role $role = null): array
    {
        $employee = Employee::factory()->create(['user_id' => null]);
        $user = User::factory()->create(['role_id' => $this->roleId($role ?? Role::Employee)]);
        $employee->update(['user_id' => $user->id]);

        return [$user, $employee->fresh()];
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    private function endpoint(): string
    {
        return '/api/v1/employee/ai-insights';
    }

    /** One completed task + one on-time approved report in the current month. */
    private function seedCurrentMonthWork(Employee $employee, Department $department): void
    {
        $task = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'completed',
            'due_date' => now()->subDays(4),
        ]);

        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'completed',
            'assigned_at' => now()->subDays(6),
            'due_date' => now()->subDays(4),
            'completed_at' => now()->subDays(5),
        ]);

        Report::factory()->approved()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'deadline' => now()->subDays(3)->toDateString(),
            'submitted_at' => now()->subDays(4),
            'approved_at' => now()->subDays(3),
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');

        $this->getJson($this->endpoint())->assertUnauthorized();
    }

    public function test_account_without_employee_profile_is_forbidden(): void
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
        $this->login($user);

        $this->getJson($this->endpoint())->assertForbidden();
    }

    public function test_a_supplied_employee_id_is_ignored_and_never_leaks_another_employee(): void
    {
        [$user, $employee] = $this->employeeUser();
        $department = Department::factory()->create();
        $this->seedCurrentMonthWork($employee, $department);

        $otherEmployee = Employee::factory()->create(['user_id' => null]);
        $otherTask = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'in_progress',
            'due_date' => now()->addDays(5),
        ]);
        TaskAssignment::factory()->create([
            'employee_id' => $otherEmployee->id,
            'task_id' => $otherTask->id,
            'status' => 'in_progress',
            'assigned_at' => now()->subDay(),
            'due_date' => now()->addDays(5),
        ]);

        $this->login($user);

        // An attacker-supplied employee_id must not change whose data is read.
        $response = $this->getJson($this->endpoint().'?employee_id='.$otherEmployee->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $response->assertJsonPath('data.employee.name', trim(implode(' ', array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
        ]))));

        // 1 own assignment, not the other employee's in-progress one.
        $taskMetric = collect($response->json('data.metrics'))
            ->firstWhere('key', 'task_completion');

        $this->assertEquals(100.0, $taskMetric['value']);
        $this->assertCount(6, $response->json('data.metrics'), 'metric list is stable');
    }

    public function test_empty_employee_reports_no_score_instead_of_a_fabricated_zero(): void
    {
        [$user] = $this->employeeUser();
        $this->login($user);

        $response = $this->getJson($this->endpoint())->assertOk();

        $response->assertJsonPath('data.available', false);
        $response->assertJsonPath('data.score.value', null);
        $response->assertJsonPath('data.score.label', null);
        $response->assertJsonPath('data.score.metrics_used', 0);
        $response->assertJsonPath('data.summary', null);
        $response->assertJsonPath('data.alerts', []);
        $response->assertJsonPath('data.recommendations', []);
        $response->assertJsonPath('data.performance_trend', []);
        $response->assertJsonPath('data.productivity_trend', []);
        $response->assertJsonPath('data.score_transparency.available', false);

        $this->assertNotNull($response->json('data.no_data_reason'));

        foreach ($response->json('data.metrics') as $metric) {
            $this->assertNull($metric['value'], "{$metric['key']} must be null without data");
            $this->assertFalse($metric['available']);
            $this->assertNotNull($metric['unavailable_reason']);
        }

        foreach ($response->json('data.score_transparency.metrics') as $metric) {
            $this->assertNull($metric['raw_value']);
            $this->assertNull($metric['applied_weight']);
            $this->assertNull($metric['contribution']);
            $this->assertNotNull($metric['excluded_reason']);
        }
    }

    public function test_forecast_is_refused_until_enough_months_are_measured(): void
    {
        [$user, $employee] = $this->employeeUser();
        $this->seedCurrentMonthWork($employee, Department::factory()->create());
        $this->login($user);

        $forecast = $this->getJson($this->endpoint())->assertOk()->json('data.forecast');

        $this->assertNull($forecast['value']);
        $this->assertSame('insufficient_data', $forecast['confidence']);
        $this->assertSame(3, $forecast['required_months']);
        $this->assertSame(1, $forecast['based_on_months']);
        $this->assertNotNull($forecast['reason']);
        $this->assertNotNull($forecast['needed']);
    }

    public function test_partial_data_excludes_unmeasured_metrics_and_renormalises_weights(): void
    {
        [$user, $employee] = $this->employeeUser();
        $department = Department::factory()->create();

        // Tasks only. A report is expected for the assigned task, so report
        // completion is measurable at a real 0% - it must NOT be dropped or
        // reported as "no data". On-time, approval and target have no records at
        // all, so those three are excluded instead.
        $task = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'completed',
            'due_date' => now()->subDays(2),
        ]);
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'completed',
            'assigned_at' => now()->subDays(3),
            'due_date' => now()->subDays(2),
            'completed_at' => now()->subDays(2),
        ]);

        $this->login($user);

        $response = $this->getJson($this->endpoint())->assertOk();

        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.score.metrics_used', 2);
        $response->assertJsonPath('data.score.metrics_excluded', 3);

        $metrics = collect($response->json('data.metrics'))->keyBy('key');
        $this->assertEquals(100.0, $metrics['task_completion']['value']);
        $this->assertEquals(0.0, $metrics['report_completion']['value'], 'a missing report is a real 0%, not missing data');
        $this->assertNull($metrics['on_time_submission']['value']);
        $this->assertNull($metrics['report_approval']['value']);
        $this->assertNull($metrics['target_achievement']['value']);

        // task_completion 30% + report_completion 20% = 50% of the configured
        // weights are measured, so they renormalise to 60% and 40%.
        $weights = collect($response->json('data.score_transparency.metrics'))->keyBy('key');

        $this->assertEquals(30.0, $weights['task_completion']['configured_weight']);
        $this->assertEquals(60.0, $weights['task_completion']['applied_weight']);
        $this->assertEquals(40.0, $weights['report_completion']['applied_weight']);
        $this->assertEquals(60.0, $weights['task_completion']['contribution']);

        // 100 * 0.60 + 0 * 0.40
        $this->assertEquals(60.0, $response->json('data.score.value'));

        $excluded = collect($response->json('data.score_transparency.metrics'))
            ->where('available', false);

        $this->assertCount(3, $excluded);
        foreach ($excluded as $metric) {
            $this->assertNull($metric['raw_value']);
            $this->assertNull($metric['applied_weight']);
            $this->assertNull($metric['contribution']);
            $this->assertNotNull($metric['excluded_reason']);
        }

        $this->assertEqualsWithDelta(
            100.0,
            collect($response->json('data.score_transparency.metrics'))
                ->where('available', true)
                ->sum('applied_weight'),
            0.01,
        );

        $this->assertStringContainsString('excluded', $response->json('data.summary'));
    }

    public function test_metric_with_no_records_at_all_is_reported_as_unavailable_not_zero(): void
    {
        [$user, $employee] = $this->employeeUser();
        $department = Department::factory()->create();

        // One completed task and one on-time approved report, but no targets and
        // no rejected reports: those two metrics have no records to measure.
        $this->seedCurrentMonthWork($employee, $department);

        $this->login($user);

        $weights = collect($this->getJson($this->endpoint())->assertOk()
            ->json('data.score_transparency.metrics'))->keyBy('key');

        $this->assertFalse($weights['target_achievement']['available']);
        $this->assertNull($weights['target_achievement']['raw_value']);
        $this->assertEquals(15.0, $weights['target_achievement']['configured_weight']);
        $this->assertNull($weights['target_achievement']['applied_weight']);

        // Remaining weights (30 + 20 + 20 + 15 = 85) renormalise to 100.
        $this->assertEqualsWithDelta(100.0, collect($weights)->where('available', true)->sum('applied_weight'), 0.01);
        $this->assertEqualsWithDelta(35.29, $weights['task_completion']['applied_weight'], 0.01);
    }

    public function test_full_dataset_uses_the_approved_configured_weights(): void
    {
        [$user, $employee] = $this->employeeUser();
        $department = Department::factory()->create();
        $this->seedCurrentMonthWork($employee, $department);

        EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 100,
            'status' => 'achieved',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $this->login($user);

        $response = $this->getJson($this->endpoint())->assertOk();

        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.score.metrics_used', 5);
        $response->assertJsonPath('data.score.metrics_excluded', 0);
        $response->assertJsonPath('data.score_transparency.weights_source', 'config/performance.php');
        $response->assertJsonPath('data.score_transparency.calculation_version', config('performance.calculation_version'));

        $weights = collect($response->json('data.score_transparency.metrics'));
        foreach ($weights as $metric) {
            $this->assertTrue($metric['available'], "{$metric['key']} should be measurable");
            $this->assertEquals($metric['configured_weight'], $metric['applied_weight']);
        }

        $this->assertEqualsWithDelta(100.0, $weights->sum('applied_weight'), 0.01);

        // Every measured metric is 100% here, so the score is 100 as well.
        $this->assertEquals(100.0, $response->json('data.score.value'));
        $this->assertCount(1, $response->json('data.performance_trend'));
    }

    public function test_alerts_report_the_counts_they_are_derived_from(): void
    {
        [$user, $employee] = $this->employeeUser();
        $department = Department::factory()->create();

        $overdueTask = Task::factory()->create([
            'department_id' => $department->id,
            'status' => 'assigned',
            'due_date' => now()->subDays(2),
        ]);
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $overdueTask->id,
            'status' => 'assigned',
            'assigned_at' => now()->subDays(5),
            'due_date' => now()->subDays(2),
        ]);

        $this->login($user);

        $alerts = collect($this->getJson($this->endpoint())->assertOk()->json('data.alerts'));

        $this->assertTrue($alerts->contains('key', 'overdue_tasks'));
        $this->assertSame(1, $alerts->firstWhere('key', 'overdue_tasks')['evidence']['overdue_tasks']);

        // Task completion is 0% but reports were never expected, so the
        // report-completion alert must not appear.
        $this->assertFalse($alerts->contains('key', 'late_reports'));
        $this->assertFalse($alerts->contains('key', 'report_rejections'));
    }

    public function test_trend_and_forecast_appear_once_three_months_are_measured(): void
    {
        [$user, $employee] = $this->employeeUser();
        $department = Department::factory()->create();

        foreach ([2, 1, 0] as $monthsAgo) {
            $assignedAt = Carbon::now()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays(5);

            $task = Task::factory()->create([
                'department_id' => $department->id,
                'status' => 'completed',
                'due_date' => $assignedAt->copy()->addDays(2),
            ]);
            TaskAssignment::factory()->create([
                'employee_id' => $employee->id,
                'task_id' => $task->id,
                'status' => 'completed',
                'assigned_at' => $assignedAt,
                'due_date' => $assignedAt->copy()->addDays(2),
                'completed_at' => $assignedAt->copy()->addDays(1),
            ]);
        }

        $this->login($user);

        $response = $this->getJson($this->endpoint())->assertOk();

        $trend = $response->json('data.performance_trend');
        $this->assertCount(3, $trend);
        $this->assertSame(['period_label', 'year', 'month', 'score', 'rating'], array_keys($trend[0]));

        $forecast = $response->json('data.forecast');
        $this->assertNotNull($forecast['value']);
        $this->assertSame('linear_trend', $forecast['method']);
        $this->assertSame('low', $forecast['confidence']);
        $this->assertSame(3, $forecast['based_on_months']);
        $this->assertNull($forecast['needed']);
    }

    public function test_invalid_period_input_is_rejected(): void
    {
        [$user] = $this->employeeUser();
        $this->login($user);

        $this->getJson($this->endpoint().'?month=13')->assertJsonValidationErrors('month');
        $this->getJson($this->endpoint().'?year=1999')->assertJsonValidationErrors('year');
    }
}
