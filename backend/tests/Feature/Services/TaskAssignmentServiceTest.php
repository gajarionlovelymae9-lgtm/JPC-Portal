<?php

namespace Tests\Feature\Services;

use App\Exceptions\TaskManagement\TaskNotAssignableException;
use App\Models\Employee;
use App\Services\TaskManagement\TaskAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\PlanningTestCase;

class TaskAssignmentServiceTest extends PlanningTestCase
{
    use RefreshDatabase;

    public function test_task_can_be_assigned_to_two_employees(): void
    {
        $service = new TaskAssignmentService;
        $creator = $this->context()['user'];
        $task = $this->makeTask();
        $employee1 = $this->context()['employee'];
        $employee2 = Employee::factory()->create([
            'department_id' => $this->context()['department']->id,
            'position_id' => $this->context()['position']->id,
        ]);

        $assignment1 = $service->assignEmployee($task, $employee1, $creator);
        $assignment2 = $service->assignEmployee($task, $employee2, $creator);

        $this->assertCount(2, $task->fresh()->assignments);
        $this->assertSame('assigned', $assignment1->status);
        $this->assertSame('assigned', $assignment2->status);
    }

    public function test_duplicate_assignment_throws(): void
    {
        $service = new TaskAssignmentService;
        $creator = $this->context()['user'];
        $task = $this->makeTask();
        $employee = $this->context()['employee'];

        $service->assignEmployee($task, $employee, $creator);

        $this->expectException(TaskNotAssignableException::class);
        $service->assignEmployee($task, $employee, $creator);
    }

    public function test_inactive_employee_cannot_be_assigned(): void
    {
        $service = new TaskAssignmentService;
        $creator = $this->context()['user'];
        $task = $this->makeTask();
        $employee = Employee::factory()->create([
            'department_id' => $this->context()['department']->id,
            'position_id' => $this->context()['position']->id,
            'employment_status' => 'inactive',
        ]);

        $this->expectException(TaskNotAssignableException::class);
        $service->assignEmployee($task, $employee, $creator);
    }

    public function test_assign_completed_task_throws(): void
    {
        $service = new TaskAssignmentService;
        $creator = $this->context()['user'];
        $task = $this->makeTask();
        $task->update(['status' => 'completed']);
        $employee = Employee::factory()->create([
            'department_id' => $this->context()['department']->id,
            'position_id' => $this->context()['position']->id,
        ]);

        $this->expectException(TaskNotAssignableException::class);
        $service->assignEmployee($task, $employee, $creator);
    }
}
