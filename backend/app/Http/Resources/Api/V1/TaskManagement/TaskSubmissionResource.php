<?php

namespace App\Http\Resources\Api\V1\TaskManagement;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskSubmissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task_assignment_id' => $this->task_assignment_id,
            'submitted_by' => $this->submitted_by,
            'submitted_by_name' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy?->name),
            'method' => $this->method,
            'url' => $this->url,
            'written_response' => $this->written_response,
            'notes' => $this->notes,
            'version' => $this->version,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
