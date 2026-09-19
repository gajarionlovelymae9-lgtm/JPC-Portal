<?php

namespace Tests\Feature\Services;

use App\Exceptions\TaskManagement\InvalidTaskReviewException;
use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\TaskManagement\TaskReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\PlanningTestCase;

class TaskReviewServiceTest extends PlanningTestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = RoleModel::query()->firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Admin', 'description' => 'Administrator'],
        );

        return User::factory()->create(['role_id' => $adminRole->id]);
    }

    private function employeeWithUser(User $user): Employee
    {
        return Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => $this->context()['department']->id,
            'position_id' => $this->context()['position']->id,
        ]);
    }

    public function test_approval_creates_review_and_completes_assignment(): void
    {
        $service = new TaskReviewService;
        $creator = $this->context()['user'];
        $admin = $this->adminUser();
        $task = $this->makeTask();
        $employeeUser = User::factory()->create();
        $employee = $this->employeeWithUser($employeeUser);
        $assignment = TaskAssignment::query()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'status' => 'submitted',
            'due_date' => now()->addDays(7),
            'progress_percentage' => 100,
        ]);

        $review = $service->approve($assignment, $admin, 'Well done.');

        $this->assertCount(1, $task->fresh()->reviews);
        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertSame('approved', $review->decision);
    }

    public function test_rejection_creates_review_and_sets_rejected_status(): void
    {
        $service = new TaskReviewService;
        $creator = $this->context()['user'];
        $admin = $this->adminUser();
        $task = $this->makeTask();
        $employeeUser = User::factory()->create();
        $employee = $this->employeeWithUser($employeeUser);
        $assignment = TaskAssignment::query()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'status' => 'submitted',
            'due_date' => now()->addDays(7),
            'progress_percentage' => 100,
        ]);

        $review = $service->reject($assignment, $admin, 'Missing documentation.');

        $this->assertSame('rejected', $assignment->fresh()->status);
        $this->assertSame('rejected', $review->decision);
    }

    public function test_needs_revision_creates_review_and_sets_rejected_status(): void
    {
        $service = new TaskReviewService;
        $creator = $this->context()['user'];
        $admin = $this->adminUser();
        $task = $this->makeTask();
        $employeeUser = User::factory()->create();
        $employee = $this->employeeWithUser($employeeUser);
        $assignment = TaskAssignment::query()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'assigned_by' => $creator->id,
            'assigned_at' => now(),
            'status' => 'submitted',
            'due_date' => now()->addDays(7),
            'progress_percentage' => 100,
        ]);

        $review = $service->requestRevision($assignment, $admin, 'Additional supporting document required.');

        $this->assertSame('needs_revision', $review->decision);
        $this->assertSame('rejected', $assignment->fresh()->status);
    }

    public function test_self_approval_is_blocked(): void
    {
        $service = new TaskReviewService;
        $task = $this->makeTask();
        $employee = $this->employeeWithUser($this->context()['user']);
        $assignment = TaskAssignment::query()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'assigned_by' => $this->context()['user']->id,
            'assigned_at' => now(),
            'status' => 'submitted',
            'due_date' => now()->addDays(7),
            'progress_percentage' => 100,
        ]);

        $this->expectException(InvalidTaskReviewException::class);
        $service->approve($assignment, $this->context()['user'], 'Self approval.');
    }

    public function test_unauthorized_reviewer_is_blocked(): void
    {
        $service = new TaskReviewService;
        $task = $this->makeTask();
        $employee = $this->employeeWithUser(User::factory()->create());
        $assignment = TaskAssignment::query()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'assigned_by' => $this->context()['user']->id,
            'assigned_at' => now(),
            'status' => 'submitted',
            'due_date' => now()->addDays(7),
            'progress_percentage' => 100,
        ]);

        $regularUser = User::factory()->create();

        $this->expectException(InvalidTaskReviewException::class);
        $service->approve($assignment, $regularUser, 'No access.');
    }

    public function test_review_non_submitted_assignment_is_blocked(): void
    {
        $service = new TaskReviewService;
        $task = $this->makeTask();
        $employeeUser = User::factory()->create();
        $employee = $this->employeeWithUser($employeeUser);
        $assignment = TaskAssignment::query()->create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'assigned_by' => $this->context()['user']->id,
            'assigned_at' => now(),
            'status' => 'assigned',
            'due_date' => now()->addDays(7),
            'progress_percentage' => 0,
        ]);

        $this->expectException(InvalidTaskReviewException::class);
        $service->approve($assignment, $this->adminUser(), 'Too early.');
    }
}
