<?php

namespace Tests\Feature\Api\Reports;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\EmployeeTarget;
use App\Models\MonthlyAchievement;
use App\Models\Task;
use Illuminate\Support\Carbon;

class ReportCalculationTest extends ReportTestCase
{
    public function test_task_completion_metrics_reconcile_with_dashboard(): void
    {
        $dept = Department::factory()->create();
        $this->login($this->adminUser());

        $this->taskIn($dept, ['status' => 'in_progress']);
        for ($i = 0; $i < 8; $i++) {
            $this->taskIn($dept, [
                'status' => 'completed',
                'due_date' => now()->addDays(10),
                'completed_at' => now()->subDays(2),
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->taskIn($dept, [
                'status' => 'completed',
                'due_date' => now()->subDays(5),
                'completed_at' => now()->subDay(),
            ]);
        }
        $this->taskIn($dept, ['status' => 'draft']);
        $this->taskIn($dept, ['status' => 'in_progress', 'due_date' => now()->subDays(3)]);

        $response = $this->getJson('/api/v1/reports/task-completion')->assertOk();

        $data = $response->json('data');
        $this->assertEquals(13, $data['total_tasks']);
        $this->assertEquals(10, $data['completed_tasks']);
        $this->assertEquals(3, $data['pending_tasks']);
        $this->assertEquals(1, $data['overdue_tasks']);
        $this->assertEquals(8, $data['on_time']);
        $this->assertEquals(2, $data['late']);
        $this->assertScore(76.92, $data['completion_rate']);
        $this->assertScore(80.0, $data['on_time_rate']);

        $this->assertEquals('task-completion', $response->json('meta.report_type'));
        $this->assertCount((int) config('reports.trend_months', 6), $data['chart']['labels']);
    }

    public function test_month_period_constrains_completed_tasks(): void
    {
        $dept = Department::factory()->create();
        $this->login($this->adminUser());

        $thisMonth = $this->thisMonth();
        $lastMonth = $thisMonth->copy()->subMonthsNoOverflow(1);

        $this->taskIn($dept, ['status' => 'completed', 'due_date' => $thisMonth->copy()->addDays(3), 'completed_at' => $thisMonth->copy()->day(8)]);
        $this->taskIn($dept, ['status' => 'completed', 'due_date' => $lastMonth->copy()->addDays(3), 'completed_at' => $lastMonth->copy()->day(8)]);
        $this->taskIn($dept, ['status' => 'in_progress']);

        $all = $this->getJson('/api/v1/reports/task-completion')->json('data');
        $this->assertEquals(2, $all['completed_tasks']);

        $period = $this->getJson('/api/v1/reports/task-completion?month='.$thisMonth->month.'&year='.$thisMonth->year)
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $period['completed_tasks']);
        $this->assertEquals($thisMonth->format('Y-m'), $period['period']);
    }

    public function test_employee_performance_ranks_and_splits_on_time(): void
    {
        $dept = Department::factory()->create();
        $empA = $this->employee($dept);
        $empB = $this->employee($dept);

        $thisMonth = $this->thisMonth();

        // A: 3 completed (2 on-time + 1 late) + 1 pending = 4 total.
        $this->completedAssignment($empA, $dept, $thisMonth, $thisMonth->copy()->addDays(10));
        $this->completedAssignment($empA, $dept, $thisMonth, $thisMonth->copy()->addDays(10));
        $this->completedAssignment($empA, $dept, $thisMonth, $thisMonth->copy()->subDays(5));
        $this->openAssignment($empA, $dept, 'in_progress', $thisMonth->copy()->addDays(15));

        // B: 3 completed, all on time = 3 total.
        for ($i = 0; $i < 3; $i++) {
            $this->completedAssignment($empB, $dept, $thisMonth, $thisMonth->copy()->addDays(10));
        }

        $this->login($this->adminUser());

        $rows = $this->getJson('/api/v1/reports/employees?sort=completion_rate&direction=desc&per_page=50')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $rows);

        $rowA = collect($rows)->firstWhere('employee_id', $empA->id);
        $rowB = collect($rows)->firstWhere('employee_id', $empB->id);

        $this->assertEquals(1, $rowB['rank']);
        $this->assertScore(100.0, $rowB['completion_rate']);
        $this->assertEquals(3, $rowB['completed_tasks']);
        $this->assertEquals(3, $rowB['on_time']);
        $this->assertEquals(0, $rowB['late']);

        $this->assertEquals(2, $rowA['rank']);
        $this->assertEquals(4, $rowA['total_tasks']);
        $this->assertEquals(3, $rowA['completed_tasks']);
        $this->assertEquals(2, $rowA['on_time']);
        $this->assertEquals(1, $rowA['late']);
        $this->assertScore(75.0, $rowA['completion_rate']);
        $this->assertScore(66.67, $rowA['on_time_rate']);
    }

