<?php

namespace Tests\Feature\Planning;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskAssignmentTest extends PlanningTestCase
{
    public function test_belongs_to_task_employee_and_assigner(): void
    {
        $context = $this->chain();
        $assignment = $context['assignment'];

        $this->assertInstanceOf(BelongsTo::class, $assignment->task());
        $this->assertInstanceOf(Task::class, $assignment->task);
        $this->assertInstanceOf(BelongsTo::class, $assignment->employee());
        $this->assertInstanceOf(Employee::class, $assignment->employee);
        $this->assertInstanceOf(BelongsTo::class, $assignment->assignedBy());
        $this->assertInstanceOf(User::class, $assignment->assignedBy);
    }

    public function test_has_many_collaboration_relationships(): void
    {
        $assignment = $this->chain()['assignment'];

        $this->assertInstanceOf(HasMany::class, $assignment->progress());
        $this->assertInstanceOf(HasMany::class, $assignment->comments());
        $this->assertInstanceOf(HasMany::class, $assignment->attachments());
        $this->assertInstanceOf(HasMany::class, $assignment->reviews());
    }

    public function test_casts_dates_percentage_and_new_fields(): void
    {
        $assignment = $this->chain()['assignment'];

        $this->assertInstanceOf(Carbon::class, $assignment->assigned_at);
        $this->assertInstanceOf(Carbon::class, $assignment->due_date);
        $this->assertSame('65.00', $assignment->progress_percentage);
    }

    public function test_accepts_new_review_fields(): void
    {
        $task = $this->makeTask(['task_code' => 'TA-ACCEPT']);
        $assignment = $this->makeAssignment($task, [
            'status' => 'accepted',
            'accepted_at' => now(),
            'remarks' => 'Accepted with noted constraints.',
        ]);

        $this->assertInstanceOf(Carbon::class, $assignment->fresh()->accepted_at);
        $this->assertSame('Accepted with noted constraints.', $assignment->fresh()->remarks);
    }

    public function test_scope_pending_and_for_employee(): void
    {
        $context = $this->context();

        $task1 = $this->makeTask(['task_code' => 'TA-P1']);
        $task2 = $this->makeTask(['task_code' => 'TA-P2']);
        $this->makeAssignment($task1, ['status' => 'in_progress']);
        $this->makeAssignment($task2, ['status' => 'completed']);

        $this->assertSame(1, TaskAssignment::query()->pending()->count());
        $this->assertSame(
            2,
            TaskAssignment::query()->forEmployee($context['employee']->id)->count(),
        );
    }

    public function test_scope_overdue_and_is_overdue_helper(): void
    {
        $task = $this->makeTask(['task_code' => 'TA-OD']);
        $overdue = $this->makeAssignment($task, [
            'status' => 'in_progress',
            'due_date' => now()->subDays(2),
            'completed_at' => null,
        ]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertTrue(
            TaskAssignment::query()->overdue()->pluck('id')->contains($overdue->id),
        );

        $overdue->update(['completed_at' => now()]);
        $this->assertFalse($overdue->fresh()->isOverdue());
    }

    public function test_status_scopes(): void
    {
        $statuses = ['assigned', 'accepted', 'in_progress', 'submitted', 'completed', 'rejected', 'cancelled'];

        foreach ($statuses as $index => $status) {
            $task = $this->makeTask(['task_code' => 'TA-S'.$index]);
            $this->makeAssignment($task, ['status' => $status]);
        }

        $this->assertSame(1, TaskAssignment::query()->assigned()->count());
        $this->assertSame(1, TaskAssignment::query()->accepted()->count());
        $this->assertSame(1, TaskAssignment::query()->inProgress()->count());
        $this->assertSame(1, TaskAssignment::query()->submitted()->count());
        $this->assertSame(1, TaskAssignment::query()->completed()->count());
        $this->assertSame(1, TaskAssignment::query()->rejected()->count());
        $this->assertSame(1, TaskAssignment::query()->cancelled()->count());
    }

    public function test_soft_delete_uses_trash_bin(): void
    {
        $assignment = $this->chain()['assignment'];

        $assignment->delete();

        $this->assertSoftDeleted('task_assignments', ['id' => $assignment->id]);
        $this->assertNotNull(TaskAssignment::withTrashed()->find($assignment->id));
    }

    public function test_mass_assignment_guards_protected_columns(): void
    {
        $assignment = new TaskAssignment;

        $this->assertFalse($assignment->isFillable('id'));
        $this->assertFalse($assignment->isFillable('created_at'));
        $this->assertFalse($assignment->isFillable('updated_at'));
        $this->assertFalse($assignment->isFillable('deleted_at'));
        $this->assertTrue($assignment->isFillable('accepted_at'));
        $this->assertTrue($assignment->isFillable('remarks'));
    }
}
