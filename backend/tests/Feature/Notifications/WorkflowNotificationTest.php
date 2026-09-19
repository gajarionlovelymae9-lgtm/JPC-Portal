<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Models\User;
use App\Notifications\Tasks\TaskAssignedNotification;
use App\Services\Notifications\NotificationService;
use App\Services\TaskManagement\TaskAssignmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
    }

    private function employeeUser(Department $department): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }

    private function departmentHeadUser(Department $department): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return $user;
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    private function taskIn(Department $department, array $attributes = []): Task
    {
        return Task::factory()->create(array_merge([
            'department_id' => $department->id,
            'due_date' => now()->addDays(5),
        ], $attributes));
    }

    private function assignmentFor(User $employeeUser, Task $task, array $attributes = []): TaskAssignment
    {
        $employee = Employee::query()->where('user_id', $employeeUser->id)->firstOrFail();

        return TaskAssignment::factory()->create(array_merge([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
        ], $attributes));
    }

    private function notificationsOf(User $user, string $type): int
    {
        return Notification::query()->where('user_id', $user->id)->where('type', $type)->count();
    }

    // ── CHANNEL SMOKE TEST ─────────────────────────────────────

    public function test_jpc_database_channel_writes_to_notifications_table(): void
    {
        $dept = Department::factory()->create();
        $employee = $this->employeeUser($dept);
        $task = $this->taskIn($dept, ['priority' => 'high']);
        $assignment = $this->assignmentFor($employee, $task);

        $employee->notify(new TaskAssignedNotification($assignment, $employee));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
        ]);
    }

    // ── ASSIGNMENT FLOW ────────────────────────────────────────

    public function test_assigning_task_notifies_employee_and_department_head(): void
    {
        $dept = Department::factory()->create();
        $head = $this->departmentHeadUser($dept);
        $employee = $this->employeeUser($dept);
        $admin = $this->adminUser();
        $task = $this->taskIn($dept);

        $service = new TaskAssignmentService;
        $service->assignEmployee($task, Employee::query()->where('user_id', $employee->id)->firstOrFail(), $admin);

        $this->assertSame(1, $this->notificationsOf($employee, 'task_assigned'));
        $this->assertSame(1, $this->notificationsOf($head, 'task_assigned'));
        $this->assertSame(0, $this->notificationsOf($admin, 'task_assigned'));
    }

    // ── SUBMISSION / REVIEW FLOW ───────────────────────────────

    public function test_submitting_task_notifies_reviewers(): void
    {
        $dept = Department::factory()->create();
        $head = $this->departmentHeadUser($dept);
        $admin = $this->adminUser();
        $employee = $this->employeeUser($dept);
        $task = $this->taskIn($dept);
        $assignment = $this->assignmentFor($employee, $task);

        (new NotificationService)->sendTaskSubmitted($assignment);

        $this->assertSame(1, $this->notificationsOf($head, 'task_submitted'));
        $this->assertSame(1, $this->notificationsOf($admin, 'task_submitted'));
        $this->assertSame(0, $this->notificationsOf($employee, 'task_submitted'));
    }

    public function test_review_approval_notifies_employee_with_task_approved(): void
    {
        $employee = $this->employeeUser(Department::factory()->create());
        $task = $this->taskIn($employee->employee->department);
        $assignment = $this->assignmentFor($employee, $task);
        $review = $this->reviewFor($assignment, 'approved');

        (new NotificationService)->sendReviewed($assignment, $review);

        $this->assertSame(1, $this->notificationsOf($employee, 'task_approved'));
    }

    public function test_review_rejection_notifies_employee_with_task_rejected(): void
    {
        $employee = $this->employeeUser(Department::factory()->create());
        $task = $this->taskIn($employee->employee->department);
        $assignment = $this->assignmentFor($employee, $task);

        (new NotificationService)->sendReviewed($assignment, $this->reviewFor($assignment, 'rejected'));

        $this->assertSame(1, $this->notificationsOf($employee, 'task_rejected'));
    }

    public function test_review_revision_request_notifies_employee_with_revision_required(): void
    {
        $employee = $this->employeeUser(Department::factory()->create());
        $task = $this->taskIn($employee->employee->department);
        $assignment = $this->assignmentFor($employee, $task);

        (new NotificationService)->sendReviewed($assignment, $this->reviewFor($assignment, 'needs_revision'));

        $this->assertSame(1, $this->notificationsOf($employee, 'revision_required'));
    }

    public function test_resubmission_notifies_reviewers(): void
    {
        $dept = Department::factory()->create();
        $head = $this->departmentHeadUser($dept);
        $employee = $this->employeeUser($dept);
        $assignment = $this->assignmentFor($employee, $this->taskIn($dept));

        (new NotificationService)->sendTaskResubmitted($assignment);

        $this->assertSame(1, $this->notificationsOf($head, 'task_resubmitted'));
    }

    // ── CANCELLATION FLOW ──────────────────────────────────────

    public function test_cancelling_task_notifies_all_assignees(): void
    {
        $dept = Department::factory()->create();
        $employeeA = $this->employeeUser($dept);
        $employeeB = $this->employeeUser($dept);
        $task = $this->taskIn($dept);
        $this->assignmentFor($employeeA, $task);
        $this->assignmentFor($employeeB, $task);

        (new NotificationService)->sendTaskCancelled($task);

        $this->assertSame(1, $this->notificationsOf($employeeA, 'task_cancelled'));
        $this->assertSame(1, $this->notificationsOf($employeeB, 'task_cancelled'));
    }

    private function reviewFor(TaskAssignment $assignment, string $decision): TaskReview
    {
        return TaskReview::factory()->create([
            'task_id' => $assignment->task_id,
            'task_assignment_id' => $assignment->id,
            'reviewed_by' => User::factory()->create(['role_id' => $this->roleId(Role::Admin)])->id,
            'decision' => $decision,
            'reviewed_at' => now(),
        ]);
    }
}