    public function test_employee_performance_detail_includes_recent_assignments(): void
    {
        $dept = Department::factory()->create();
        $emp = $this->employee($dept);
        $thisMonth = $this->thisMonth();

        $this->completedAssignment($emp, $dept, $thisMonth, $thisMonth->copy()->addDays(10));
        $this->openAssignment($emp, $dept, 'in_progress', $thisMonth->copy()->addDays(15));

        $this->login($this->adminUser());

        $data = $this->getJson("/api/v1/reports/employees/{$emp->id}")
            ->assertOk()
            ->json('data');

        $this->assertEquals($emp->id, $data['employee_id']);
        $this->assertCount(2, $data['recent_assignments']);
        $this->assertArrayHasKey('task_title', $data['recent_assignments'][0]);
    }

    public function test_department_performance_kpis(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $this->taskIn($deptA, ['status' => 'completed', 'due_date' => now()->addDays(5), 'completed_at' => now()->subDay()]);
        $this->taskIn($deptA, ['status' => 'in_progress']);
        $this->taskIn($deptB, ['status' => 'in_progress']);

        $this->login($this->adminUser());

        $rows = $this->getJson('/api/v1/reports/departments?sort=completed_tasks&direction=desc&per_page=50')
            ->assertOk()
            ->json('data');

        $rowA = collect($rows)->firstWhere('department_id', $deptA->id);
        $this->assertEquals(2, $rowA['total_tasks']);
        $this->assertEquals(1, $rowA['completed_tasks']);
        $this->assertScore(50.0, $rowA['completion_rate']);

        $detail = $this->getJson("/api/v1/reports/departments/{$deptA->id}")->assertOk()->json('data');
        $this->assertEquals($deptA->id, $detail['department_id']);
        $this->assertCount(2, $detail['recent_tasks']);
        $this->assertArrayHasKey('assignee_names', $detail['recent_tasks'][0]);
    }

    public function test_achievements_monthly_ranks_and_mirrors_to_table(): void
    {
        $year = now()->year;
        AcademicYear::factory()->create([
            'name' => 'Y'.$year,
            'start_date' => $year.'-01-01',
            'end_date' => $year.'-12-31',
        ]);

        $dept = Department::factory()->create();
        $empA = $this->employee($dept);
        $empB = $this->employee($dept);
        $thisMonth = $this->thisMonth();

        // A: 2/2 completed on time → overall 100.
        $this->completedAssignment($empA, $dept, $thisMonth, $thisMonth->copy()->addDays(10));
        $this->completedAssignment($empA, $dept, $thisMonth, $thisMonth->copy()->addDays(10));

        // B: 2 total, 1 completed on time → overall 70.
        $this->completedAssignment($empB, $dept, $thisMonth, $thisMonth->copy()->addDays(10));
        $this->openAssignment($empB, $dept, 'in_progress', $thisMonth->copy()->addDays(15));

        $this->login($this->adminUser());

        $rankings = $this->getJson('/api/v1/reports/achievements/monthly')->assertOk()->json('data.rankings');

        $this->assertCount(2, $rankings);
        $this->assertEquals($empA->id, $rankings[0]['employee_id']);
        $this->assertEquals(1, $rankings[0]['rank']);
        $this->assertTrue($rankings[0]['is_employee_of_month']);
        $this->assertScore(100.0, $rankings[0]['overall_score']);

        $this->assertEquals($empB->id, $rankings[1]['employee_id']);
        $this->assertEquals(2, $rankings[1]['rank']);
        $this->assertScore(70.0, $rankings[1]['overall_score']);

        $this->assertDatabaseHas('monthly_achievements', [
            'employee_id' => $empA->id,
            'month' => now()->month,
            'year' => now()->year,
            'rank' => 1,
            'is_employee_of_month' => true,
        ]);
        $this->assertDatabaseCount('monthly_achievements', 2);
    }

    public function test_achievements_employee_series_and_rank(): void
    {
        $dept = Department::factory()->create();
        $emp = $this->employee($dept);
        $thisMonth = $this->thisMonth();

        $this->completedAssignment($emp, $dept, $thisMonth, $thisMonth->copy()->addDays(10));

        $this->login($this->adminUser());

        $data = $this->getJson("/api/v1/reports/achievements/employees/{$emp->id}")
            ->assertOk()
            ->json('data');

        $this->assertEquals($emp->id, $data['employee_id']);
        $this->assertCount(6, $data['series']);
        $this->assertEquals(1, $data['current_rank']);
        $this->assertIsArray($data['badges']);
        $this->assertEquals(Carbon::create(now()->year, now()->month, 1)->format('Y-m'), $data['period']);
        $this->assertEquals(Carbon::create(now()->year, now()->month, 1)->format('Y-m'), $data['series'][5]['label']);
    }

