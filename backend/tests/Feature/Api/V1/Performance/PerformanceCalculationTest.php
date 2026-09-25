<?php

namespace Tests\Feature\Api\V1\Performance;

use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeTarget;
use App\Models\PerformancePeriod;
use App\Models\Position;
use App\Models\Report;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Performance\PerformanceSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deptHead;
    protected User $employeeUser;
    protected User $otherEmployeeUser;
    protected Employee $employee;
    protected Employee $otherEmployee;
    protected Department $department;
    protected AcademicYear $academicYear;
    protected AnnualPlan $annualPlan;
    protected PerformancePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\BadgeSeeder::class);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $deptHeadRole = Role::where('name', 'department_head')->firstOrFail();
        $employeeRole = Role::where('name', 'employee')->firstOrFail();

        $this->department = Department::create([
            'name' => 'College of Computer Studies',
            'code' => 'CCS',
            'status' => 'active',
        ]);

        $position = Position::create([
            'department_id' => $this->department->id,
            'name' => 'Instructor I',
            'code' => 'INST-1',
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => 'AY 2026-2027',
            'code' => 'AY2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
        ]);

        $this->annualPlan = AnnualPlan::create([
            'academic_year_id' => $this->academicYear->id,
            'department_id' => $this->department->id,
            'title' => 'CCS Annual Plan 2026-2027',
            'status' => 'approved',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
        ]);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->deptHead = User::factory()->create(['role_id' => $deptHeadRole->id]);

        $this->employeeUser = User::factory()->create(['role_id' => $employeeRole->id]);
        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'department_id' => $this->department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-TEST-001',
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'email' => $this->employeeUser->email,
            'status' => 'active',
            'date_hired' => '2025-01-01',
        ]);

        $this->otherEmployeeUser = User::factory()->create(['role_id' => $employeeRole->id]);
        $this->otherEmployee = Employee::create([
            'user_id' => $this->otherEmployeeUser->id,
            'department_id' => $this->department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-TEST-002',
            'first_name' => 'Teacher',
            'last_name' => 'Two',
            'email' => $this->otherEmployeeUser->email,
            'status' => 'active',
            'date_hired' => '2025-01-01',
        ]);

        $this->period = PerformancePeriod::findOrCreateMonthly(2026, 8);
    }

    /**
     * Test Case 1: Critical mathematical test case (Section 57).
     * 10 assigned tasks, 9 completed (90%)
     * 10 expected reports, 10 submitted (100%)
     * 8 on-time (80%)
     * 9 approved (90%)
     * 90% target achievement
     *
     * Expected score: (90 * 0.30) + (100 * 0.20) + (80 * 0.20) + (90 * 0.15) + (90 * 0.15)
     *               = 27 + 20 + 16 + 13.5 + 13.5 = 90.00%
     * Expected rating: Excellent
     */
    public function test_critical_performance_calculation_formula_and_weights(): void
    {
        $startDate = Carbon::create(2026, 8, 1)->startOfDay();
        $dueDate = Carbon::create(2026, 8, 20)->endOfDay();

        // 1. Create 10 Tasks and Assignments (9 completed, 1 pending)
        for ($i = 1; $i <= 10; $i++) {
            $task = Task::create([
                'annual_plan_id' => $this->annualPlan->id,
                'department_id' => $this->department->id,
                'created_by' => $this->admin->id,
                'title' => "Task {$i}",
                'due_date' => $dueDate,
                'status' => $i <= 9 ? 'completed' : 'in_progress',
            ]);

            $assignment = TaskAssignment::create([
                'task_id' => $task->id,
                'employee_id' => $this->employee->id,
                'assigned_by' => $this->admin->id,
                'assigned_at' => $startDate,
                'due_date' => $dueDate,
                'status' => $i <= 9 ? 'completed' : 'in_progress',
                'progress_percentage' => $i <= 9 ? 100 : 50,
                'completed_at' => $i <= 9 ? $startDate->copy()->addDays(5) : null,
            ]);

            // 2. Create 10 Reports: 8 on-time (submitted before due), 2 late (submitted after due)
            // 9 approved, 1 rejected
            $isLate = ($i > 8);
            $submittedAt = $isLate ? $dueDate->copy()->addDays(2) : $dueDate->copy()->subDays(2);
            $status = ($i <= 9) ? 'approved' : 'rejected';

            Report::create([
                'task_id' => $task->id,
                'employee_id' => $this->employee->id,
                'title' => "Report {$i}",
                'content' => "Content for report {$i}",
                'status' => $status,
                'is_late' => $isLate,
                'version' => 1,
                'submitted_at' => $submittedAt,
                'approved_at' => $status === 'approved' ? $submittedAt->copy()->addDay() : null,
                'rejected_at' => $status === 'rejected' ? $submittedAt->copy()->addDay() : null,
            ]);
        }

        // 3. Create 10 Targets (9 completed, 1 in_progress => 90% achievement)
        for ($j = 1; $j <= 10; $j++) {
            EmployeeTarget::create([
                'annual_plan_id' => $this->annualPlan->id,
                'employee_id' => $this->employee->id,
                'title' => "Target {$j}",
                'target_value' => 100.00,
                'status' => $j <= 9 ? 'completed' : 'in_progress',
                'created_at' => $startDate,
            ]);
        }

        // Calculate
        $snapshotService = app(PerformanceSnapshotService::class);
        $record = $snapshotService->calculateEmployeePerformance($this->employee, $this->period);

        $this->assertEquals(90.00, (float) $record->completion_rate);
        $this->assertEquals(100.00, (float) $record->report_completion_rate);
        $this->assertEquals(80.00, (float) $record->on_time_rate);
        $this->assertEquals(90.00, (float) $record->approval_rate);
        $this->assertEquals(90.00, (float) $record->target_achievement_rate);
        $this->assertEquals(90.00, (float) $record->productivity_score);
        $this->assertEquals('Excellent', $record->rating?->value ?? (string) $record->rating);

        $this->assertCount(5, $record->breakdowns);
        $taskBreakdown = $record->breakdowns->firstWhere('metric_key', 'task_completion');
        $this->assertEquals(27.00, (float) $taskBreakdown->weighted_value);
        $reportBreakdown = $record->breakdowns->firstWhere('metric_key', 'report_completion');
        $this->assertEquals(20.00, (float) $reportBreakdown->weighted_value);
        $onTimeBreakdown = $record->breakdowns->firstWhere('metric_key', 'on_time_submission');
        $this->assertEquals(16.00, (float) $onTimeBreakdown->weighted_value);
        $approvalBreakdown = $record->breakdowns->firstWhere('metric_key', 'report_approval');
        $this->assertEquals(13.50, (float) $approvalBreakdown->weighted_value);
        $targetBreakdown = $record->breakdowns->firstWhere('metric_key', 'target_achievement');
        $this->assertEquals(13.50, (float) $targetBreakdown->weighted_value);
    }

    /**
     * Test Case 2: Zero-division handling.
     */
    public function test_zero_division_returns_safe_defaults(): void
    {
        $snapshotService = app(PerformanceSnapshotService::class);
        $record = $snapshotService->calculateEmployeePerformance($this->otherEmployee, $this->period);

        $this->assertEquals(0.00, (float) $record->completion_rate);
        $this->assertEquals(0.00, (float) $record->report_completion_rate);
        $this->assertEquals(0.00, (float) $record->on_time_rate);
        $this->assertEquals(0.00, (float) $record->approval_rate);
        $this->assertEquals(0.00, (float) $record->target_achievement_rate);
        $this->assertEquals(0.00, (float) $record->productivity_score);
        $this->assertEquals('Unsatisfactory', $record->rating?->value ?? (string) $record->rating);
        $this->assertFalse(is_nan((float) $record->productivity_score));
    }

    /**
     * Test Case 3: Historical integrity.
     */
    public function test_historical_performance_record_preserves_its_score(): void
    {
        $julyPeriod = PerformancePeriod::findOrCreateMonthly(2026, 7);

        // Create July record with 82% score (verdict fields are computed by the
        // snapshot service, so forceFill is used to seed the historical fixture)
        $julyRecord = EmployeePerformanceRecord::forceCreate([
            'employee_id' => $this->employee->id,
            'academic_year_id' => $this->academicYear->id,
            'performance_period_id' => $julyPeriod->id,
            'period_type' => 'monthly',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'completion_rate' => 82.00,
            'productivity_score' => 82.00,
            'rating' => 'Very Good',
            'calculation_version' => 'v1.0',
        ]);

        // Add August data and calculate August
        $snapshotService = app(PerformanceSnapshotService::class);
        $augustRecord = $snapshotService->calculateEmployeePerformance($this->employee, $this->period);

        // Verify July is unchanged
        $julyRecordFresh = EmployeePerformanceRecord::find($julyRecord->id);
        $this->assertEquals(82.00, (float) $julyRecordFresh->productivity_score);
        $this->assertEquals('Very Good', $julyRecordFresh->rating?->value ?? (string) $julyRecordFresh->rating);
    }

    /**
     * Test Case 4: API endpoints and RBAC security.
     */
    public function test_employee_can_fetch_own_performance_but_not_others(): void
    {
        $snapshotService = app(PerformanceSnapshotService::class);
        $snapshotService->calculateEmployeePerformance($this->employee, $this->period);
        $snapshotService->calculateEmployeePerformance($this->otherEmployee, $this->period);

        // 1. Employee fetches own performance
        Sanctum::actingAs($this->employeeUser);
        $response = $this->getJson('/api/v1/performance/me?year=2026&month=8');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.id', $this->employee->id);

        // 2. Employee cannot fetch other employee's performance
        $responseOther = $this->getJson("/api/v1/performance/employees/{$this->otherEmployee->id}?year=2026&month=8");
        $responseOther->assertForbidden();

        // 3. Admin can fetch other employee's performance
        Sanctum::actingAs($this->admin);
        $responseAdmin = $this->getJson("/api/v1/performance/employees/{$this->otherEmployee->id}?year=2026&month=8");
        $responseAdmin->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * Test Case 5: Leaderboard and duplicate badge prevention.
     */
    public function test_rankings_and_badge_deduplication(): void
    {
        $snapshotService = app(PerformanceSnapshotService::class);
        $result = $snapshotService->calculateMonthlyPeriod(2026, 8);

        $this->assertGreaterThanOrEqual(1, $result['processed_employees']);

        // Check rankings API
        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/v1/performance/rankings?year=2026&month=8');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['period', 'total', 'rankings']]);

        // Verify badge deduplication: running calculation again does not duplicate badges
        $badgesBefore = EmployeeBadge::where('employee_id', $this->employee->id)->count();
        $snapshotService->calculateMonthlyPeriod(2026, 8);
        $badgesAfter = EmployeeBadge::where('employee_id', $this->employee->id)->count();

        $this->assertEquals($badgesBefore, $badgesAfter);
    }
}
