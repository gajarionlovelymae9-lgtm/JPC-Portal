<?php

namespace App\Services\TaskManagement;

use App\Events\TaskManagement\TaskCancelled;
use App\Exceptions\TaskManagement\InvalidTaskStatusTransitionException;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Support\Facades\DB;

class TaskService
{
    private const ALLOWED_STATUSES = [
        'draft', 'assigned', 'in_progress', 'submitted',
        'under_review', 'completed', 'rejected', 'cancelled',
    ];

    /**
     * Create a new task with a concurrency-safe generated code.
     */
    public function createTask(array $data, User $creator): Task
    {
        $taskCode = $data['task_code'] ?? $this->generateTaskCode();

        return DB::transaction(function () use ($data, $creator, $taskCode) {
            $task = Task::query()->create(array_merge(
                [
                    'task_code' => $taskCode,
                    'department_id' => $data['department_id'],
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'priority' => $data['priority'],
                    'status' => 'draft',
                    'start_date' => $data['start_date'] ?? null,
                    'due_date' => $data['due_date'],
                    'estimated_hours' => $data['estimated_hours'] ?? null,
                    'target_value' => $data['target_value'] ?? null,
                    'target_unit' => $data['target_unit'] ?? null,
                    'required_output' => $data['required_output'] ?? null,
                    'created_by' => $creator->id,
                    'updated_by' => null,
                ],
                $this->filterPlanningIds($data)
            ));

            $this->recordActivity($task, 'task.created', $creator);

            return $task;
        });
    }

    /**
     * Update a task, enforcing lifecycle restrictions.
     */
    public function updateTask(Task $task, array $data, User $user): Task
    {
        if ($task->status === 'completed') {
            throw InvalidTaskStatusTransitionException::from('completed', 'update');
        }

        if ($task->status === 'cancelled') {
            throw InvalidTaskStatusTransitionException::from('cancelled', 'update');
        }

        if (isset($data['status']) && ! in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            throw InvalidTaskStatusTransitionException::from($task->status, $data['status']);
        }

        if (isset($data['status'])) {
            $this->enforceTransition($task->status, $data['status']);
        }

        return DB::transaction(function () use ($task, $data, $user) {
            $task->fill(array_filter($data, fn ($v) => $v !== null));
            $task->updated_by = $user->id;
            $task->save();

            $this->recordActivity($task, 'task.updated', $user);

            return $task;
        });
    }

    /**
     * Cancel a task (draft or assigned only).
     */
    public function cancelTask(Task $task, User $user): Task
    {
        if (! in_array($task->status, ['draft', 'assigned'], true)) {
            throw InvalidTaskStatusTransitionException::from($task->status, 'cancelled');
        }

        return DB::transaction(function () use ($task, $user) {
            $task->update([
                'status' => 'cancelled',
                'updated_by' => $user->id,
            ]);

            $this->recordActivity($task, 'task.cancelled', $user);

            TaskCancelled::dispatch($task);

            return $task;
        });
    }

    /**
     * Generate a concurrency-safe unique task code.
     */
    public function generateTaskCode(): string
    {
        $year = now()->format('Y');
        $prefix = "TASK-{$year}-";

        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $codes = DB::table('tasks')
                    ->where('task_code', 'like', "{$prefix}%")
                    ->pluck('task_code');
                $max = 0;
                foreach ($codes as $c) {
                    if (preg_match('/-(\d+)$/', (string) $c, $m)) {
                        $seq = (int) $m[1];
                        if ($seq > $max) {
                            $max = $seq;
                        }
                    }
                }
            } else {
                $max = DB::table('tasks')
                    ->where('task_code', 'like', "{$prefix}%")
                    ->lockForUpdate()
                    ->select(DB::raw('IFNULL(MAX(CAST(SUBSTRING_INDEX(task_code, "-", -1) AS UNSIGNED)), 0) AS seq'))
                    ->value('seq');
            }

            $code = $prefix.str_pad((string) ((int) $max + 1), 4, '0', STR_PAD_LEFT);

            if (! Task::query()->where('task_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a unique task code.');
    }

    /**
     * Determine the overall status from the task's current assignments.
     *
     * A task can have several assignees, so the aggregate status describes the
     * whole set: it is only completed once every assignee is done, it is
     * submitted as soon as one assignee is waiting for review, and it is in
     * progress once any assignee has started (or completed part of) the work.
     */
    public function getOverallTaskStatus(Task $task): string
    {
        if ($task->status === 'cancelled') {
            return 'cancelled';
        }

        $statuses = $task->assignments()->pluck('status')->all();
        $active = array_values(array_filter(
            $statuses,
            fn (string $status) => $status !== 'cancelled',
        ));

        if ($active === []) {
            return $statuses === [] ? $task->status : 'cancelled';
        }

        if (count(array_filter($active, fn (string $s) => $s === 'completed')) === count($active)) {
            return 'completed';
        }

        if (in_array('submitted', $active, true) || in_array('under_review', $active, true)) {
            return 'submitted';
        }

        if (
            in_array('in_progress', $active, true)
            || in_array('accepted', $active, true)
            || in_array('completed', $active, true)
        ) {
            return 'in_progress';
        }

        if (in_array('rejected', $active, true)) {
            return 'in_progress';
        }

        return 'assigned';
    }

    /**
     * Keep the aggregate task status aligned with its assignments so the task
     * header no longer disagrees with what an employee/reviewer sees.
     */
    public function syncAggregateStatus(Task $task): void
    {
        $task->refresh();

        if ($task->status === 'cancelled') {
            return;
        }

        $status = $this->getOverallTaskStatus($task);

        $attributes = ['status' => $status];

        if ($status === 'completed' && $task->completed_at === null) {
            // The task is only completed once every assignment is approved.
            // Stamp the completion time the assignment approval produced.
            $attributes['completed_at'] = now();
        } elseif ($status !== 'completed' && $task->completed_at !== null) {
            // Work was legitimately reopened; a completed date would be wrong.
            $attributes['completed_at'] = null;
        }

        if ($status !== $task->status || count($attributes) > 1) {
            $task->update($attributes);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filterPlanningIds(array $data): array
    {
        return array_filter(
            array_intersect_key($data, array_flip([
                'annual_plan_id', 'department_plan_id', 'department_target_id',
                'employee_target_id', 'parent_task_id',
            ])),
            fn ($v) => $v !== null
        );
    }

    private function enforceTransition(string $from, string $to): void
    {
        $allowed = [
            'draft' => ['assigned', 'cancelled'],
            'assigned' => ['in_progress', 'cancelled'],
            'in_progress' => ['submitted', 'cancelled'],
            'submitted' => ['under_review', 'rejected'],
            'under_review' => ['completed', 'rejected'],
            'rejected' => ['in_progress'],
        ];

        if (! isset($allowed[$from]) || ! in_array($to, $allowed[$from], true)) {
            throw InvalidTaskStatusTransitionException::from($from, $to);
        }
    }

    private function recordActivity(Task $task, string $event, User $user): void
    {
        app(ActivityLogService::class)->log(
            $user,
            $event,
            $task,
            null,
            ['task_code' => $task->task_code, 'status' => $task->status],
        );
    }
}
