<?php

namespace Tests\Feature\Planning;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskProgress;
use App\Models\TaskReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCollaborationTest extends PlanningTestCase
{
    public function test_task_progress_relationships_and_casts(): void
    {
        $context = $this->chain();

        $progress = TaskProgress::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'employee_id' => $context['employee']->id,
            'previous_percentage' => 40,
            'new_percentage' => 65,
            'status' => 'in_progress',
            'comment' => 'Draft section completed.',
            'updated_by' => $context['user']->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $progress->task());
        $this->assertInstanceOf(Task::class, $progress->task);
        $this->assertInstanceOf(TaskAssignment::class, $progress->assignment);
        $this->assertSame($context['employee']->id, $progress->employee->id);
        $this->assertSame($context['user']->id, $progress->updatedBy->id);

        $this->assertSame('40.00', $progress->previous_percentage);
        $this->assertSame('65.00', $progress->new_percentage);
    }

    public function test_task_progress_is_append_only_without_soft_deletes(): void
    {
        $context = $this->chain();

        $progress = TaskProgress::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'employee_id' => $context['employee']->id,
            'previous_percentage' => 0,
            'new_percentage' => 10,
        ]);

        $this->assertFalse(method_exists($progress, 'trashed'));
        $this->assertTrue($context['assignment']->progress->contains($progress));
    }

    public function test_task_comment_relationships_and_soft_delete(): void
    {
        $context = $this->chain();

        $comment = TaskComment::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'user_id' => $context['user']->id,
            'comment' => 'Please attach the signed copy.',
        ]);

        $this->assertInstanceOf(BelongsTo::class, $comment->task());
        $this->assertInstanceOf(Task::class, $comment->task);
        $this->assertInstanceOf(TaskAssignment::class, $comment->assignment);
        $this->assertInstanceOf(User::class, $comment->user);
        $this->assertTrue($context['task']->comments->contains($comment));

        $comment->delete();
        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
        $this->assertNotNull(TaskComment::withTrashed()->find($comment->id));
    }

    public function test_task_attachment_relationships_and_casts(): void
    {
        $context = $this->chain();

        $attachment = TaskAttachment::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'uploaded_by' => $context['user']->id,
            'file_name' => 'report.pdf',
            'file_path' => 'task-attachments/report.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 204800,
        ]);

        $this->assertInstanceOf(Task::class, $attachment->task);
        $this->assertInstanceOf(TaskAssignment::class, $attachment->assignment);
        $this->assertInstanceOf(User::class, $attachment->uploadedBy);
        $this->assertSame(204800, $attachment->file_size);

        $attachment->delete();
        $this->assertSoftDeleted('task_attachments', ['id' => $attachment->id]);
    }

    public function test_task_review_relationships_casts_and_scopes(): void
    {
        $context = $this->chain();

        $approved = TaskReview::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'reviewed_by' => $context['user']->id,
            'decision' => 'approved',
            'remarks' => 'Well documented.',
            'reviewed_at' => now(),
        ]);

        $this->assertInstanceOf(BelongsTo::class, $approved->task());
        $this->assertInstanceOf(Task::class, $approved->task);
        $this->assertInstanceOf(TaskAssignment::class, $approved->assignment);
        $this->assertInstanceOf(User::class, $approved->reviewedBy);
        $this->assertInstanceOf(Carbon::class, $approved->reviewed_at);
        $this->assertTrue($context['task']->reviews->contains($approved));

        $rejected = TaskReview::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'reviewed_by' => $context['user']->id,
            'decision' => 'rejected',
        ]);
        $revision = TaskReview::query()->create([
            'task_id' => $context['task']->id,
            'task_assignment_id' => $context['assignment']->id,
            'reviewed_by' => $context['user']->id,
            'decision' => 'needs_revision',
        ]);

        $this->assertTrue(TaskReview::query()->approved()->pluck('id')->contains($approved->id));
        $this->assertTrue(TaskReview::query()->rejected()->pluck('id')->contains($rejected->id));
        $this->assertTrue(TaskReview::query()->needsRevision()->pluck('id')->contains($revision->id));
    }

    public function test_reviews_are_append_only_without_soft_deletes(): void
    {
        $review = new TaskReview;

        $this->assertFalse(method_exists($review, 'trashed'));
        $this->assertTrue($review->isFillable('decision'));
        $this->assertFalse($review->isFillable('id'));
    }
}