    public function test_trend_reports_ten_pp_month_over_month(): void
    {
        $dept = Department::factory()->create();
        $emp = $this->employee($dept);
        $this->login($this->adminUser());

        $current = Carbon::create(now()->year, now()->month, 1);
        $prev = $current->copy()->subMonthsNoOverflow(1);

        // Previous month: 10 created, 5 completed → 50%.
        for ($i = 0; $i < 10; $i++) {
            $isCompleted = $i < 5;
            $task = Task::factory()->create([
                'department_id' => $dept->id,
                'status' => $isCompleted ? 'completed' : 'submitted',
                'due_date' => $prev->copy()->day(20),
                'completed_at' => $isCompleted ? $prev->copy()->day(10) : null,
            ]);
            $this->assertModelExists($task);
            $this->assertModelExists(
                $this->createAssignment($emp, $task, $prev, $isCompleted),
            );
        }

        // Current month: 10 created, 6 completed → 60%.
        for ($i = 0; $i < 10; $i++) {
            $isCompleted = $i < 6;
            $task = Task::factory()->create([
                'department_id' => $dept->id,
                'status' => $isCompleted ? 'completed' : 'submitted',
                'due_date' => $current->copy()->day(20),
                'completed_at' => $isCompleted ? $current->copy()->day(10) : null,
            ]);
            $this->createAssignment($emp, $task, $current, $isCompleted);
        }

        $data = $this->getJson('/api/v1/reports/trend/monthly-completions')->assertOk()->json('data');

        $this->assertCount(6, $data['labels']);
        $this->assertCount(6, $data['completion_rates']);
        $this->assertScore(50.0, $data['completion_rates'][4]);
        $this->assertScore(60.0, $data['completion_rates'][5]);
        $this->assertScore(10.0, $data['month_over_month_pp']);
    }

    public function test_target_vs_actual_summary(): void
    {
        $dept = Department::factory()->create();
        $emp = $this->employee($dept);

        $achieved = EmployeeTarget::factory()->create(['employee_id' => $emp->id]);
        $pending = EmployeeTarget::factory()->create(['employee_id' => $emp->id]);

        Task::factory()->create([
            'department_id' => $dept->id,
            'employee_target_id' => $achieved->id,
            'status' => 'completed',
            'due_date' => now()->addDays(5),
            'completed_at' => $this->thisMonth(),
        ]);
        Task::factory()->create([
            'department_id' => $dept->id,
            'employee_target_id' => $pending->id,
            'status' => 'in_progress',
        ]);

        $this->login($this->adminUser());

        $data = $this->getJson('/api/v1/reports/target-vs-actual')->assertOk()->json('data');

        $this->assertEquals(2, $data['target']);
        $this->assertEquals(1, $data['achieved']);
        $this->assertEquals(1, $data['remaining']);
        $this->assertScore(50.0, $data['achievement_rate']);
    }

    public function test_top_performers_daily_scope(): void
    {
        $dept = Department::factory()->create();
        $empA = $this->employee($dept);
        $empB = $this->employee($dept);
        $today = now();

        $this->completedAssignment($empA, $dept, $today, $today->copy()->addDays(5));
        $this->completedAssignment($empA, $dept, $today, $today->copy()->addDays(5));
        $this->completedAssignment($empB, $dept, $today, $today->copy()->addDays(5));

        $this->login($this->adminUser());

        $data = $this->getJson('/api/v1/reports/top-performers?scope=daily')->assertOk()->json('data');

        $this->assertEquals('daily', $data['scope']);
        $performers = $data['performers'];
        $this->assertCount(2, $performers);
        $this->assertEquals($empA->id, $performers[0]['employee_id']);
        $this->assertEquals(2, $performers[0]['completed_tasks']);
        $this->assertEquals(1, $performers[0]['rank']);
    }

    public function test_filters_are_revalidated_server_side(): void
    {
        $dept = Department::factory()->create();
        $this->login($this->adminUser());

        $this->getJson('/api/v1/reports/task-completion?from_date=2026-09-10&to_date=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_date');

        $this->getJson('/api/v1/reports/task-completion?status=does_not_exist')->assertUnprocessable();
        $this->getJson('/api/v1/reports/task-completion?sort=nope')->assertUnprocessable();
        $this->getJson('/api/v1/reports/task-completion?per_page=999')->assertUnprocessable();
        $this->getJson("/api/v1/reports/task-completion?department_id={$dept->id}")->assertOk();

        $this->getJson("/api/v1/reports/task-completion?department_id=999999")->assertUnprocessable();
    }

    private function createAssignment(object $employee, Task $task, Carbon $monthStart, bool $completed)
    {
        return \App\Models\TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => $completed ? 'completed' : 'submitted',
            'assigned_at' => $monthStart->copy()->day(5),
            'due_date' => $monthStart->copy()->day(20),
            'completed_at' => $completed ? $monthStart->copy()->day(10) : null,
        ]);
    }
}