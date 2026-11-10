<?php

namespace Tests\Unit\Models;

use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\ReportVersion;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 18 — Report model regression tests (relationships, casts, scopes
 * and enum-backed status behaviour).
 */
class ReportModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function report(): Report
    {
        $dept = Department::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $dept->id]);
        $task = Task::factory()->create(['department_id' => $dept->id]);

        return Report::factory()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);
    }

    #[Test]
    public function status_is_cast_to_enum(): void
    {
        $report = $this->report();
        $this->assertInstanceOf(ReportStatus::class, $report->status);
        $this->assertSame(ReportStatus::Submitted, $report->status);
    }

    #[Test]
    public function boolean_is_late_is_casted(): void
    {
        $report = $this->report();
        $report->forceFill(['is_late' => true])->save();

        $fresh = $report->fresh();
        $this->assertIsBool($fresh->is_late);
        $this->assertTrue($fresh->is_late);
    }

    #[Test]
    public function dates_are_cast(): void
    {
        $report = $this->report();
        $this->assertInstanceOf(Carbon::class, $report->submitted_at);
        $this->assertInstanceOf(Carbon::class, $report->deadline);
    }

    #[Test]
    public function related_models_resolve(): void
    {
        $report = $this->report();

        $this->assertInstanceOf(Task::class, $report->task);
        $this->assertInstanceOf(Employee::class, $report->employee);

        ReportVersion::factory()->create(['report_id' => $report->id, 'version_number' => 2, 'submitted_by' => $report->employee->user_id]);
        ReportReview::factory()->create(['report_id' => $report->id]);

        $this->assertCount(1, $report->versions);
        $this->assertCount(1, $report->reviews);
    }

    #[Test]
    public function soft_deletes_report(): void
    {
        $report = $this->report();
        $report->delete();

        $this->assertSoftDeleted('reports', ['id' => $report->id]);
        $this->assertNull(Report::find($report->id));
    }

    #[Test]
    public function scope_draft_returns_only_drafts(): void
    {
        $this->report(); // submitted
        $draft = Report::factory()->draft()->create();

        $ids = Report::draft()->pluck('id')->all();
        $this->assertSame([$draft->id], $ids);
    }

    #[Test]
    public function scope_pending_includes_active_workflow_statuses(): void
    {
        $this->report(); // submitted counts as pending
        Report::factory()->approved()->create();

        $this->assertSame(1, Report::pending()->count());
        $this->assertSame(1, Report::approved()->count());
    }

    #[Test]
    public function scope_late_only_flags_late_reports(): void
    {
        $this->report();
        Report::factory()->create(['is_late' => true, 'status' => ReportStatus::Submitted->value]);

        $this->assertSame(1, Report::late()->count());
    }

    #[Test]
    public function scope_approved_and_rejected_partition_finalised_reports(): void
    {
        $approved = Report::factory()->approved()->create();
        $rejected = Report::factory()->create(['status' => ReportStatus::Rejected->value]);

        $this->assertSame([$approved->id], Report::approved()->pluck('id')->all());
        $this->assertSame([$rejected->id], Report::rejected()->pluck('id')->all());
    }

    #[Test]
    public function top_level_fields_are_fillable(): void
    {
        $report = $this->report();
        $report->forceFill([
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'references' => 'Updated references',
            'deadline' => now()->addWeek()->toDateString(),
        ])->save();

        $fresh = $report->fresh();
        $this->assertSame('Updated Title', $fresh->title);
        $this->assertSame('Updated content', $fresh->content);
        $this->assertSame('Updated references', $fresh->references);
    }
}
