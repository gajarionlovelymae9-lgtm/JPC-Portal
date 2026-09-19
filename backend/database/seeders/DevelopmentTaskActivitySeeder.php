<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskProgress;
use App\Models\TaskReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the collaboration history around development tasks: append-only
 * progress entries, comments, attachment metadata and task reviews.
 *
 * Attachment records are METADATA ONLY — no physical files are created or
 * referenced on disk (see Phase 3.1-D Step 24: never seed broken storage
 * references). The file_path value is an inert development label.
 *
 * Covers all three canonical review decisions: approved, rejected and
 * needs_revision (docs/database/planning-task-management.md §11).
 *
 * DEVELOPMENT ONLY. Gated by config("jpc.seed_development.enabled") and skips
 * when task progress already exists so repeated db:seed runs never duplicate.
 */
class DevelopmentTaskActivitySeeder extends Seeder
{
    /**
     * Task-level comments: [task_code, author_email, comment, employee|null].
     *
     * @return array<int, array{code: string, author: string, comment: string, employee: string|null}>
     */
    private function comments(): array
    {
        return [
            ['code' => 'TASK-2026-0024', 'author' => 'admin@jpc.local', 'comment' => 'Parent documentation task created and broken down into three subtasks.', 'employee' => null],
            ['code' => 'TASK-2026-0001', 'author' => 'departmenthead@jpc.local', 'comment' => 'Please ensure the report follows the standard faculty report template.', 'employee' => 'DEV-0005'],
            ['code' => 'TASK-2026-0005', 'author' => 'admin@jpc.local', 'comment' => 'Accomplishment report received and forwarded for approval.', 'employee' => 'DEV-0004'],
            ['code' => 'TASK-2026-0008', 'author' => 'departmenthead@jpc.local', 'comment' => 'Survey breakdown tables are missing. Revise and resubmit.', 'employee' => 'DEV-0007'],
            ['code' => 'TASK-2026-0017', 'author' => 'admin@jpc.local', 'comment' => 'Outcomes-based syllabus submitted and queued for review.', 'employee' => 'DEV-0004'],
            ['code' => 'TASK-2026-0022', 'author' => 'departmenthead@jpc.local', 'comment' => 'Compliance report received after the deadline; noted for the record.', 'employee' => 'DEV-0008'],
        ];
    }

    /**
     * Attachment metadata: [task_code, employee|null, uploader_email, file_name, mime_type, file_size].
     *
     * @return array<int, array{code: string, employee: string|null, uploader: string, name: string, mime: string, size: int}>
     */
    private function attachments(): array
    {
        return [
            ['code' => 'TASK-2026-0001', 'employee' => 'DEV-0005', 'uploader' => 'admin@jpc.local', 'name' => 'monthly_faculty_report_draft.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => 184_320],
            ['code' => 'TASK-2026-0005', 'employee' => 'DEV-0004', 'uploader' => 'admin@jpc.local', 'name' => 'faculty_accomplishment_july_2026.pdf', 'mime' => 'application/pdf', 'size' => 512_000],
            ['code' => 'TASK-2026-0008', 'employee' => 'DEV-0007', 'uploader' => 'admin@jpc.local', 'name' => 'business_faculty_survey_v1.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'size' => 96_256],
            ['code' => 'TASK-2026-0017', 'employee' => 'DEV-0004', 'uploader' => 'admin@jpc.local', 'name' => 'obe_course_syllabus.pdf', 'mime' => 'application/pdf', 'size' => 348_160],
            ['code' => 'TASK-2026-0022', 'employee' => 'DEV-0008', 'uploader' => 'admin@jpc.local', 'name' => 'academic_compliance_report.pdf', 'mime' => 'application/pdf', 'size' => 421_888],
        ];
    }

    /**
     * Reviews: [task_code, employee, reviewer_email, decision, remarks, reviewed_at].
     *
     * @return array<int, array{code: string, employee: string, reviewer: string, decision: string, remarks: string, reviewed_at: string}>
     */
    private function reviews(): array
    {
        return [
            ['code' => 'TASK-2026-0005', 'employee' => 'DEV-0004', 'reviewer' => 'admin@jpc.local', 'decision' => 'approved', 'remarks' => 'Documentation is complete and consistent with the required output.', 'reviewed_at' => '2026-07-18 10:00:00'],
            ['code' => 'TASK-2026-0006', 'employee' => 'DEV-0006', 'reviewer' => 'admin@jpc.local', 'decision' => 'approved', 'remarks' => 'Student progress narrative is clear and complete.', 'reviewed_at' => '2026-09-11 11:00:00'],
            ['code' => 'TASK-2026-0008', 'employee' => 'DEV-0007', 'reviewer' => 'departmenthead@jpc.local', 'decision' => 'rejected', 'remarks' => 'Documentation is incomplete. Please revise and resubmit.', 'reviewed_at' => '2026-08-18 15:30:00'],
            ['code' => 'TASK-2026-0010', 'employee' => 'DEV-0006', 'reviewer' => 'admin@jpc.local', 'decision' => 'needs_revision', 'remarks' => 'Additional supporting document required.', 'reviewed_at' => '2026-07-14 12:00:00'],
            ['code' => 'TASK-2026-0013', 'employee' => 'DEV-0012', 'reviewer' => 'admin@jpc.local', 'decision' => 'approved', 'remarks' => 'Comprehensive college accomplishment report.', 'reviewed_at' => '2026-08-09 15:00:00'],
            ['code' => 'TASK-2026-0017', 'employee' => 'DEV-0004', 'reviewer' => 'departmenthead@jpc.local', 'decision' => 'needs_revision', 'remarks' => 'Additional supporting document required.', 'reviewed_at' => '2026-08-06 09:00:00'],
            ['code' => 'TASK-2026-0022', 'employee' => 'DEV-0008', 'reviewer' => 'departmenthead@jpc.local', 'decision' => 'approved', 'remarks' => 'Compliance report accepted despite the late submission.', 'reviewed_at' => '2026-08-20 10:00:00'],
        ];
    }

    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentTaskActivitySeeder skipped: development seeding is disabled.');

            return;
        }

        if (TaskProgress::query()->count() > 0 || TaskReview::query()->count() > 0) {
            $this->command?->warn('DevelopmentTaskActivitySeeder skipped: development task activity already exists.');

            return;
        }

        DB::transaction(function () {
            $tasks = Task::query()->pluck('id', 'task_code')->all();
            $users = User::query()->pluck('id', 'email')->all();

            $assignments = TaskAssignment::query()
                ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
                ->join('employees', 'employees.id', '=', 'task_assignments.employee_id')
                ->get([
                    'task_assignments.id',
                    'task_assignments.task_id',
                    'task_assignments.employee_id',
                    'task_assignments.progress_percentage',
                    'task_assignments.status',
                    'tasks.task_code',
                    'employees.employee_number',
                ])
                ->keyBy(fn ($row) => $row->task_code.'|'.$row->employee_number);

            $this->seedProgress($assignments, $users);
            $this->seedComments($tasks, $assignments, $users);
            $this->seedAttachments($tasks, $assignments, $users);
            $this->seedReviews($tasks, $assignments, $users);
        });
    }

