<?php

namespace Tests\Feature\Services;

use App\Exceptions\TaskManagement\InvalidTaskStatusTransitionException;
use App\Models\User;
use App\Services\TaskManagement\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\PlanningTestCase;

class TaskServiceTest extends PlanningTestCase
{
    use RefreshDatabase;

    public function test_authorized_user_creates_task_with_draft_status(): void
    {
        $service = new TaskService;
        $creator = User::factory()->create();

        $task = $service->createTask([
            'title' => 'Prepare Monthly Faculty Report',
            'description' => 'Monthly faculty report.',
            'priority' => 'high',
            'department_id' => $this->context()['department']->id,
            'due_date' => '2026-12-31',
        ], $creator);

        $this->assertSame('draft', $task->status);
        $this->assertNotNull($task->task_code);
        $this->assertStringStartsWith('TASK-', $task->task_code);
    }

    public function test_create_task_generates_unique_codes(): void
    {
        $service = new TaskService;
        $creator = User::factory()->create();
        $dept = $this->context()['department'];

        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $codes[] = $service->createTask([
                'title' => "Task {$i}",
                'priority' => 'medium',
                'department_id' => $dept->id,
                'due_date' => '2026-12-31',
            ], $creator)->task_code;
        }

        $this->assertCount(5, array_unique($codes));
    }

    public function test_update_completed_task_throws(): void
    {
        $service = new TaskService;
        $creator = User::factory()->create();
        $task = $service->createTask([
            'title' => 'Complete Task',
            'priority' => 'medium',
            'department_id' => $this->context()['department']->id,
            'due_date' => '2026-12-31',
        ], $creator);

        $task->update(['status' => 'completed']);

        $this->expectException(InvalidTaskStatusTransitionException::class);
        $service->updateTask($task, ['title' => 'Changed'], User::factory()->create());
    }

    public function test_invalid_transition_draft_to_completed_throws(): void
    {
        $service = new TaskService;
        $creator = User::factory()->create();
        $task = $service->createTask([
            'title' => 'Draft Task',
            'priority' => 'medium',
            'department_id' => $this->context()['department']->id,
            'due_date' => '2026-12-31',
        ], $creator);

        $this->expectException(InvalidTaskStatusTransitionException::class);
        $service->updateTask($task, ['status' => 'completed'], User::factory()->create());
    }

    public function test_cancel_assigned_task_succeeds(): void
    {
        $service = new TaskService;
        $creator = User::factory()->create();
        $task = $service->createTask([
            'title' => 'Cancel Me',
            'priority' => 'medium',
            'department_id' => $this->context()['department']->id,
            'due_date' => '2026-12-31',
        ], $creator);

        $task->update(['status' => 'assigned']);
        $cancelled = $service->cancelTask($task, $creator);

        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_cancel_completed_task_throws(): void
    {
        $service = new TaskService;
        $creator = User::factory()->create();
        $task = $service->createTask([
            'title' => 'Already Done',
            'priority' => 'medium',
            'department_id' => $this->context()['department']->id,
            'due_date' => '2026-12-31',
        ], $creator);

        $task->update(['status' => 'completed']);

        $this->expectException(InvalidTaskStatusTransitionException::class);
        $service->cancelTask($task, $creator);
    }
}
