<?php

namespace App\Services\TaskManagement;

use App\Events\TaskManagement\TaskReviewed;
use App\Exceptions\TaskManagement\InvalidTaskReviewException;
use App\Models\Employee;
use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Support\Facades\DB;

class TaskReviewService
{
    private const AUTHORIZED_ROLES = ['admin', 'department_head'];

    /**
     * Approve an assignment (submitted → completed).
     */
    public function approve(TaskAssignment $assignment, User $reviewer, ?string $remarks): TaskReview
    {
        $this->guardAuthorized($reviewer);
        $this->guardNotSelfApproval($assignment, $reviewer);
        $this->guardReviewableState($assignment);

        $remarks = $remarks ?? 'Approved.';

        return DB::transaction(function () use ($assignment, $reviewer, $remarks) {
            $review = TaskReview::query()->create([
                'task_id' => $assignment->task_id,
                'task_assignment_id' => $assignment->id,
                'reviewed_by' => $reviewer->id,
                'decision' => 'approved',
                'remarks' => $remarks,
                'reviewed_at' => now(),
            ]);

            // Approval is the only action that closes an assignment. Force the
            // progress value to 100 so an approved assignment can never keep an
            // incomplete percentage, and stamp the approval completion time.
            $assignment->update([
                'status' => 'completed',
                'progress_percentage' => 100,
                'completed_at' => now(),
            ]);

            $this->recordActivity($assignment, $reviewer, 'approved', $remarks);
            app(TaskService::class)->syncAggregateStatus($assignment->task);
            TaskReviewed::dispatch($assignment->fresh(), $reviewer, $review);

            return $review;
        });
    }

    /**
     * Reject an assignment (submitted → rejected).
     */
    public function reject(TaskAssignment $assignment, User $reviewer, string $remarks): TaskReview
    {
        $this->guardAuthorized($reviewer);
        $this->guardNotSelfApproval($assignment, $reviewer);
        $this->guardReviewableState($assignment);

        return DB::transaction(function () use ($assignment, $reviewer, $remarks) {
            $review = TaskReview::query()->create([
                'task_id' => $assignment->task_id,
                'task_assignment_id' => $assignment->id,
                'reviewed_by' => $reviewer->id,
                'decision' => 'rejected',
                'remarks' => $remarks,
                'reviewed_at' => now(),
            ]);

            $assignment->update([
                'status' => 'rejected',
                // Reopening work means the assignment is no longer completed.
                // Progress is preserved so the employee can continue from where
                // they left off.
                'completed_at' => null,
            ]);

            $this->recordActivity($assignment, $reviewer, 'rejected', $remarks);
            app(TaskService::class)->syncAggregateStatus($assignment->task);
            TaskReviewed::dispatch($assignment->fresh(), $reviewer, $review);

            return $review;
        });
    }

    /**
     * Request revision (submitted → rejected with needs_revision decision).
     */
    public function requestRevision(TaskAssignment $assignment, User $reviewer, string $remarks): TaskReview
    {
        $this->guardAuthorized($reviewer);
        $this->guardNotSelfApproval($assignment, $reviewer);
        $this->guardReviewableState($assignment);

        return DB::transaction(function () use ($assignment, $reviewer, $remarks) {
            $review = TaskReview::query()->create([
                'task_id' => $assignment->task_id,
                'task_assignment_id' => $assignment->id,
                'reviewed_by' => $reviewer->id,
                'decision' => 'needs_revision',
                'remarks' => $remarks,
                'reviewed_at' => now(),
            ]);

            $assignment->update([
                'status' => 'rejected',
                // Revision keeps the employee's actual progress but clears the
                // completion stamp until the work is approved again.
                'completed_at' => null,
            ]);

            $this->recordActivity($assignment, $reviewer, 'needs_revision', $remarks);
            app(TaskService::class)->syncAggregateStatus($assignment->task);
            TaskReviewed::dispatch($assignment->fresh(), $reviewer, $review);

            return $review;
        });
    }

    /**
     * Check whether a user is an authorized reviewer.
     */
    public function isAuthorizedReviewer(User $user): bool
    {
        return in_array($user->roleName(), self::AUTHORIZED_ROLES, true);
    }

    private function guardAuthorized(User $reviewer): void
    {
        if (! $this->isAuthorizedReviewer($reviewer)) {
            throw InvalidTaskReviewException::unauthorized();
        }
    }

    private function guardNotSelfApproval(TaskAssignment $assignment, User $reviewer): void
    {
        $employeeUserId = Employee::query()->where('id', $assignment->employee_id)->value('user_id');

        if ($employeeUserId !== null && $reviewer->id === (int) $employeeUserId) {
            throw InvalidTaskReviewException::selfApproval();
        }
    }

    private function guardReviewableState(TaskAssignment $assignment): void
    {
        if (! in_array($assignment->status, ['submitted', 'under_review'], true)) {
            throw InvalidTaskReviewException::invalidState($assignment->status);
        }
    }

    private function recordActivity(
        TaskAssignment $assignment,
        User $reviewer,
        string $decision,
        string $remarks,
    ): void {
        app(ActivityLogService::class)->log(
            $reviewer,
            'task.reviewed',
            $assignment->task,
            "{$assignment->task->task_code} reviewed as {$decision}.",
            [
                'task_code' => $assignment->task->task_code,
                'assignment_id' => $assignment->id,
                'decision' => $decision,
                'remarks' => $remarks,
            ],
        );
    }
}
