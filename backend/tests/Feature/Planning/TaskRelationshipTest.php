<?php

namespace Tests\Feature\Planning;

use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\EmployeeTarget;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskProgress;
use App\Models\TaskReview;

class TaskRelationshipTest extends PlanningTestCase
{
    public function test_full_planning_chain_resolves_end_to_end(): void
    {
        $context = $this->chain();

        $departmentPlan = $context['annualPlan']->departmentPlans->first();
        $this->assertInstanceOf(DepartmentPlan::class, $departmentPlan);

        $departmentTarget = $departmentPlan->departmentTargets->first();
        $this->assertInstanceOf(DepartmentTarget::class, $departmentTarget);
        $this->assertSame($context['department']->id, $departmentTarget->department_id);

        $employeeTarget = $departmentTarget->employeeTargets->first();
        $this->assertInstanceOf(EmployeeTarget::class, $employeeTarget);
        $this->assertSame($context['employee']->id, $employeeTarget->employee_id);

        $task = $employeeTarget->tasks->first();
        $this->assertInstanceOf(Task::class, $task);
        $this->assertSame($context['departmentPlan']->id, $task->department_plan_id);

        $assignment = $task->assignments->first();
        $this->assertInstanceOf(TaskAssignment::class, $assignment);
        $this->assertSame($context['employee']->id, $assignment->employee->id);
    }

    public function test_assignment_collaboration_history_resolves(): void
    {
        $context = $this->chain();
        $assignment = $context['assignment'];

        TaskProgress::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $assignment->id,
            'employee_id' => $context['employee']->id,
            'previous_percentage' => 0,
            'new_percentage' => 50,
        ]);

        TaskReview::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $assignment->id,
            'reviewed_by' => $context['user']->id,
            'decision' => 'approved',
        ]);

        $this->assertCount(1, $assignment->progress);
        $this->assertCount(1, $assignment->reviews);
        $this->assertSame($assignment->id, $assignment->progress->first()->task_assignment_id);
        $this->assertSame($assignment->id, $assignment->reviews->first()->task_assignment_id);
    }

    public function test_department_reflects_planning_children(): void
    {
        $context = $this->chain();
        $department = $context['department'];

        $this->assertTrue($department->departmentPlans->contains($context['departmentPlan']));
        $this->assertTrue($department->departmentTargets->contains($context['departmentTarget']));
        $this->assertTrue($department->tasks->contains($context['task']));
    }

    public function test_user_authorship_relationships_resolve(): void
    {
        $context = $this->chain();
        $user = $context['user'];

        $context['task']->update(['updated_by' => $user->id]);

        TaskReview::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'reviewed_by' => $user->id,
            'decision' => 'needs_revision',
        ]);

        $this->assertTrue($user->createdAnnualPlans->contains($context['annualPlan']));
        $this->assertTrue($user->createdDepartmentPlans->contains($context['departmentPlan']));
        $this->assertTrue($user->createdTasks->contains($context['task']));
        $this->assertTrue($user->updatedTasks->pluck('id')->contains($context['task']->id));
        $this->assertTrue($user->assignedTasks->contains($context['assignment']));
        $this->assertGreaterThanOrEqual(1, $user->reviewedTasks()->count());
    }

    public function test_employee_sees_assignment_and_target(): void
    {
        $context = $this->chain();
        $employee = $context['employee'];

        $this->assertTrue($employee->taskAssignments->contains($context['assignment']));
        $this->assertTrue(
            $employee->employeeTargets()
                ->pluck('id')
                ->contains($context['employeeTarget']->id),
        );
    }

    public function test_chain_records_are_isolated_per_test(): void
    {
        $context = $this->chain();

        $this->assertDatabaseHas('tasks', ['id' => $context['task']->id]);
        $this->assertDatabaseHas('task_assignments', ['id' => $context['assignment']->id]);
        $this->assertSame(1, Task::query()->count());
        $this->assertSame(1, TaskAssignment::query()->count());
    }
}
