<?php

namespace App\Services\TaskManagement;

use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\TaskSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Persists versioned task submissions and their uploaded files.
 *
 * Every submit/resubmit creates a new row, so previous files, links, written
 * responses, notes and review decisions remain intact for auditing.
 */
class TaskSubmissionService
{
    private const DISK = 'local';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function create(TaskAssignment $assignment, int $submittedByUserId, array $data, array $files = []): TaskSubmission
    {
        /** @var list<string> $storedPaths */
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($assignment, $submittedByUserId, $data, $files, &$storedPaths): TaskSubmission {
                $version = ((int) TaskSubmission::query()
                    ->where('task_assignment_id', $assignment->id)
                    ->lockForUpdate()
                    ->max('version')) + 1;

                $submission = TaskSubmission::query()->create([
                    'task_id' => $assignment->task_id,
                    'task_assignment_id' => $assignment->id,
                    'submitted_by' => $submittedByUserId,
                    'method' => $data['method'],
                    'url' => $data['url'] ?? null,
                    'written_response' => $data['written_response'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'version' => $version,
                    'submitted_at' => now(),
                ]);

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $path = $file->store('task-submissions/'.$assignment->id, self::DISK);
                    $storedPaths[] = $path;

                    TaskAttachment::query()->create([
                        'task_id' => $assignment->task_id,
                        'task_assignment_id' => $assignment->id,
                        'task_submission_id' => $submission->id,
                        'uploaded_by' => $submittedByUserId,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }

                return $submission->load(['attachments', 'submittedBy']);
            });
        } catch (\Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk(self::DISK)->delete($storedPaths);
            }

            throw $exception;
        }
    }
}
