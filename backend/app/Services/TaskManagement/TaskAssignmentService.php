<?php

namespace App\Services\TaskManagement;

use App\Events\TaskManagement\TaskAssigned;
use App\Events\TaskManagement\TaskResubmitted;
use App\Events\TaskManagement\TaskSubmitted;
use App\Exceptions\TaskManagement\InvalidTaskStatusTransitionException;
use App\Exceptions\TaskManagement\TaskNotAssignableException;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskAssignmentService
{
    /**
     * Assign an employee to a task.
     */
    public function assignEmployee(Task $task, Employee $employee, User $assignedBy, array $data = []): TaskAssignment
    {
        if ($employee->employment_status !== 'active') {
            throw TaskNotAssignableException::employeeNotActive();
        }

        if (! in_array($task->status, ['draft', 'assigned'], true)) {
            throw TaskNotAssignableException::taskNotAssignable($task->status);
        }

        if ($data['due_date'] ?? null) {
            if ($task->start_date && strtotime($data['due_date']) < strtotime($task->start_date)) {
                // due_date must be >= start_date; let validation handle this at the form request level
            }
        }

        $existing = TaskAssignment::query()
            ->where('task_id', $task->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing) {
            throw TaskNotAssignableException::assignmentAlreadyExists();
        }

        return DB::transaction(function () use ($task, $employee, $assignedBy, $data) {
            $assignment = TaskAssignment::query()->create([
                'task_id' => $task->id,
                'employee_id' => $employee->id,
                'assigned_by' => $assignedBy->id,
                'assigned_at' => now(),
                'status' => 'assigned',
                'progress_percentage' => 0,
                'due_date' => $data['due_date'] ?? $task->due_date,
                'start_date' => $data['start_date'] ?? now(),
            ]);

            $this->recordActivity($task, 'task.assigned', $assignedBy);

            TaskAssigned::dispatch($assignment);

            return $assignment;
        });
    }

    /**
     * Accept an assignment (assigned → accepted).
     */
    public function acceptAssignment(TaskAssignment $assignment, Employee $employee): TaskAssignment
    {
        $this->guardEmployeeOwnsAssignment($assignment, $employee);

        if ($assignment->status !== 'assigned') {
            throw InvalidTaskStatusTransitionException::from($assignment->status, 'accepted');
        }

        if (in_array($assignment->status, ['cancelled', 'completed'], true)) {
            throw new \RuntimeException('Cannot accept a cancelled or completed assignment.');
        }

        return DB::transaction(function () use ($assignment, $employee) {
            $assignment->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            $this->recordActivity($assignment->task, 'task.accepted', $employee->user);

            return $assignment->fresh();
        });
    }

    /**
     * Start an assignment (accepted → in_progress).
     */
    public function startAssignment(TaskAssignment $assignment, Employee $employee): TaskAssignment
    {
        $this->guardEmployeeOwnsAssignment($assignment, $employee);

        $allowedFrom = ['assigned', 'accepted'];
        if (! in_array($assignment->status, $allowedFrom, true)) {
            throw InvalidTaskStatusTransitionException::from($assignment->status, 'in_progress');
        }

        return DB::transaction(function () use ($assignment, $employee) {
            $assignment->update(['status' => 'in_progress']);

            $this->recordActivity($assignment->task, 'task.started', $employee->user);

            return $assignment->fresh();
        });
    }

    /**
     * Submit an assignment (in_progress/accepted → submitted).
     */
    public function submitAssignment(TaskAssignment $assignment, Employee $employee, ?string $remarks = null): TaskAssignment
    {
        $this->guardEmployeeOwnsAssignment($assignment, $employee);

        $submittedStatuses = ['in_progress', 'accepted'];
        if (! in_array($assignment->status, $submittedStatuses, true)) {
            throw InvalidTaskStatusTransitionException::from($assignment->status, 'submitted');
        }

        if ($assignment->task->status === 'cancelled') {
            throw new \RuntimeException('Cannot submit assignments for cancelled tasks.');
        }

        return DB::transaction(function () use ($assignment, $employee, $remarks) {
            $assignment->update([
                'status' => 'submitted',
                'remarks' => $remarks,
            ]);

            $this->recordActivity($assignment->task, 'task.submitted', $employee->user);

            TaskSubmitted::dispatch($assignment->fresh());

            return $assignment->fresh();
        });
    }

    /**
     * Resubmit a rejected assignment (rejected → submitted).
     */
    public function resubmitAssignment(TaskAssignment $assignment, Employee $employee): TaskAssignment
    {
        $this->guardEmployeeOwnsAssignment($assignment, $employee);

        if ($assignment->status !== 'rejected') {
            throw InvalidTaskStatusTransitionException::from($assignment->status, 'submitted');
        }

        return DB::transaction(function () use ($assignment, $employee) {
            $assignment->update([
                'status' => 'submitted',
                'remarks' => null,
            ]);

            $this->recordActivity($assignment->task, 'task.resubmitted', $employee->user);

            TaskResubmitted::dispatch($assignment->fresh());

            return $assignment->fresh();
        });
    }

    /**
     * @param  Collection<int, TaskAssignment>|array<int, TaskAssignment>  $assignments
     */
    private function guardEmployeeOwnsAssignment(TaskAssignment $assignment, Employee $employee): void
    {
        if ($assignment->employee_id !== $employee->id) {
            throw new \RuntimeException('Employee does not own this assignment.');
        }
    }

    private function recordActivity(Task $task, string $event, ?User $user): void
    {
        app(ActivityLogService::class)->log(
            $user,
            $event,
            $task,
            null,
            ['task_code' => $task->task_code],
        );
    }
}