    /**
     * @param  Collection<string, object>  $assignments
     * @param  array<string, int>  $users
     */
    private function seedProgress($assignments, array $users): void
    {
        $recorderId = $users['admin@jpc.local'];
        $messages = [
            'Initial progress recorded.',
            'Draft section completed.',
            'Supporting documents gathered.',
            'Final review and proofreading in progress.',
        ];

        foreach ($assignments as $assignment) {
            $target = (float) $assignment->progress_percentage;
            if ($target <= 0) {
                continue;
            }

            $steps = [];
            $previous = 0;
            while ($previous < $target) {
                $next = min($target, $previous + 25);
                $steps[] = [$previous, $next];
                $previous = $next;
            }

            foreach ($steps as $index => [$from, $to]) {
                $isLast = $index === count($steps) - 1;

                TaskProgress::updateOrCreate(
                    [
                        'task_assignment_id' => $assignment->id,
                        'previous_percentage' => $from,
                        'new_percentage' => $to,
                    ],
                    [
                        'task_id' => $assignment->task_id,
                        'employee_id' => $assignment->employee_id,
                        'status' => $isLast ? $assignment->status : 'in_progress',
                        'comment' => $messages[$index % count($messages)],
                        'updated_by' => $recorderId,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, int>  $tasks
     * @param  Collection<string, object>  $assignments
     * @param  array<string, int>  $users
     */
    private function seedComments(array $tasks, $assignments, array $users): void
    {
        foreach ($this->comments() as $comment) {
            if (! isset($tasks[$comment['code']])) {
                continue;
            }

            $assignmentId = $comment['employee'] !== null
                ? ($assignments[$comment['code'].'|'.$comment['employee']]->id ?? null)
                : null;

            TaskComment::updateOrCreate(
                [
                    'task_id' => $tasks[$comment['code']],
                    'user_id' => $users[$comment['author']],
                    'comment' => $comment['comment'],
                ],
                ['task_assignment_id' => $assignmentId],
            );
        }
    }

    /**
     * @param  array<string, int>  $tasks
     * @param  Collection<string, object>  $assignments
     * @param  array<string, int>  $users
     */
    private function seedAttachments(array $tasks, $assignments, array $users): void
    {
        foreach ($this->attachments() as $attachment) {
            if (! isset($tasks[$attachment['code']])) {
                continue;
            }

            $assignmentId = $attachment['employee'] !== null
                ? ($assignments[$attachment['code'].'|'.$attachment['employee']]->id ?? null)
                : null;

            TaskAttachment::updateOrCreate(
                [
                    'task_id' => $tasks[$attachment['code']],
                    'file_name' => $attachment['name'],
                ],
                [
                    'task_assignment_id' => $assignmentId,
                    'uploaded_by' => $users[$attachment['uploader']],
                    'file_path' => 'development/task-attachments/'.$attachment['name'],
                    'mime_type' => $attachment['mime'],
                    'file_size' => $attachment['size'],
                ],
            );
        }
    }

    /**
     * @param  array<string, int>  $tasks
     * @param  Collection<string, object>  $assignments
     * @param  array<string, int>  $users
     */
    private function seedReviews(array $tasks, $assignments, array $users): void
    {
        foreach ($this->reviews() as $review) {
            $key = $review['code'].'|'.$review['employee'];

            if (! isset($tasks[$review['code']], $assignments[$key])) {
                continue;
            }

            TaskReview::updateOrCreate(
                [
                    'task_id' => $tasks[$review['code']],
                    'task_assignment_id' => $assignments[$key]->id,
                    'decision' => $review['decision'],
                ],
                [
                    'reviewed_by' => $users[$review['reviewer']],
                    'remarks' => $review['remarks'],
                    'reviewed_at' => $review['reviewed_at'],
                ],
            );
        }
    }
}
