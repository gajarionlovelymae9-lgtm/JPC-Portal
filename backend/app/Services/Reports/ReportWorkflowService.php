<?php

namespace App\Services\Reports;

use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportFile;
use App\Models\ReportReview;
use App\Models\ReportTimeline;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReportWorkflowService
{
    public function __construct(
        protected ReportVersionService $versionService,
        protected ReportReviewService $reviewService,
    ) {}

    /**
     * The deadline a report is measured against.
     *
     * The report's own `deadline` wins; otherwise the parent task's `due_date`.
     * This is the single definition of an "effective deadline" for reports and
     * it matches the rule the dashboards use for overdue task assignments
     * (`task_assignments.due_date` falling back to `tasks.due_date`).
     */
    public function effectiveDeadline(?Report $report, ?Task $task = null): ?Carbon
    {
        $deadline = $report?->deadline ?? $task?->due_date;

        if ($deadline === null) {
            return null;
        }

        return $deadline instanceof Carbon ? $deadline->copy() : Carbon::parse($deadline);
    }

    /**
     * Whether a submission arrived after its effective deadline.
     *
     * Judged against the actual submission timestamp rather than `now()`, so a
     * report that was handed in on time is not retroactively marked late just
     * because it sat in the review queue.
     */
    private function submittedLate(?Carbon $submittedAt, ?Report $report, ?Task $task = null): bool
    {
        if ($submittedAt === null) {
            return false;
        }

        $deadline = $this->effectiveDeadline($report, $task);

        return $deadline !== null && $submittedAt->gt($deadline);
    }

    /**
     * Create a new Report Draft for an employee.
     */
    public function createDraft(User $user, array $data, ?UploadedFile $file = null): Report
    {
        $employee = $user->employee;
        if (! $employee && $user->roleName() === 'employee') {
            throw new AccessDeniedHttpException('No employee record associated with this account.');
        }

        $employeeId = $data['employee_id'] ?? $employee?->id;
        if (! $employeeId) {
            throw new UnprocessableEntityHttpException('Employee ID is required.');
        }

        // Verify task exists and is assigned
        $task = Task::findOrFail($data['task_id']);

        return DB::transaction(function () use ($user, $data, $employeeId, $task, $file) {
            // A draft is not a submission, so there is no submission timestamp
            // to judge lateness against yet — `submit()` decides this.
            $isLate = false;

            $report = Report::create([
                'task_id' => $task->id,
                'employee_id' => $employeeId,
                'title' => $data['title'],
                'content' => $data['content'] ?? null,
                'references' => $data['references'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'version' => 1,
                'status' => ReportStatus::Draft,
                'ai_status' => 'pending',
                'is_late' => $isLate,
            ]);

            $filePath = null;
            if ($file) {
                $path = $file->store("reports/{$report->id}", 'public');
                $filePath = $path;
                ReportFile::create([
                    'report_id' => $report->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getClientMimeType(),
                ]);
            }

            // Create initial v1 snapshot
            $version = $this->versionService->createSnapshot(
                $report,
                $user,
                notes: 'Initial draft created.',
                filePath: $filePath,
                versionNumber: 1
            );

            // Log timeline
            ReportTimeline::create([
                'report_id' => $report->id,
                'report_version_id' => $version->id,
                'actor_id' => $user->id,
                'event_type' => 'draft_created',
                'title' => 'Report Draft Created',
                'description' => "{$user->name} created draft for report: {$report->title}",
                'metadata' => [
                    'version' => 1,
                    'task_id' => $task->id,
                ],
            ]);

            return $report->load(['files', 'task', 'employee']);
        });
    }

    /**
     * Update an existing draft report.
     */
    public function updateDraft(User $user, Report $report, array $data, ?UploadedFile $file = null): Report
    {
        $this->assertStatus($report, [ReportStatus::Draft], 'Cannot update a submitted or locked report.');

        return DB::transaction(function () use ($user, $report, $data, $file) {
            $task = $report->task;
            // Lateness is a property of the submission, so an edit to the draft
            // must not flip the flag — only `submit()`/`submitRevision()` do.
            $isLate = (bool) $report->is_late;

            $report->update([
                'title' => $data['title'] ?? $report->title,
                'content' => array_key_exists('content', $data) ? $data['content'] : $report->content,
                'references' => array_key_exists('references', $data) ? $data['references'] : $report->references,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $report->remarks,
                'is_late' => $isLate,
            ]);

            $filePath = null;
            if ($file) {
                $path = $file->store("reports/{$report->id}", 'public');
                $filePath = $path;
                ReportFile::create([
                    'report_id' => $report->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getClientMimeType(),
                ]);
            }

            $version = $this->versionService->createSnapshot(
                $report,
                $user,
                notes: 'Draft updated.',
                filePath: $filePath,
                versionNumber: $report->version ?? 1
            );

            ReportTimeline::create([
                'report_id' => $report->id,
                'report_version_id' => $version->id,
                'actor_id' => $user->id,
                'event_type' => 'draft_updated',
                'title' => 'Draft Updated',
                'description' => "{$user->name} updated report draft.",
                'metadata' => [
                    'version' => $report->version ?? 1,
                ],
            ]);

            return $report->fresh(['files', 'task', 'employee']);
        });
    }

    /**
     * Submit report (draft -> submitted -> under_review / processing).
     */
    public function submit(User $user, Report $report, ?string $notes = null, ?UploadedFile $file = null): Report
    {
        $this->assertStatus($report, [ReportStatus::Draft], 'Only draft reports can be submitted.');

        return DB::transaction(function () use ($user, $report, $notes, $file) {
            $task = $report->task;
            $submittedAt = Carbon::now();
            $isLate = $this->submittedLate($submittedAt, $report, $task);

            $filePath = null;
            if ($file) {
                $path = $file->store("reports/{$report->id}", 'public');
                $filePath = $path;
                ReportFile::create([
                    'report_id' => $report->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getClientMimeType(),
                ]);
            }

            $report->update([
                'status' => ReportStatus::Submitted,
                'submitted_at' => $submittedAt,
                'is_late' => $isLate,
                'ai_status' => 'processing',
            ]);

            $version = $this->versionService->createSnapshot(
                $report,
                $user,
                notes: $notes ?? 'Submitted for review.',
                filePath: $filePath,
                versionNumber: $report->version ?? 1
            );

            // Log timeline
            ReportTimeline::create([
                'report_id' => $report->id,
                'report_version_id' => $version->id,
                'actor_id' => $user->id,
                'event_type' => 'submitted',
                'title' => 'Report Submitted',
                'description' => "{$user->name} submitted report (v{$report->version}) for review.".($isLate ? ' [LATE SUBMISSION]' : ''),
                'metadata' => [
                    'version' => $report->version ?? 1,
                    'is_late' => $isLate,
                    'submitted_at' => Carbon::now()->toIso8601String(),
                ],
            ]);

            // Audit
            AuditLog::forceCreate([
                'user_id' => $user->id,
                'action' => 'report.submitted',
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'new_values' => [
                    'status' => 'submitted',
                    'version' => $report->version ?? 1,
                    'is_late' => $isLate,
                ],
            ]);

            // Automatically move from submitted to under_review
            $report->update(['status' => ReportStatus::UnderReview]);

            return $report->fresh(['files', 'task', 'employee', 'versions']);
        });
    }

    /**
     * Request a revision on a submitted/under-review report.
     */
    public function requestRevision(User $reviewer, Report $report, string $reason, ?array $comments = null): Report
    {
        $this->assertStatus($report, [ReportStatus::Submitted, ReportStatus::UnderReview, ReportStatus::Processing], 'Can only request revision on reports under review.');

        return DB::transaction(function () use ($reviewer, $report, $reason, $comments) {
            $report->update([
                'status' => ReportStatus::RevisionRequired,
            ]);

            // Create review entry
            $review = ReportReview::create([
                'report_id' => $report->id,
                'reviewer_id' => $reviewer->id,
                'decision' => 'revision_required',
                'comments' => $reason,
                'reviewed_at' => Carbon::now(),
            ]);

            // Create review comments if provided
            if (! empty($comments)) {
                foreach ($comments as $c) {
                    $this->reviewService->addComment($reviewer, $report, [
                        'section_key' => $c['section_key'] ?? 'general',
                        'comment' => $c['comment'] ?? $c,
                        'version_number' => $report->version ?? 1,
                    ]);
                }
            }

            // Timeline
            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $reviewer->id,
                'event_type' => 'revision_requested',
                'title' => 'Revision Requested',
                'description' => "{$reviewer->name} requested revisions: {$reason}",
                'metadata' => [
                    'reason' => $reason,
                    'reviewer_id' => $reviewer->id,
                ],
            ]);

            // Audit
            AuditLog::forceCreate([
                'user_id' => $reviewer->id,
                'action' => 'report.revision_requested',
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'new_values' => [
                    'status' => 'revision_required',
                    'reason' => $reason,
                ],
            ]);

            // Notify Employee
            if ($report->employee && $report->employee->user) {
                Notification::create([
                    'user_id' => $report->employee->user->id,
                    'type' => 'revision',
                    'title' => "Revision Requested: {$report->title}",
                    'message' => "Your Department Head requested revisions on your report: {$reason}",
                ]);
            }

            return $report->fresh(['files', 'task', 'employee', 'reviews']);
        });
    }

    /**
     * Submit a revision (creates Version N+1, status -> resubmitted -> under_review).
     */
    public function submitRevision(User $user, Report $report, array $data, ?UploadedFile $file = null): Report
    {
        $this->assertStatus($report, [ReportStatus::RevisionRequired], 'Can only submit revision for reports marked as revision required.');

        return DB::transaction(function () use ($user, $report, $data, $file) {
            $newVersionNumber = ($report->version ?? 1) + 1;

            $task = $report->task;
            $submittedAt = Carbon::now();
            // Lateness is sticky: once a submission missed its deadline, a later
            // revision cannot un-late the report.
            $isLate = (bool) $report->is_late
                || $this->submittedLate($submittedAt, $report, $task);

            $filePath = null;
            if ($file) {
                $path = $file->store("reports/{$report->id}", 'public');
                $filePath = $path;
                ReportFile::create([
                    'report_id' => $report->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getClientMimeType(),
                ]);
            }

            $report->update([
                'title' => $data['title'] ?? $report->title,
                'content' => array_key_exists('content', $data) ? $data['content'] : $report->content,
                'references' => array_key_exists('references', $data) ? $data['references'] : $report->references,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $report->remarks,
                'version' => $newVersionNumber,
                'status' => ReportStatus::Resubmitted,
                'submitted_at' => $submittedAt,
                'is_late' => $isLate,
                'ai_status' => 'processing',
            ]);

            $changeSummary = $data['change_summary'] ?? $data['notes'] ?? 'Resubmitted with revisions.';

            $version = $this->versionService->createSnapshot(
                $report,
                $user,
                notes: $data['notes'] ?? null,
                changeSummary: $changeSummary,
                filePath: $filePath,
                versionNumber: $newVersionNumber
            );

            // Log timeline
            ReportTimeline::create([
                'report_id' => $report->id,
                'report_version_id' => $version->id,
                'actor_id' => $user->id,
                'event_type' => 'resubmitted',
                'title' => "Revision Submitted (v{$newVersionNumber})",
                'description' => "{$user->name} submitted revision v{$newVersionNumber}. Changes: {$changeSummary}",
                'metadata' => [
                    'version' => $newVersionNumber,
                    'change_summary' => $changeSummary,
                ],
            ]);

            // Audit
            AuditLog::forceCreate([
                'user_id' => $user->id,
                'action' => 'report.resubmitted',
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'new_values' => [
                    'version' => $newVersionNumber,
                    'status' => 'resubmitted',
                    'change_summary' => $changeSummary,
                ],
            ]);

            // Transition to under_review
            $report->update(['status' => ReportStatus::UnderReview]);

            return $report->fresh(['files', 'task', 'employee', 'versions']);
        });
    }

    /**
     * Approve a report.
     */
    public function approve(User $reviewer, Report $report, ?string $comments = null): Report
    {
        $this->assertStatus($report, [ReportStatus::Submitted, ReportStatus::UnderReview, ReportStatus::Resubmitted, ReportStatus::Processing], 'Report is not in a reviewable state.');

        return DB::transaction(function () use ($reviewer, $report, $comments) {
            $report->update([
                'status' => ReportStatus::Approved,
                'approved_at' => Carbon::now(),
            ]);

            // Review record
            $review = ReportReview::create([
                'report_id' => $report->id,
                'reviewer_id' => $reviewer->id,
                'decision' => 'approved',
                'comments' => $comments ?? 'Approved by reviewer.',
                'reviewed_at' => Carbon::now(),
            ]);

            // Downstream: update linked task assignment
            if ($report->task_id && $report->employee_id) {
                TaskAssignment::where('task_id', $report->task_id)
                    ->where('employee_id', $report->employee_id)
                    ->update([
                        'status' => 'completed',
                        'progress_percentage' => 100,
                        'completed_at' => Carbon::now(),
                    ]);
            }

            // Timeline
            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $reviewer->id,
                'event_type' => 'approved',
                'title' => 'Report Approved',
                'description' => "{$reviewer->name} approved report v{$report->version}.",
                'metadata' => [
                    'version' => $report->version ?? 1,
                    'comments' => $comments,
                ],
            ]);

            // Audit
            AuditLog::forceCreate([
                'user_id' => $reviewer->id,
                'action' => 'report.approved',
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'new_values' => [
                    'status' => 'approved',
                    'approved_at' => Carbon::now()->toIso8601String(),
                ],
            ]);

            // Notify Employee
            if ($report->employee && $report->employee->user) {
                Notification::create([
                    'user_id' => $report->employee->user->id,
                    'type' => 'approval',
                    'title' => "Report Approved: {$report->title}",
                    'message' => $comments ?: 'Congratulations! Your report has been approved.',
                ]);
            }

            return $report->fresh(['files', 'task', 'employee', 'reviews']);
        });
    }

    /**
     * Reject a report.
     */
    public function reject(User $reviewer, Report $report, string $reason): Report
    {
        $this->assertStatus($report, [ReportStatus::Submitted, ReportStatus::UnderReview, ReportStatus::Resubmitted, ReportStatus::Processing], 'Report is not in a reviewable state.');

        return DB::transaction(function () use ($reviewer, $report, $reason) {
            $report->update([
                'status' => ReportStatus::Rejected,
                'rejected_at' => Carbon::now(),
            ]);

            $review = ReportReview::create([
                'report_id' => $report->id,
                'reviewer_id' => $reviewer->id,
                'decision' => 'rejected',
                'comments' => $reason,
                'reviewed_at' => Carbon::now(),
            ]);

            // Timeline
            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $reviewer->id,
                'event_type' => 'rejected',
                'title' => 'Report Rejected',
                'description' => "{$reviewer->name} rejected report. Reason: {$reason}",
                'metadata' => [
                    'reason' => $reason,
                ],
            ]);

            // Audit
            AuditLog::forceCreate([
                'user_id' => $reviewer->id,
                'action' => 'report.rejected',
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'new_values' => [
                    'status' => 'rejected',
                    'reason' => $reason,
                ],
            ]);

            // Notify Employee
            if ($report->employee && $report->employee->user) {
                Notification::create([
                    'user_id' => $report->employee->user->id,
                    'type' => 'rejection',
                    'title' => "Report Rejected: {$report->title}",
                    'message' => "Your report has been rejected. Reason: {$reason}",
                ]);
            }

            return $report->fresh(['files', 'task', 'employee', 'reviews']);
        });
    }

    /**
     * Cancel a report.
     */
    public function cancel(User $user, Report $report, ?string $reason = null): Report
    {
        $this->assertStatus($report, [ReportStatus::Draft, ReportStatus::UnderReview, ReportStatus::RevisionRequired], 'Cannot cancel a completed or archived report.');

        return DB::transaction(function () use ($user, $report, $reason) {
            $report->update(['status' => ReportStatus::Cancelled]);

            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $user->id,
                'event_type' => 'cancelled',
                'title' => 'Report Cancelled',
                'description' => "{$user->name} cancelled the report.".($reason ? " Reason: {$reason}" : ''),
                'metadata' => ['reason' => $reason],
            ]);

            return $report->fresh();
        });
    }

    /**
     * Archive a report.
     */
    public function archive(User $user, Report $report): Report
    {
        $this->assertStatus($report, [ReportStatus::Approved, ReportStatus::Rejected, ReportStatus::Cancelled], 'Only terminal reports can be archived.');

        return DB::transaction(function () use ($user, $report) {
            $report->update(['status' => ReportStatus::Archived]);

            ReportTimeline::create([
                'report_id' => $report->id,
                'actor_id' => $user->id,
                'event_type' => 'archived',
                'title' => 'Report Archived',
                'description' => "{$user->name} archived the report.",
            ]);

            return $report->fresh();
        });
    }

    /**
     * Get timeline events for a report.
     */
    public function getTimeline(Report $report): Collection
    {
        return ReportTimeline::where('report_id', $report->id)
            ->with(['actor:id,name,email', 'reportVersion'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Validate report status helper.
     *
     * @param  list<ReportStatus>  $allowedStatuses
     */
    protected function assertStatus(Report $report, array $allowedStatuses, string $message): void
    {
        $current = $report->status instanceof ReportStatus ? $report->status : ReportStatus::tryFrom((string) $report->status);

        if (! in_array($current, $allowedStatuses, true)) {
            throw new UnprocessableEntityHttpException($message.' Current status is: '.($current?->value ?? (string) $report->status));
        }
    }
}
