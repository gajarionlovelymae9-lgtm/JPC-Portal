<?php

namespace Tests\Feature\Planning;

use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskProgress;
use App\Models\TaskReference;
use App\Models\TaskReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.3-D — verifies the planning & task factories build valid, correctly
 * related records and expose the expected states.
 */
class PlanningFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_factory_states_produce_expected_records(): void
    {
        $task = Task::factory()->create();

        $this->assertNotNull($task->task_code);
        $this->assertNotNull($task->department);
        $this->assertNotNull($task->createdBy);

        $this->assertSame('in_progress', Task::factory()->inProgress()->create()->status);
        $this->assertSame('assigned', Task::factory()->assigned()->create()->status);
        $this->assertSame('draft', Task::factory()->draft()->create()->status);

        $completed = Task::factory()->completed()->create();
        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);

        $this->assertTrue(Task::factory()->overdue()->create()->isOverdue());
    }

    public function test_task_assignment_factory_links_task_and_employee(): void
    {
        $assignment = TaskAssignment::factory()->create();

        $this->assertNotNull($assignment->task);
        $this->assertNotNull($assignment->employee);
        $this->assertNotNull($assignment->assignedBy);

        $completed = TaskAssignment::factory()->completed()->create();
        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_progress_factory_derives_task_and_employee_from_assignment(): void
    {
        $progress = TaskProgress::factory()->create();

        $this->assertNotNull($progress->assignment);
        $this->assertSame($progress->assignment->task_id, $progress->task_id);
        $this->assertSame($progress->assignment->employee_id, $progress->employee_id);
    }

    public function test_collaboration_factories_build_relationships(): void
    {
        $comment = TaskComment::factory()->create();
        $this->assertNotNull($comment->task);
        $this->assertNotNull($comment->user);

        $attachment = TaskAttachment::factory()->create();
        $this->assertNotNull($attachment->task);
        $this->assertNotNull($attachment->uploadedBy);

        $review = TaskReview::factory()->create();
        $this->assertNotNull($review->task);
        $this->assertNotNull($review->assignment);
        $this->assertNotNull($review->reviewedBy);
        $this->assertContains($review->decision, ['approved', 'rejected', 'needs_revision']);
    }

    public function test_department_plan_and_target_factories_share_department(): void
    {
        $plan = DepartmentPlan::factory()->create();
        $this->assertSame($plan->annualPlan->department_id, $plan->department_id);

        $target = DepartmentTarget::factory()->create();
        $this->assertSame($target->departmentPlan->department_id, $target->department_id);
        $this->assertNotNull($target->departmentPlan->annualPlan);
    }

    public function test_task_reference_factory_links_to_task(): void
    {
        $reference = TaskReference::factory()->create();

        $this->assertNotNull($reference->task);
        $this->assertNotNull($reference->title);
    }
}
