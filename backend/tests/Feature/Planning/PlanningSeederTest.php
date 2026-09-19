<?php

namespace Tests\Feature\Planning;

use App\Models\AcademicYear;
use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\EmployeeTarget;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskProgress;
use App\Models\TaskReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.3-D — verifies the development planning & task seeders produce a
 * complete, realistic and idempotent dataset.
 */
class PlanningSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every canonical task status from docs/database/planning-task-management.md §11.
     *
     * @var list<string>
     */
    private const TASK_STATUSES = [
        'draft', 'assigned', 'in_progress', 'submitted',
        'under_review', 'completed', 'rejected', 'cancelled',
    ];

    /**
     * Every canonical assignment status.
     *
     * @var list<string>
     */
    private const ASSIGNMENT_STATUSES = [
        'assigned', 'accepted', 'in_progress', 'submitted',
        'completed', 'rejected', 'cancelled',
    ];

    public function test_development_planning_dataset_shapes_and_links_correctly(): void
    {
        $this->seed();

        $this->assertSame(2, AcademicYear::query()->count());
        $this->assertSame(1, AcademicYear::query()->where('status', 'completed')->count());

        $this->assertSame(6, DepartmentPlan::query()->count());
        $this->assertSame(8, DepartmentTarget::query()->count());

        $this->assertGreaterThanOrEqual(20, EmployeeTarget::query()->count());
        $this->assertGreaterThanOrEqual(5, EmployeeTarget::query()->whereNotNull('department_target_id')->count());

        $task = Task::query()->where('task_code', 'TASK-2026-0001')->firstOrFail();
        $this->assertNotNull($task->annual_plan_id);
        $this->assertNotNull($task->department_plan_id);
        $this->assertNotNull($task->department_target_id);
        $this->assertNotNull($task->employee_target_id);
        $this->assertNotNull($task->createdBy);
    }

    public function test_development_task_dataset_covers_all_statuses_and_scenarios(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(20, Task::query()->count());

        foreach (self::TASK_STATUSES as $status) {
            $this->assertGreaterThanOrEqual(1, Task::query()->where('status', $status)->count(), "Missing task status: {$status}");
        }

        foreach (self::ASSIGNMENT_STATUSES as $status) {
            $this->assertGreaterThanOrEqual(1, TaskAssignment::query()->where('status', $status)->count(), "Missing assignment status: {$status}");
        }

        $multi = Task::query()->where('task_code', 'TASK-2026-0001')->firstOrFail();
        $this->assertSame(2, $multi->assignments()->count());
        $this->assertSame(2, $multi->assignments()->distinct()->count('employee_id'));

        $parent = Task::query()->where('task_code', 'TASK-2026-0024')->firstOrFail();
        $this->assertSame(3, $parent->children()->count());

        $this->assertGreaterThanOrEqual(2, Task::query()->overdue()->count());

        $late = Task::query()->where('task_code', 'TASK-2026-0022')->firstOrFail();
        $this->assertNotNull($late->completed_at);
        $this->assertTrue($late->completed_at->greaterThan($late->due_date));
        $this->assertFalse($late->isOverdue());
    }

    public function test_development_task_activity_dataset_and_idempotency(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, TaskProgress::query()->count());
        $this->assertGreaterThan(0, TaskComment::query()->count());
        $this->assertGreaterThan(0, TaskAttachment::query()->count());

        foreach (['approved', 'rejected', 'needs_revision'] as $decision) {
            $this->assertGreaterThanOrEqual(1, TaskReview::query()->where('decision', $decision)->count(), "Missing review decision: {$decision}");
        }

        foreach (TaskProgress::query()->get() as $progress) {
            $this->assertSame($progress->assignment->task_id, $progress->task_id);
            $this->assertSame($progress->assignment->employee_id, $progress->employee_id);
        }

        $taskCount = Task::query()->count();
        $assignmentCount = TaskAssignment::query()->count();
        $progressCount = TaskProgress::query()->count();
        $reviewCount = TaskReview::query()->count();

        $this->seed();

        $this->assertSame($taskCount, Task::query()->count());
        $this->assertSame($assignmentCount, TaskAssignment::query()->count());
        $this->assertSame($progressCount, TaskProgress::query()->count());
        $this->assertSame($reviewCount, TaskReview::query()->count());
    }
}
