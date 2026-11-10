<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaskAttachment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    /**
     * Stream a privately-stored task attachment to an authorised user.
     *
     * Files live on the private "local" disk (storage/app/private), so they can
     * never be reached through a public URL. Access is limited to the employee
     * the assignment belongs to, their manager, or HR monitoring the module.
     */
    public function download(Request $request, TaskAttachment $attachment): JsonResponse|StreamedResponse
    {
        $user = $request->user();
        $assignment = $attachment->assignment;

        if ($assignment !== null) {
            $isOwner = $assignment->employee?->user_id !== null
                && (int) $assignment->employee->user_id === (int) $user->id;
            $isManager = in_array($user->roleName(), ['admin', 'department_head', 'hr'], true)
                && $user->hasPermission('tasks.view');

            abort_unless($isOwner || $isManager, 403, 'You are not allowed to download this attachment.');
        } else {
            abort_unless($user->hasPermission('tasks.view'), 403, 'You are not allowed to download this attachment.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($attachment->file_path)) {
            return ApiResponse::error('Attachment file not found', null, 404);
        }

        return $disk->download($attachment->file_path, $attachment->file_name);
    }
}
