<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportReview;
use App\Models\ReportReviewComment;
use App\Models\ReportTimeline;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportReviewService
{
    /**
     * Add an inline / section comment to a report.
     */
    public function addComment(User $reviewer, Report $report, array $data): ReportReviewComment
    {
        $versionNumber = $data['version_number'] ?? $report->version ?? 1;
        $reportVersion = ReportVersion::where('report_id', $report->id)
            ->where('version_number', $versionNumber)
            ->first();

        return DB::transaction(function () use ($reviewer, $report, $reportVersion, $data) {
            $comment = ReportReviewComment::create([
                'report_id' => $report->id,
                'report_version_id' => $reportVersion?->id,
                'reviewer_id' => $reviewer->id,
                'section_key' => $data['section_key'] ?? 'general',
                'comment' => $data['comment'],
                'status' => 'open',
            ]);

            ReportTimeline::create([
                'report_id' => $report->id,
                'report_version_id' => $reportVersion?->id,
                'actor_id' => $reviewer->id,
                'event_type' => 'comment_added',
                'title' => 'Reviewer Comment Added',
                'description' => "{$reviewer->name} added feedback on section: ".($data['section_key'] ?? 'general'),
                'metadata' => [
                    'comment_id' => $comment->id,
                    'section_key' => $data['section_key'] ?? 'general',
                ],
            ]);

            return $comment->load(['reviewer:id,name,email']);
        });
    }

    /**
     * Resolve an existing review comment.
     */
    public function resolveComment(User $user, Report $report, ReportReviewComment $comment): ReportReviewComment
    {
        if ($comment->report_id !== $report->id) {
            throw new NotFoundHttpException('Comment does not belong to this report.');
        }

        return DB::transaction(function () use ($user, $report, $comment) {
            $comment->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $user->id,
            ]);

            ReportTimeline::create([
                'report_id' => $report->id,
                'report_version_id' => $comment->report_version_id,
                'actor_id' => $user->id,
                'event_type' => 'comment_resolved',
                'title' => 'Review Comment Resolved',
                'description' => "{$user->name} marked a comment as resolved.",
                'metadata' => [
                    'comment_id' => $comment->id,
                    'section_key' => $comment->section_key,
                ],
            ]);

            return $comment->load(['reviewer:id,name,email', 'resolver:id,name,email']);
        });
    }

    /**
     * Get all comments for a report.
     */
    public function getComments(Report $report, ?int $versionNumber = null): Collection
    {
        $query = ReportReviewComment::where('report_id', $report->id)
            ->with(['reviewer:id,name,email', 'resolver:id,name,email'])
            ->orderBy('created_at', 'asc');

        if ($versionNumber !== null) {
            $version = ReportVersion::where('report_id', $report->id)
                ->where('version_number', $versionNumber)
                ->first();
            if ($version) {
                $query->where('report_version_id', $version->id);
            }
        }

        return $query->get();
    }

    /**
     * Get review history for a report.
     */
    public function getReviewHistory(Report $report): Collection
    {
        return ReportReview::where('report_id', $report->id)
            ->with(['reviewer:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
