<?php

namespace Tests\Feature\Services;

use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\TaskAssignment;
use App\Models\TaskProgress;
use App\Models\User;
use App\Services\TaskManagement\TaskAssignmentService;
use App\Services\TaskManagement\TaskProgressService;
use App\Services\TaskManagement\TaskReviewService;
use App\Services\TaskManagement\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\PlanningTestCase;

class TaskProgressConsistencyTest extends PlanningTestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $role = RoleModel::query()->firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Admin', 'description' => 'Administrator'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function departmentHeadUser(): User
    {
        $role = RoleModel::query()->firstOrCreate(
            ['name' => 'department_head'],
            ['label' => 'Department Head', 'description' => 'Department Head'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function employeeWithUser(User $user): Employee
    {
        return Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => $this->context()['department']->id,
            'position_id' => $this->context()['position']->id,
        ]);
    }

    public function test_progress_update_saves_value_and_history_without_completing_work(): void
    {
        $task = $this->makeTask(['status' => 'assigned']);
        $assignment = $this->makeAssignment($task, [
            'status' => 'in_progress',
            'progress_percentage' => 0,
        ]);

        $progress = (new TaskProgressService)->updateProgress(
            $assignment,
            40.0,
            'Started the report',
            $this->context()['employee'],
        );

        $assignment->refresh();
        $task->refresh();

        $this->assertSame(40.0, (float) $assignment->progress_percentage);
        $this->assertNull($assignment->completed_at);
        $this->assertSame('in_progress', $assignment->status);
        $this->assertSame(40.0, (float) $progress->new_percentage);
        $this->assertSame('in_progress', $progress->status);
        $this->assertSame('in_progress', $task->status);
    }

    public function test_approval_forces_100_percent_and_stamps_assignment_and_task_completion(): void
    {
        $task = $this->makeTask(['status' => 'submitted']);
        $assignment = $this->makeAssignment($task, [
            'status' => 'submitted',
            'progress_percentage' => 2,
        ]);

        (new TaskReviewService)->approve($assignment, $this->adminUser(), 'Approved.');

        $assignment->refresh();
        $task->refresh();

        $this->assertSame('completed', $assignment->status);
        $this->assertSame(100.0, (float) $assignment->progress_percentage);
        $this->assertNotNull($assignment->completed_at);
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_revision_preserves_progress_and_clears_completion_timestamp(): void
    {
        $task = $this->makeTask(['status' => 'completed', 'completed_at' => now()]);
        $assignment = $this->makeAssignment($task, [
            'status' => 'submitted',
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);

        (new TaskReviewService)->requestRevision($assignment, $this->adminUser(), 'Please revise.');

        $assignment->refresh();
        $task->refresh();

        $this->assertSame('rejected', $assignment->status);
        $this->assertSame(100.0, (float) $assignment->progress_percentage);
        $this->assertNull($assignment->completed_at);
        $this->assertSame('in_progress', $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_revision_allows_progress_to_move_backwards(): void
    {
        $task = $this->makeTask(['status' => 'in_progress']);
        $assignment = $this->makeAssignment($task, [
            'status' => 'rejected',
            'progress_percentage' => 100,
        ]);

        TaskProgress::query()->create([
            'task_id' => $task->id,
            'task_assignment_id' => $assignment->id,
            'employee_id' => $assignment->employee_id,
            'previous_percentage' => 0,
            'new_percentage' => 100,
            'status' => 'in_progress',
        ]);

        (new TaskProgressService)->updateProgress(
            $assignment,
            60.0,
            'Reworking the missing section',
            $this->context()['employee'],
        );

        $assignment->refresh();

        $this->assertSame(60.0, (float) $assignment->progress_percentage);
        $this->assertNull($assignment->completed_at);
    }

    public function test_submission_sets_100_percent_without_approval_timestamp(): void
    {
        $task = $this->makeTask(['status' => 'in_progress']);
        $assignment = $this->makeAssignment($task, [
            'status' => 'in_progress',
            'progress_percentage' => 40,
        ]);

        (new TaskAssignmentService)->submitAssignment(
            $assignment,
            $this->context()['employee'],
            'Done',
        );

        $assignment->refresh();
        $task->refresh();

        $this->assertSame('submitted', $assignment->status);
        $this->assertSame(100.0, (float) $assignment->progress_percentage);
        $this->assertNull($assignment->completed_at);
        $this->assertSame('submitted', $task->status);
    }

    public function test_multiple_assignments_average_progress_and_complete_only_when_all_are_approved(): void
    {
        $task = $this->makeTask(['status' => 'in_progress']);
        $employeeA = $this->context()['employee'];
        $employeeB = $this->employeeWithUser(User::factory()->create());

        $assignmentA = $this->makeAssignment($task, [
            'employee_id' => $employeeA->id,
            'status' => 'in_progress',
            'progress_percentage' => 40,
        ]);
        $assignmentB = $this->makeAssignment($task, [
            'employee_id' => $employeeB->id,
            'status' => 'submitted',
            'progress_percentage' => 100,
        ]);

        $progressService = new TaskProgressService;
        $reviewService = new TaskReviewService;
        $admin = $this->adminUser();

        $this->assertSame(70.0, $progressService->getCompletionRate($task->fresh()));

        $reviewService->approve($assignmentB, $admin, 'Approved.');

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
        $this->assertNull($task->completed_at);
        $this->assertSame(70.0, $progressService->getCompletionRate($task->fresh()));

        $assignmentA->update(['status' => 'submitted', 'progress_percentage' => 100]);
        $reviewService->approve($assignmentA->fresh(), $admin, 'Approved.');

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(100.0, $progressService->getCompletionRate($task->fresh()));
    }

    public function test_department_head_can_approve_and_request_revision(): void
    {
        $head = $this->departmentHeadUser();
        $service = new TaskReviewService;

        $approvedTask = $this->makeTask(['status' => 'submitted']);
        $approved = $this->makeAssignment($approvedTask, [
            'status' => 'submitted',
            'progress_percentage' => 80,
        ]);

        $service->approve($approved, $head, 'Approved.');
        $this->assertSame(100.0, (float) $approved->fresh()->progress_percentage);

        $revisionTask = $this->makeTask(['status' => 'submitted']);
        $revision = $this->makeAssignment($revisionTask, [
            'status' => 'submitted',
            'progress_percentage' => 100,
        ]);

        $review = $service->requestRevision($revision, $head, 'Revise.');
        $this->assertSame('needs_revision', $review->decision);
        $this->assertSame('rejected', $revision->fresh()->status);
    }

    public function test_repair_command_fixes_approved_assignment_and_missing_task_completion_date(): void
    {
        $task = $this->makeTask(['status' => 'completed', 'completed_at' => null]);
        $assignment = $this->makeAssignment($task, [
            'status' => 'completed',
            'progress_percentage' => 2,
            'completed_at' => null,
        ]);

        $this->artisan('tasks:repair-progress', ['--apply' => true])
            ->assertSuccessful();

        $assignment->refresh();
        $task->refresh();

        $this->assertSame(100.0, (float) $assignment->progress_percentage);
        $this->assertNotNull($assignment->completed_at);
        $this->assertNotNull($task->completed_at);
    }
}
