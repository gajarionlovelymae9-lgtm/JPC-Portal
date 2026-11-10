<?php

namespace Tests\Feature\Jobs;

use App\Enums\ReportExportStatus;
use App\Jobs\Reporting\GenerateReportExportJob;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reporting\Export\ReportRowBuilder;
use App\Services\Reporting\ReportingService;
use App\Services\Reports\ReportExportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 18 — Queue pipeline regression tests for the report export job.
 *
 * Confirms the queued export pipeline (QUEUED -> PROCESSING -> COMPLETED /
 * FAILED) produced by GenerateReportExportJob. A real (non-broken, wired)
 * background job outcome is asserted end to end; the dead `dispatchAiPipeline`
 * job references are covered as findings in the Phase 18 report, not asserted
 * here (they are inert and never dispatched).
 */
class GenerateReportExportJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake(ReportExport::DISK);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_queue_endpoint_dispatches_export_job(): void
    {
        Queue::fake();

        $user = $this->adminUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/exports', [
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'year' => now()->year,
            'month' => now()->month,
        ])->assertStatus(202);

        // The job carries no explicit queue name (default queue), which the
        // QueueFake records as null — assert presence, not the queue label.
        Queue::assertPushed(GenerateReportExportJob::class);
    }

    public function test_export_job_is_dispatched_with_bus_fake(): void
    {
        Bus::fake();

        $user = $this->adminUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/exports', [
            'report_type' => 'institution_performance',
            'format' => 'csv',
        ])->assertStatus(202);

        Bus::assertDispatched(GenerateReportExportJob::class);
    }

    public function test_job_completes_export_and_writes_file(): void
    {
        $reporting = $this->createMock(ReportingService::class);
        $reporting->method('generateInstitutionReport')->willReturn([
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_employees' => 3,
                'total_departments' => 1,
                'tasks_assigned' => 10,
                'tasks_completed' => 8,
                'task_completion_rate' => 80.0,
                'reports_submitted' => 2,
                'late_reports' => 1,
                'pending_reports' => 0,
                'approval_rate' => 100.0,
                'target_achievement_rate' => 75.0,
                'average_productivity' => 0.85,
                'data_quality_score' => 92.0,
            ],
        ]);

        $rowBuilder = new ReportRowBuilder;
        $exportService = app(ReportExportService::class);

        $user = $this->adminUser();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => ['year' => now()->year, 'month' => now()->month],
            'requested_at' => now(),
        ]);

        $job = new GenerateReportExportJob($export);
        $job->handle($reporting, $rowBuilder, $exportService);

        $export->refresh();

        $this->assertSame(ReportExportStatus::Completed->value, $export->status);
        $this->assertNotNull($export->file_path);
        $this->assertNotNull($export->file_name);
        $this->assertGreaterThan(0, $export->file_size);
        $this->assertGreaterThanOrEqual(1, $export->row_count);
        $this->assertNotNull($export->completed_at);
        $this->assertNull($export->error_message);

        $this->assertTrue(Storage::disk(ReportExport::DISK)->exists($export->file_path));
        $contents = Storage::disk(ReportExport::DISK)->get($export->file_path);
        $this->assertStringContainsString('Metric,Value', $contents);
    }

    public function test_job_resolves_first_active_department_for_bare_department_export(): void
    {
        $department = Department::factory()->create();

        $receivedDepartmentId = null;
        $reporting = $this->createMock(ReportingService::class);
        $reporting->method('generateDepartmentReport')->willReturnCallback(
            function (int $departmentId, array $filters, User $user) use (&$receivedDepartmentId): array {
                $receivedDepartmentId = $departmentId;

                return ['generated_at' => now()->toIso8601String()];
            },
        );

        $rowBuilderMock = $this->createMock(ReportRowBuilder::class);
        $rowBuilderMock->method('build')->willReturn([
            'columns' => ['Metric', 'Value'],
            'rows' => [['Metric' => 'employees', 'Value' => '1']],
        ]);

        $user = $this->adminUser();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'department_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => [],
            'requested_at' => now(),
        ]);

        $job = new GenerateReportExportJob($export);
        $job->handle($reporting, $rowBuilderMock, app(ReportExportService::class));

        $this->assertSame($department->id, $receivedDepartmentId);
        $this->assertNotSame(0, $receivedDepartmentId);

        $export->refresh();
        $this->assertSame(ReportExportStatus::Completed->value, $export->status);
    }

    public function test_job_marks_export_failed_when_writer_throws(): void
    {
        $reporting = $this->createMock(ReportingService::class);
        $reporting->method('generateInstitutionReport')->willReturn([
            'generated_at' => now()->toIso8601String(),
            'metrics' => [],
        ]);

        $rowBuilder = $this->createMock(ReportRowBuilder::class);
        $rowBuilder->method('build')->willReturn([
            'columns' => ['Metric', 'Value'],
            'rows' => [],
        ]);

        $boom = $this->createMock(ReportExportService::class);
        $boom->method('writeToFile')->willThrowException(new \RuntimeException('disk full'));

        $user = $this->adminUser();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => [],
            'requested_at' => now(),
        ]);

        $job = new GenerateReportExportJob($export);
        $job->handle($reporting, $rowBuilder, $boom);

        $export->refresh();

        $this->assertSame(ReportExportStatus::Failed->value, $export->status);
        $this->assertNotNull($export->error_message);
        $this->assertNull($export->file_path);
    }

    public function test_job_skips_terminal_export(): void
    {
        $reporting = $this->createMock(ReportingService::class);
        $reporting->expects($this->never())->method('generateInstitutionReport');

        $user = $this->adminUser();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Completed->value,
            'filters' => [],
            'requested_at' => now(),
        ]);

        $job = new GenerateReportExportJob($export);
        $job->handle($reporting, new ReportRowBuilder, app(ReportExportService::class));

        $export->refresh();
        $this->assertSame(ReportExportStatus::Completed->value, $export->status);
    }

    public function test_export_job_defines_unique_id_and_is_queued(): void
    {
        $user = User::factory()->create();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => [],
            'requested_at' => now(),
        ]);

        $job = new GenerateReportExportJob($export);

        $this->assertSame('report-export:'.$export->id, $job->uniqueId());
        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
    }

    public function test_employee_progress_export_job_keeps_employee_only_rows(): void
    {
        $dept = Department::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $dept->id]);

        $reporting = $this->createMock(ReportingService::class);
        $reporting->method('generateEmployeeReport')->willReturn([
            'employee' => [
                'name' => $employee->user->name,
                'employee_number' => $employee->employee_number,
                'department' => $dept->name,
            ],
            'metrics' => [
                'tasks_assigned' => 1,
                'tasks_completed' => 0,
                'task_completion_rate' => 0.0,
                'reports_expected' => 1,
                'reports_submitted' => 0,
                'report_completion_rate' => 0.0,
                'on_time_submission_rate' => 0.0,
                'approval_rate' => 0.0,
                'target_achievement_rate' => 0.0,
                'productivity_score' => null,
                'rating' => '',
            ],
        ]);

        $rowBuilder = new ReportRowBuilder;
        $exportService = app(ReportExportService::class);

        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'employee_progress',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => ['employee_id' => $employee->id],
            'requested_at' => now(),
        ]);

        $job = new GenerateReportExportJob($export);
        $job->handle($reporting, $rowBuilder, $exportService);

        $export->refresh();

        $this->assertSame(ReportExportStatus::Completed->value, $export->status);
        $this->assertSame(1, $export->row_count);
        $contents = Storage::disk(ReportExport::DISK)->get($export->file_path);
        $this->assertStringContainsString($employee->user->name, $contents);
    }
}
