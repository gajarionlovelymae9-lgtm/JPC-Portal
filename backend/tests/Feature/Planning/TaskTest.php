<?php

namespace Tests\Feature\Planning;

use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\EmployeeTarget;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TaskTest extends PlanningTestCase
{
    public function test_belongs_to_planning_parents(): void
    {
        $context = $this->chain();
        $task = $context['task'];

        $this->assertInstanceOf(BelongsTo::class, $task->annualPlan());
        $this->assertInstanceOf(AnnualPlan::class, $task->annualPlan);
        $this->assertInstanceOf(DepartmentPlan::class, $task->departmentPlan);
        $this->assertInstanceOf(DepartmentTarget::class, $task->departmentTarget);
        $this->assertInstanceOf(EmployeeTarget::class, $task->employeeTarget);
    }

    public function test_belongs_to_department_and_users(): void
    {
        $context = $this->chain();
        $task = $context['task'];

        $this->assertInstanceOf(Department::class, $task->department);
        $this->assertInstanceOf(BelongsTo::class, $task->createdBy());
        $this->assertInstanceOf(User::class, $task->createdBy);
        $this->assertInstanceOf(BelongsTo::class, $task->updatedBy());

        $task->update(['updated_by' => $context['user']->id]);
        $this->assertSame($context['user']->id, $task->fresh()->updatedBy->id);
    }

    public function test_parent_child_self_relationship(): void
    {
        $parent = $this->makeTask(['task_code' => 'TASK-PARENT']);
        $child = $this->makeTask([
            'task_code' => 'TASK-CHILD',
            'parent_task_id' => $parent->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $child->parent());
        $this->assertSame($parent->id, $child->parent->id);
        $this->assertInstanceOf(HasMany::class, $parent->children());
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_has_many_collaboration_relationships(): void
    {
        $context = $this->chain();
        $task = $context['task'];

        foreach (['assignments', 'progress', 'comments', 'attachments', 'reviews', 'references', 'reports'] as $relation) {
            $this->assertInstanceOf(HasMany::class, $task->{$relation}(), "Task::{$relation}()");
        }

        $this->assertTrue($task->assignments->contains($context['assignment']));
        $this->assertInstanceOf(TaskAssignment::class, $context['assignment']);
    }

    public function test_casts_dates_and_numbers(): void
    {
        $task = $this->chain()['task'];

        $this->assertInstanceOf(Carbon::class, $task->start_date);
        $this->assertInstanceOf(Carbon::class, $task->due_date);
        $this->assertSame('8.50', $task->estimated_hours);
    }

    public function test_status_scopes(): void
    {
        $this->makeTask(['task_code' => 'T-DRAFT', 'status' => 'draft']);
        $this->makeTask(['task_code' => 'T-ASSIGNED', 'status' => 'assigned']);
        $this->makeTask(['task_code' => 'T-INPROG', 'status' => 'in_progress']);
        $this->makeTask(['task_code' => 'T-SUBMITTED', 'status' => 'submitted']);
        $this->makeTask(['task_code' => 'T-REVIEW', 'status' => 'under_review']);
        $this->makeTask(['task_code' => 'T-COMPLETED', 'status' => 'completed']);
        $this->makeTask(['task_code' => 'T-REJECTED', 'status' => 'rejected']);
        $this->makeTask(['task_code' => 'T-CANCELLED', 'status' => 'cancelled']);

        $this->assertSame(1, Task::query()->draft()->count());
        $this->assertSame(1, Task::query()->assigned()->count());
        $this->assertSame(1, Task::query()->inProgress()->count());
        $this->assertSame(1, Task::query()->submitted()->count());
        $this->assertSame(1, Task::query()->underReview()->count());
        $this->assertSame(1, Task::query()->completed()->count());
        $this->assertSame(1, Task::query()->rejected()->count());
        $this->assertSame(1, Task::query()->cancelled()->count());
    }

    public function test_priority_scopes(): void
    {
        $this->makeTask(['task_code' => 'P-HIGH', 'priority' => 'high']);
        $this->makeTask(['task_code' => 'P-URGENT', 'priority' => 'urgent']);
        $this->makeTask(['task_code' => 'P-LOW', 'priority' => 'low']);

        $this->assertSame(1, Task::query()->highPriority()->count());
        $this->assertSame(1, Task::query()->urgent()->count());
        $this->assertSame(3, Task::query()->count());
    }

    public function test_overdue_scope_excludes_finished_and_future_tasks(): void
    {
        $this->makeTask([
            'task_code' => 'OD-1',
            'status' => 'in_progress',
            'due_date' => now()->subDays(2),
            'completed_at' => null,
        ]);
        $this->makeTask([
            'task_code' => 'OD-COMPLETED',
            'status' => 'completed',
            'due_date' => now()->subDays(5),
            'completed_at' => now(),
        ]);
        $this->makeTask([
            'task_code' => 'OD-FUTURE',
            'status' => 'in_progress',
            'due_date' => now()->addDays(5),
        ]);

        $overdue = Task::query()->overdue()->get();

        $this->assertCount(1, $overdue);
        $this->assertSame('OD-1', $overdue->first()->task_code);
    }

    public function test_is_overdue_and_is_completed_helpers(): void
    {
        $overdue = $this->makeTask([
            'task_code' => 'H-OVERDUE',
            'status' => 'in_progress',
            'due_date' => now()->subDay(),
        ]);
        $completed = $this->makeTask([
            'task_code' => 'H-DONE',
            'status' => 'completed',
            'due_date' => now()->subDay(),
        ]);
        $future = $this->makeTask([
            'task_code' => 'H-FUTURE',
            'status' => 'in_progress',
            'due_date' => now()->addDay(),
        ]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($completed->isOverdue());
        $this->assertFalse($future->isOverdue());
        $this->assertTrue($completed->isCompleted());
        $this->assertFalse($overdue->isCompleted());
    }

    public function test_by_department_scope(): void
    {
        $context = $this->context();
        $other = Department::factory()->create();

        $this->makeTask(['task_code' => 'BD-1']);
        $this->makeTask(['task_code' => 'BD-2', 'department_id' => $other->id]);

        $this->assertSame(
            1,
            Task::query()->byDepartment($context['department']->id)->count(),
        );
    }

    public function test_eager_loading_avoids_n_plus_one(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $task = $this->makeTask(['task_code' => 'N1-'.$i]);
            $this->makeAssignment($task);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $tasks = Task::query()->with(['assignments.employee', 'department'])->get();
        foreach ($tasks as $task) {
            $task->department->name;
            foreach ($task->assignments as $assignment) {
                $assignment->employee->first_name;
            }
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, $queryCount);
    }

    public function test_soft_delete_uses_trash_bin(): void
    {
        $task = $this->chain()['task'];

        $task->delete();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertNotNull(Task::withTrashed()->find($task->id));
    }

    public function test_mass_assignment_guards_protected_columns(): void
    {
        $task = new Task;

        $this->assertFalse($task->isFillable('id'));
        $this->assertFalse($task->isFillable('created_at'));
        $this->assertFalse($task->isFillable('updated_at'));
        $this->assertFalse($task->isFillable('deleted_at'));
        $this->assertTrue($task->isFillable('task_code'));
        $this->assertTrue($task->isFillable('department_plan_id'));
        $this->assertTrue($task->isFillable('updated_by'));
    }
}
