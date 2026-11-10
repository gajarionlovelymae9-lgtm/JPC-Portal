<?php

namespace App\Services\TaskManagement;

use App\Exceptions\TaskManagement\InvalidProgressException;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskProgress;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Support\Facades\DB;

class TaskProgressService
{
    /**
     * Record a progress update.
     *
     * Progress normally moves forward, but an assignment returned for revision
     * may legitimately move backwards while the employee reworks it.
     */
    public function updateProgress(TaskAssignment $assignment, float $percentage, ?string $comment, Employee $employee): TaskProgress
    {
        if ($percentage < 0 || $percentage > 100) {
            throw InvalidProgressException::outOfBounds($percentage);
        }

        $latest = $assignment->progress()->latest()->first();
        $isRevision = $assignment->status === 'rejected';

        if (! $isRevision && $latest !== null && $percentage < $latest->new_percentage) {
            throw InvalidProgressException::backwardProgress($latest->new_percentage, $percentage);
        }

        return DB::transaction(function () use ($assignment, $percentage, $comment, $employee, $latest, $isRevision) {
            $progress = TaskProgress::query()->create([
                'task_id' => $assignment->task_id,
                'task_assignment_id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'previous_percentage' => $latest?->new_percentage ?? 0,
                'new_percentage' => $percentage,
                // A progress update is never a submission; it only moves work
                // forward. Submission is a separate, explicit action.
                'status' => 'in_progress',
                'comment' => $comment,
                'updated_by' => $employee->user_id,
            ]);

            $attributes = [
                'progress_percentage' => $percentage,
                // Completion is stamped only when work is approved. Any stale
                // value from an older submission leaves with this update.
                'completed_at' => null,
            ];

            if (! $isRevision && in_array($assignment->status, ['assigned', 'accepted'], true)) {
                $attributes['status'] = 'in_progress';
            }

            $assignment->update($attributes);

            app(ActivityLogService::class)->log(
                $employee->user,
                'progress.updated',
                $assignment->task,
                "{$percentage}% progress on {$assignment->task->task_code}.",
                [
                    'task_code' => $assignment->task->task_code,
                    'assignment_id' => $assignment->id,
                    'previous_percentage' => $latest?->new_percentage,
                    'new_percentage' => $percentage,
                ],
            );

            app(TaskService::class)->syncAggregateStatus($assignment->task);

            return $progress;
        });
    }

    /**
     * Calculate the arithmetic average completion across assignments.
     */
    public function getCompletionRate(Task $task): float
    {
        $assignments = $task->assignments;

        if ($assignments->isEmpty()) {
            return 0.0;
        }

        return round($assignments->pluck('progress_percentage')->average(), 2);
    }
}
