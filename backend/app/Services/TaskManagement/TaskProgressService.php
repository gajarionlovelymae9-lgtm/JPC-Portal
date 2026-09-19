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
     * Record a progress update. Progress must move forward only.
     */
    public function updateProgress(TaskAssignment $assignment, float $percentage, ?string $comment, Employee $employee): TaskProgress
    {
        if ($percentage < 0 || $percentage > 100) {
            throw InvalidProgressException::outOfBounds($percentage);
        }

        $latest = $assignment->progress()->latest()->first();

        if ($latest !== null && $percentage < $latest->new_percentage) {
            throw InvalidProgressException::backwardProgress($latest->new_percentage, $percentage);
        }

        return DB::transaction(function () use ($assignment, $percentage, $comment, $employee, $latest) {
            $progress = TaskProgress::query()->create([
                'task_id' => $assignment->task_id,
                'task_assignment_id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'previous_percentage' => $latest?->new_percentage ?? 0,
                'new_percentage' => $percentage,
                'status' => $percentage === 100 ? 'completed' : 'in_progress',
                'comment' => $comment,
                'updated_by' => $employee->user_id,
            ]);

            $assignment->update([
                'progress_percentage' => $percentage,
                'completed_at' => $percentage === 100 ? now() : ($assignment->completed_at ?? null),
            ]);

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
