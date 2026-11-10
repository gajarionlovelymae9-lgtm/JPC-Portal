<?php

namespace Tests\Feature\Reporting;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualityScanStatus;
use App\Enums\ReportStatus;
use App\Models\AcademicYear;
use App\Models\Badge;
use App\Models\DataQualityIssue;
use App\Models\DataQualityScan;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeTarget;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reporting Hub backend contract: the institution report must agree with the
 * canonical HR aggregation, the academic-year filter must behave as a real
 * date range, data quality must say N/A before the first completed scan, and
 * the management summary must work for non-monthly periods.
 */
class ReportingHubTest extends TestCase
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

    private function assignment(Employee $employee, string $status): TaskAssignment
    {
        return TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    private function submittedReport(Employee $employee, bool $late = false): Report
    {
        return Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'is_late' => $late,
            'submitted_at' => now(),
            'submission_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
    }

    public function test_institution_report_matches_canonical_hr_aggregation(): void
    {
        $admin = $this->adminUser();
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $empA1 = Employee::factory()->create(['department_id' => $deptA->id]);
        $empA2 = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        $this->assignment($empA1, 'completed');
        $this->assignment($empA1, 'cancelled');
        $this->assignment($empA2, 'assigned');

        $this->submittedReport($empA1);
        $this->submittedReport($empA1, true);
        Report::factory()->create([
            'employee_id' => $empA1->id,
            'status' => ReportStatus::Draft,
            'submitted_at' => null,
            'submission_date' => now()->toDateString(),
            'created_at' => now(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        $this->submittedReport($empB);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/institution?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonPath('data.scope', 'institution')
            ->assertJsonPath('data.summary.total_employees', 3)
            ->assertJsonPath('data.summary.active_employees', 3)
            ->assertJsonPath('data.summary.tasks_assigned', 3)
            ->assertJsonPath('data.summary.tasks_completed', 1)
            ->assertJsonPath('data.summary.task_completion_rate', 50)
            ->assertJsonPath('data.summary.reports_submitted', 3)
            ->assertJsonPath('data.summary.late_reports', 1)
            ->assertJsonPath('data.summary.awaiting_submission', 1)
            ->assertJsonPath('data.summary.data_quality_score', null)
            ->assertJsonPath('data.summary.data_quality_records_checked', 0);
    }

    public function test_institution_report_is_department_scoped(): void
    {
        $admin = $this->adminUser();
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();

        $empA = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        $this->assignment($empA, 'completed');
        $this->assignment($empA, 'assigned');
        $this->submittedReport($empA);
        $this->submittedReport($empB);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/institution?department_id='.$deptA->id)
            ->assertOk()
            ->assertJsonPath('data.scope', 'department')
            ->assertJsonPath('data.department_id', $deptA->id)
            ->assertJsonPath('data.summary.total_employees', 1)
            ->assertJsonPath('data.summary.tasks_assigned', 2)
            ->assertJsonPath('data.summary.tasks_completed', 1)
            ->assertJsonPath('data.summary.task_completion_rate', 50)
            ->assertJsonPath('data.summary.reports_submitted', 1);

        $this->getJson('/api/v1/reports/institution?department_id='.$deptB->id)
            ->assertOk()
            ->assertJsonPath('data.summary.total_employees', 1)
            ->assertJsonPath('data.summary.tasks_assigned', 0)
            ->assertJsonPath('data.summary.task_completion_rate', null)
            ->assertJsonPath('data.summary.reports_submitted', 1);
    }

    public function test_academic_year_filter_uses_its_start_and_end_dates(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create();

        $academicYear = AcademicYear::forceCreate([
            'name' => 'Reporting Hub AY',
            'start_date' => now()->startOfMonth()->subMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->submittedReport($employee);

        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Submitted,
            'is_late' => false,
            'submitted_at' => now()->subMonths(4),
            'submission_date' => now()->subMonths(4)->toDateString(),
            'deadline' => now()->subMonths(4)->addWeek()->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/institution?academic_year_id='.$academicYear->id)
            ->assertOk()
            ->assertJsonPath('data.period', 'Reporting Hub AY')
            ->assertJsonPath('data.period_key.type', 'academic_year')
            ->assertJsonPath('data.period_key.academic_year_id', $academicYear->id)
            ->assertJsonPath('data.summary.reports_submitted', 1);
    }

    public function test_data_quality_report_is_not_evaluated_without_a_completed_scan(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/data-quality')
            ->assertOk()
            ->assertJsonPath('data.summary.evaluated', false)
            ->assertJsonPath('data.summary.data_quality_score', null)
            ->assertJsonPath('data.summary.records_checked', 0)
            ->assertJsonPath('data.summary.issues_found', 0)
            ->assertJsonPath('data.summary.latest_scan_at', null)
            ->assertJsonPath('data.has_data', false)
            ->assertJsonPath('data.recent_scans', []);
    }

    public function test_data_quality_report_and_institution_report_use_latest_completed_scan_coverage(): void
    {
        $admin = $this->adminUser();
        Employee::factory()->create();

        DataQualityScan::create([
            'scan_type' => 'full',
            'status' => DataQualityScanStatus::COMPLETED,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'records_checked' => 25,
            'issues_found' => 3,
            'critical_count' => 0,
            'high_count' => 1,
            'medium_count' => 1,
            'low_count' => 1,
            'initiated_by' => $admin->id,
        ]);

        DataQualityScan::create([
            'scan_type' => 'incremental',
            'status' => DataQualityScanStatus::COMPLETED,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(10),
            'records_checked' => 30,
            'issues_found' => 4,
            'critical_count' => 1,
            'high_count' => 1,
            'medium_count' => 1,
            'low_count' => 1,
            'initiated_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/data-quality')
            ->assertOk()
            ->assertJsonPath('data.summary.evaluated', true)
            ->assertJsonPath('data.summary.records_checked', 30)
            ->assertJsonPath('data.summary.issues_found', 4)
            ->assertJsonPath('data.has_data', true);

        $this->assertNotNull(
            $this->getJson('/api/v1/reports/data-quality')->json('data.summary.latest_scan_at'),
        );

        $this->getJson('/api/v1/reports/institution')
            ->assertOk()
            ->assertJsonPath('data.summary.data_quality_records_checked', 30)
            ->assertJsonPath('data.summary.data_quality_issues_found', 4);
    }

    public function test_data_quality_by_category_is_a_list_of_label_count_rows(): void
    {
        // The Data Quality statistics service returns `by_category` as a keyed
        // map (`entity_type => count`). The reporting endpoint must normalise
        // it into a list of {label, count} rows — the React tabs call
        // `.map()` on it and would crash on an object.
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        DataQualityIssue::create([
            'issue_type' => 'MISSING_REQUIRED_PROFILE_DATA',
            'severity' => DataQualityIssueSeverity::HIGH,
            'status' => DataQualityIssueStatus::OPEN,
            'entity_type' => 'employee',
            'entity_id' => $employee->id,
            'department_id' => $department->id,
            'title' => 'Missing email',
            'description' => 'Employee email is empty.',
            'detected_by' => 'automated_rule',
            'fingerprint' => 'fp-employee-email-'.$employee->id,
        ]);
        DataQualityIssue::create([
            'issue_type' => 'TASK_WITHOUT_ASSIGNEE',
            'severity' => DataQualityIssueSeverity::MEDIUM,
            'status' => DataQualityIssueStatus::OPEN,
            'entity_type' => 'task',
            'entity_id' => 1,
            'title' => 'Orphaned task',
            'description' => 'No assignee.',
            'detected_by' => 'automated_rule',
            'fingerprint' => 'fp-task-assignee-1',
        ]);

        Sanctum::actingAs($admin);

        $payload = $this->getJson('/api/v1/reports/data-quality')->json('data.by_category');

        $this->assertIsList($payload);
        $this->assertCount(2, $payload);

        foreach ($payload as $row) {
            $this->assertArrayHasKey('label', $row);
            $this->assertArrayHasKey('count', $row);
            $this->assertIsInt($row['count']);
        }

        // Ordered by count descending: two different entity types, one each.
        $labels = array_column($payload, 'label');
        $this->assertContains('employee', $labels);
        $this->assertContains('task', $labels);
    }

    public function test_management_summary_supports_quarterly_periods_with_change(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create();
        $academicYear = AcademicYear::factory()->create();

        $currentQuarterStart = now()->startOfQuarter();
        $previousQuarterStart = now()->startOfQuarter()->subQuarter();

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => $academicYear->id,
            'period_type' => 'quarterly',
            'period_start' => $currentQuarterStart->copy()->addDays(5)->toDateString(),
            'period_end' => $currentQuarterStart->copy()->addDays(20)->toDateString(),
            'completion_rate' => 90.0,
            'on_time_rate' => 85.0,
            'approval_rate' => 92.0,
            'target_achievement_rate' => 80.0,
            'productivity_score' => 90.0,
            'overall_score' => 90.0,
        ]);

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => $academicYear->id,
            'period_type' => 'quarterly',
            'period_start' => $previousQuarterStart->copy()->addDays(5)->toDateString(),
            'period_end' => $previousQuarterStart->copy()->addDays(20)->toDateString(),
            'completion_rate' => 80.0,
            'on_time_rate' => 75.0,
            'approval_rate' => 88.0,
            'target_achievement_rate' => 70.0,
            'productivity_score' => 80.0,
            'overall_score' => 80.0,
        ]);

        $quarter = (int) ceil(now()->month / 3);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/management-summary?period_type=quarterly&year='.now()->year.'&quarter='.$quarter)
            ->assertOk()
            ->assertJsonPath('data.period_type', 'quarterly')
            ->assertJsonPath('data.has_data', true)
            ->assertJsonPath('data.change.average_productivity_change', 10)
            ->assertJsonPath('data.metrics.average_productivity', 90);
    }

    public function test_management_summary_reports_no_baseline_instead_of_zero_change(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create();
        $academicYear = AcademicYear::factory()->create();

        EmployeePerformanceRecord::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => $academicYear->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'completion_rate' => 90.0,
            'on_time_rate' => 85.0,
            'approval_rate' => 92.0,
            'target_achievement_rate' => 80.0,
            'productivity_score' => 88.0,
            'overall_score' => 88.0,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/management-summary?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonPath('data.change', null)
            ->assertJsonPath('data.metrics.average_productivity', 88)
            ->assertJsonPath('data.metrics.data_quality_score', null);
    }

    public function test_management_summary_respects_the_admin_department_filter(): void
    {
        $admin = $this->adminUser();
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $employeeA = Employee::factory()->create(['department_id' => $deptA->id]);
        $employeeB = Employee::factory()->create(['department_id' => $deptB->id]);
        $academicYear = AcademicYear::factory()->create();

        foreach ([$employeeA->id => 70.0, $employeeB->id => 95.0] as $employeeId => $score) {
            EmployeePerformanceRecord::forceCreate([
                'employee_id' => $employeeId,
                'academic_year_id' => $academicYear->id,
                'period_type' => 'monthly',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'completion_rate' => $score,
                'on_time_rate' => $score,
                'approval_rate' => $score,
                'target_achievement_rate' => $score,
                'productivity_score' => $score,
                'overall_score' => $score,
            ]);
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/management-summary?department_id='.$deptA->id.'&year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->assertJsonPath('data.top_performer.employee_id', $employeeA->id)
            ->assertJsonPath('data.top_performer.department', $deptA->name)
            ->assertJsonPath('data.metrics.average_productivity', 70);
    }

    public function test_academic_year_lookup_does_not_change_shared_period_defaults(): void
    {
        $admin = $this->adminUser();
        Employee::factory()->create();
        $academicYear = AcademicYear::factory()->create();

        Sanctum::actingAs($admin);

        $withoutFilter = $this->getJson('/api/v1/reports/institution')->assertOk()->json('data');
        $withUnknownYear = $this->getJson('/api/v1/reports/institution?academic_year_id='.$academicYear->id)
            ->assertOk()
            ->json('data');

        $this->assertSame($withoutFilter['summary']['total_employees'], $withUnknownYear['summary']['total_employees']);
        $this->assertNotSame($withoutFilter['period'], $withUnknownYear['period']);
        $this->assertSame(Carbon::now()->year, (int) $withoutFilter['period_key']['year'] ?? null);
    }

    public function test_late_report_list_matches_the_institution_late_counter(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        // One report was flagged late at submission, one was handed in on time.
        $this->submittedReport($employee, true);
        $this->submittedReport($employee);

        // And one draft was never submitted although its deadline already passed.
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Draft,
            'is_late' => false,
            'submitted_at' => null,
            'submission_date' => null,
            'created_at' => now(),
            'deadline' => now()->subDays(3)->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $params = '?year='.now()->year.'&month='.now()->month;
        $institution = $this->getJson('/api/v1/reports/institution'.$params)
            ->assertOk()
            ->json('data');
        $late = $this->getJson('/api/v1/reports/late'.$params)
            ->assertOk()
            ->json('data');

        // The Hub KPI and the Late Submissions list must count the same rows.
        $this->assertSame(2, $institution['summary']['late_reports']);
        $this->assertSame($institution['summary']['late_reports'], $late['summary']['late_reports']);
        $this->assertSame(1, $institution['summary']['late_submitted']);
        $this->assertSame(1, $institution['summary']['late_outstanding']);
        $this->assertCount(2, $late['reports']);

        $outstanding = collect($late['reports'])->firstWhere('is_outstanding', true);
        $this->assertNotNull($outstanding);
        $this->assertTrue($outstanding['is_outstanding']);
        $this->assertSame(ReportStatus::Draft->label(), $outstanding['status_label']);
    }

    public function test_employee_performance_rows_expose_report_and_target_aggregates(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $achievedTarget = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 4,
            'status' => 'achieved',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 6,
            'status' => 'active',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        // Two completed tasks operationalize the targets: 2 / 10 = 20%.
        Task::factory()->count(2)->create([
            'employee_target_id' => $achievedTarget->id,
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
        ]);

        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Approved,
            'is_late' => false,
            'submitted_at' => now(),
            'submission_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Rejected,
            'is_late' => true,
            'submitted_at' => now(),
            'submission_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        // Drafts are excluded from the submitted aggregate.
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Draft,
            'is_late' => false,
            'submitted_at' => null,
            'submission_date' => null,
            'created_at' => now(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $row = collect($this->getJson('/api/v1/reports/employees?per_page=50')->assertOk()->json('data'))
            ->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($row);
        $this->assertSame(2, $row['reports_submitted']);
        $this->assertSame(1, $row['late_reports']);
        $this->assertEquals(50, $row['approval_rate']);
        $this->assertEquals(20, $row['target_achievement']);
        $this->assertSame(2, $row['total_targets']);
        $this->assertSame(1, $row['achieved_targets']);
    }

    public function test_department_performance_rows_expose_report_and_target_aggregates(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 10,
            'status' => 'in_progress',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        // Four completed tasks of a ten-unit target: 40%.
        Task::factory()->count(4)->create([
            'employee_target_id' => $target->id,
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
        ]);

        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Approved,
            'is_late' => false,
            'submitted_at' => now(),
            'submission_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::RevisionRequired,
            'is_late' => true,
            'submitted_at' => now(),
            'submission_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        Report::factory()->create([
            'employee_id' => $employee->id,
            'status' => ReportStatus::Draft,
            'is_late' => false,
            'submitted_at' => null,
            'submission_date' => null,
            'created_at' => now(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $row = collect($this->getJson('/api/v1/reports/departments?per_page=50')->assertOk()->json('data'))
            ->firstWhere('department_id', $department->id);

        $this->assertNotNull($row);
        $this->assertSame(2, $row['reports_submitted']);
        $this->assertSame(1, $row['late_reports']);
        $this->assertEquals(50, $row['approval_rate']);
        // One of the two submitted reports was late.
        $this->assertEquals(50, $row['on_time_submission_rate']);
        $this->assertEquals(40, $row['target_achievement']);
        $this->assertSame(1, $row['total_targets']);
        $this->assertSame(0, $row['achieved_targets']);
    }

    public function test_target_report_exposes_actual_progress_and_remaining_target(): void
    {
        $admin = $this->adminUser();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'title' => 'Enrolment target',
            'target_value' => 150,
            'target_unit' => 'students',
            'status' => 'in_progress',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        Task::factory()->count(3)->create([
            'employee_target_id' => $target->id,
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($admin);

        $data = $this->getJson('/api/v1/reports/targets?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $data['total_targets']);
        $this->assertSame(0, $data['achieved_targets']);
        $this->assertSame(1, $data['in_progress']);
        $this->assertEquals(150, $data['total_target_value']);
        $this->assertSame(3, $data['total_actual']);
        $this->assertEquals(2, $data['achievement_percentage']);

        $row = $data['targets'][0];
        $this->assertSame('Enrolment target', $row['title']);
        $this->assertSame('in_progress', $row['status']);
        $this->assertSame('In progress', $row['status_label']);
        $this->assertEquals(150, $row['target_value']);
        $this->assertSame('students', $row['target_unit']);
        $this->assertSame(3, $row['actual_value']);
        $this->assertEquals(2, $row['achievement_percentage']);
        $this->assertEquals(147, $row['remaining_target']);
        $this->assertSame('monthly', $row['period_type']);
    }

    public function test_pending_report_distinguishes_review_revision_and_overdue_states(): void
    {
        // Freeze "today" a couple of weeks into the month so month-boundary
        // runs (e.g. on the 1st) cannot flip these reports into another
        // period or make the overdue deadline equal to today.
        $frozen = now()->startOfMonth()->addDays(14);
        Carbon::setTestNow($frozen);

        $admin = $this->adminUser();
        $employee = Employee::factory()->create();

        Report::factory()->create([
            'employee_id' => $employee->id,
            'title' => 'Awaiting approval report',
            'status' => ReportStatus::Submitted,
            'submitted_at' => $frozen->copy()->subDay(),
            'submission_date' => $frozen->copy()->subDay()->toDateString(),
            'deadline' => $frozen->copy()->addDays(3)->toDateString(),
        ]);

        Report::factory()->create([
            'employee_id' => $employee->id,
            'title' => 'Returned for revision report',
            'status' => ReportStatus::RevisionRequired,
            'submitted_at' => $frozen->copy()->subDays(4),
            'submission_date' => $frozen->copy()->subDays(4)->toDateString(),
            'deadline' => $frozen->copy()->subDays(2)->toDateString(),
        ]);

        // Approved reports are not pending and must not inflate the list.
        Report::factory()->create([
            'employee_id' => $employee->id,
            'title' => 'Approved report',
            'status' => ReportStatus::Approved,
            'submitted_at' => $frozen->copy()->subDays(6),
            'submission_date' => $frozen->copy()->subDays(6)->toDateString(),
            'deadline' => $frozen->copy()->subDays(5)->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $data = $this->getJson('/api/v1/reports/pending?year='.$frozen->year.'&month='.$frozen->month)
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $data['summary']['pending_reports']);
        $this->assertSame(1, $data['summary']['awaiting_review']);
        $this->assertSame(1, $data['summary']['revision_required']);

        $rows = collect($data['reports'])->keyBy('title');
        $this->assertFalse($rows->has('Approved report'));

        $awaiting = $rows['Awaiting approval report'];
        $this->assertSame('submitted', $awaiting['status']);
        $this->assertSame(ReportStatus::Submitted->label(), $awaiting['status_label']);
        // Awaiting approval is not "not submitted".
        $this->assertSame('pending_review', $awaiting['category']);
        $this->assertSame('Awaiting reviewer approval', $awaiting['required_action']);
        $this->assertFalse($awaiting['is_overdue']);
        $this->assertGreaterThan(0, $awaiting['days_remaining']);

        $revision = $rows['Returned for revision report'];
        $this->assertSame(ReportStatus::RevisionRequired->label(), $revision['status_label']);
        $this->assertSame('revision_required', $revision['category']);
        $this->assertSame('Revise and resubmit the report', $revision['required_action']);
        $this->assertTrue($revision['is_overdue']);
        $this->assertGreaterThan(0, $revision['days_overdue']);
        $this->assertSame(0, $revision['days_remaining']);
    }

    public function test_table_report_pagination_accepts_the_100_row_ceiling_and_rejects_more(): void
    {
        // The React hub pins `per_page: 100` (the server ceiling) on every
        // table report. A regression that sent anything larger produced the
        // repeated "The per page field must not be greater than 100."
        // validation failure on every tab; this locks the contract at both
        // ends: 100 is honoured, 101 is a 422.
        $admin = $this->adminUser();
        Employee::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/employees?per_page=100&page=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);

        $this->getJson('/api/v1/reports/employees?per_page=101&page=1')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_achievement_report_breakdowns_are_lists_not_keyed_maps(): void
    {
        // HrAchievementService::reports() builds by_category / by_tier with
        // `Collection::map()->all()`, which preserves the source keys and used
        // to serialize as a JSON object. The React page then called `.map()`
        // on it and crashed with "data.by_category.map is not a function".
        // The wire contract must always be a JSON list.
        $admin = $this->adminUser();
        $academicYear = AcademicYear::factory()->create();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $badges = [
            Badge::create([
                'name' => 'Progress Star',
                'code' => 'PROGRESS_STAR',
                'category' => 'Progress',
                'tier' => 'gold',
                'is_active' => true,
            ]),
            Badge::create([
                'name' => 'Team Player',
                'code' => 'TEAM_PLAYER',
                'category' => 'Teamwork',
                'tier' => 'silver',
                'is_active' => true,
            ]),
        ];

        foreach ($badges as $index => $badge) {
            EmployeeBadge::forceCreate([
                'employee_id' => $employee->id,
                'badge_id' => $badge->id,
                'academic_year_id' => $academicYear->id,
                'period_start' => '2026-01-0'.($index + 1),
                'period_end' => '2026-01-31',
                'awarded_at' => now(),
                'verification_status' => 'verified',
            ]);
        }

        Sanctum::actingAs($admin);

        $data = $this->getJson('/api/v1/hr/achievement-badges/reports')
            ->assertOk()
            ->json('data');

        $this->assertIsList($data['by_category']);
        $this->assertIsList($data['by_tier']);
        $this->assertCount(2, $data['by_category']);
        $this->assertCount(2, $data['by_tier']);

        foreach ($data['by_category'] as $row) {
            $this->assertArrayHasKey('category', $row);
            $this->assertArrayHasKey('total', $row);
            $this->assertIsInt($row['total']);
        }

        foreach ($data['by_tier'] as $row) {
            $this->assertArrayHasKey('tier', $row);
            $this->assertArrayHasKey('total', $row);
            $this->assertIsInt($row['total']);
        }
    }
}
