<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Services\TaskManagement\TaskService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Repair task records written before approval forced progress to 100%.
 *
 * The command is read-only by default. Pass --apply to run the correction;
 * a JSON snapshot of every affected row is written to storage/app/repairs
 * before anything changes so the operation is reversible.
 */
class RepairTaskProgress extends Command
{
    protected $signature = 'tasks:repair-progress
        {--apply : Persist the repairs (default is a dry run)}';

    protected $description = 'Repair approved assignments below 100% and missing task completed dates';

    public function handle(TaskService $taskService): int
    {
        $assignments = TaskAssignment::withTrashed()
            ->with('task')
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->where('progress_percentage', '<', 100)
                    ->orWhereNull('completed_at');
            })
            ->orderBy('id')
            ->get();

        $completedTasks = Task::withTrashed()
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->orderBy('id')
            ->get();

        $reopenedTasks = Task::withTrashed()
            ->where('status', '!=', 'completed')
            ->whereNotNull('completed_at')
            ->orderBy('id')
            ->get();

        if ($assignments->isEmpty() && $completedTasks->isEmpty() && $reopenedTasks->isEmpty()) {
            $this->info('No inconsistent task progress records found.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($assignments as $assignment) {
            $approvedAt = TaskReview::query()
                ->where('task_assignment_id', $assignment->id)
                ->where('decision', 'approved')
                ->latest('reviewed_at')
                ->value('reviewed_at');

            $rows[] = [
                'type' => 'assignment',
                'id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'status' => $assignment->status,
                'progress_before' => (float) $assignment->progress_percentage,
                'progress_after' => 100,
                'completed_at_before' => optional($assignment->completed_at)->toIso8601String(),
                'completed_at_after' => optional($assignment->completed_at)->toIso8601String()
                    ?? ($approvedAt !== null ? Carbon::parse($approvedAt)->toIso8601String() : now()->toIso8601String()),
            ];
        }

        foreach ($completedTasks as $task) {
            $latestAssignmentCompletion = TaskAssignment::withTrashed()
                ->where('task_id', $task->id)
                ->max('completed_at');

            $rows[] = [
                'type' => 'task_completed_at',
                'id' => $task->id,
                'task_code' => $task->task_code,
                'completed_at_before' => null,
                'completed_at_after' => $latestAssignmentCompletion
                    ?? $task->updated_at?->toIso8601String(),
            ];
        }

        foreach ($reopenedTasks as $task) {
            $rows[] = [
                'type' => 'task_reopened',
                'id' => $task->id,
                'task_code' => $task->task_code,
                'status' => $task->status,
                'completed_at_before' => optional($task->completed_at)->toIso8601String(),
                'completed_at_after' => null,
            ];
        }

        $this->table(
            ['Type', 'ID', 'Task', 'Before', 'After'],
            collect($rows)->map(fn (array $row) => [
                $row['type'],
                $row['id'],
                $row['task_code'] ?? $row['task_id'] ?? '—',
                $row['progress_before'] ?? $row['completed_at_before'] ?? '—',
                $row['progress_after'] ?? $row['completed_at_after'] ?? '—',
            ])->all(),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply to persist these repairs.');

            return self::SUCCESS;
        }

        $snapshotPath = 'repairs/task-progress-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($snapshotPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        DB::transaction(function () use ($assignments, $completedTasks, $reopenedTasks, $taskService) {
            foreach ($assignments as $assignment) {
                $approvedAt = TaskReview::query()
                    ->where('task_assignment_id', $assignment->id)
                    ->where('decision', 'approved')
                    ->latest('reviewed_at')
                    ->value('reviewed_at');

                $assignment->update([
                    'progress_percentage' => 100,
                    'completed_at' => $assignment->completed_at
                        ?? ($approvedAt !== null ? Carbon::parse($approvedAt) : now()),
                ]);

                if ($assignment->task !== null) {
                    $taskService->syncAggregateStatus($assignment->task);
                }
            }

            foreach ($completedTasks as $task) {
                $latestAssignmentCompletion = TaskAssignment::withTrashed()
                    ->where('task_id', $task->id)
                    ->max('completed_at');

                $task->update([
                    'completed_at' => $latestAssignmentCompletion ?? $task->updated_at ?? now(),
                ]);
            }

            foreach ($reopenedTasks as $task) {
                $task->update(['completed_at' => null]);
            }
        });

        $this->info("Repaired {$assignments->count()} assignment(s), {$completedTasks->count()} completed task date(s), and {$reopenedTasks->count()} reopened task date(s).");
        $this->info("Snapshot written to storage/app/{$snapshotPath}");

        return self::SUCCESS;
    }
}
