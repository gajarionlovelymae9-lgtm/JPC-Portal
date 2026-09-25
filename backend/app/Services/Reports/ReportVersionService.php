<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportVersionService
{
    /**
     * Create an immutable snapshot of a report version.
     */
    public function createSnapshot(
        Report $report,
        User $user,
        ?string $notes = null,
        ?string $changeSummary = null,
        ?string $filePath = null,
        ?int $versionNumber = null
    ): ReportVersion {
        $versionNum = $versionNumber ?? $report->version ?? 1;

        return DB::transaction(function () use ($report, $user, $notes, $changeSummary, $filePath, $versionNum) {
            $existing = ReportVersion::where('report_id', $report->id)
                ->where('version_number', $versionNum)
                ->first();

            $metadata = [
                'task_id' => $report->task_id,
                'is_late' => (bool) ($report->is_late ?? false),
                'ai_status' => $report->ai_status ?? 'pending',
                'files_count' => $report->files()->count(),
            ];

            if ($existing) {
                // If it's still draft/in-progress snapshot, update it, else if submitted it's immutable
                $existing->update([
                    'title' => $report->title,
                    'content' => $report->content,
                    'references' => $report->references,
                    'status' => $report->status instanceof \App\Enums\ReportStatus ? $report->status->value : (string) $report->status,
                    'file_path' => $filePath ?? $existing->file_path,
                    'notes' => $notes ?? $existing->notes,
                    'change_summary' => $changeSummary ?? $existing->change_summary,
                    'metadata' => $metadata,
                    'submitted_at' => now(),
                ]);

                return $existing;
            }

            return ReportVersion::create([
                'report_id' => $report->id,
                'version_number' => $versionNum,
                'title' => $report->title,
                'content' => $report->content,
                'references' => $report->references,
                'status' => $report->status instanceof \App\Enums\ReportStatus ? $report->status->value : (string) $report->status,
                'submitted_by' => $user->id,
                'file_path' => $filePath,
                'notes' => $notes,
                'change_summary' => $changeSummary,
                'metadata' => $metadata,
                'submitted_at' => now(),
            ]);
        });
    }

    /**
     * Get all versions for a report.
     */
    public function getVersions(Report $report): Collection
    {
        return ReportVersion::where('report_id', $report->id)
            ->with(['submitter:id,name,email', 'files', 'comments.reviewer:id,name'])
            ->orderBy('version_number', 'desc')
            ->get();
    }

    /**
     * Get a specific version by version number.
     */
    public function getVersion(Report $report, int $versionNumber): ReportVersion
    {
        $version = ReportVersion::where('report_id', $report->id)
            ->where('version_number', $versionNumber)
            ->with(['submitter:id,name,email', 'files', 'comments.reviewer:id,name', 'timeline.actor:id,name'])
            ->first();

        if (!$version) {
            throw new NotFoundHttpException("Report version v{$versionNumber} not found.");
        }

        return $version;
    }

    /**
     * Compare two versions of a report.
     */
    public function compareVersions(Report $report, int $fromVersion, int $toVersion): array
    {
        $vFrom = $this->getVersion($report, $fromVersion);
        $vTo = $this->getVersion($report, $toVersion);

        $fromContent = $vFrom->content ?? '';
        $toContent = $vTo->content ?? '';

        $fromLines = explode("\n", $fromContent);
        $toLines = explode("\n", $toContent);

        $diff = [
            'report_id' => $report->id,
            'from_version' => [
                'version_number' => $vFrom->version_number,
                'title' => $vFrom->title,
                'status' => $vFrom->status,
                'submitted_at' => $vFrom->submitted_at?->toIso8601String(),
                'submitted_by' => $vFrom->submitter?->name,
                'notes' => $vFrom->notes,
                'content_length' => strlen($fromContent),
                'line_count' => count($fromLines),
            ],
            'to_version' => [
                'version_number' => $vTo->version_number,
                'title' => $vTo->title,
                'status' => $vTo->status,
                'submitted_at' => $vTo->submitted_at?->toIso8601String(),
                'submitted_by' => $vTo->submitter?->name,
                'change_summary' => $vTo->change_summary,
                'notes' => $vTo->notes,
                'content_length' => strlen($toContent),
                'line_count' => count($toLines),
            ],
            'changes' => [
                'title_changed' => $vFrom->title !== $vTo->title,
                'content_changed' => $fromContent !== $toContent,
                'references_changed' => ($vFrom->references ?? '') !== ($vTo->references ?? ''),
                'length_difference' => strlen($toContent) - strlen($fromContent),
                'line_difference' => count($toLines) - count($fromLines),
            ],
            'side_by_side' => [
                'from_content' => $fromContent,
                'to_content' => $toContent,
                'from_references' => $vFrom->references,
                'to_references' => $vTo->references,
            ],
        ];

        return $diff;
    }
}
