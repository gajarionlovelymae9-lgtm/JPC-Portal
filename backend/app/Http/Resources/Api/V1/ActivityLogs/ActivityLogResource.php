<?php

namespace App\Http\Resources\Api\V1\ActivityLogs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'id' => $this->id,
            'activity_type' => $this->activity_type,
            'action' => $this->activity_type,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'metadata' => $metadata,
            'task_code' => $metadata['task_code'] ?? null,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}