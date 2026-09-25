<?php

namespace App\Http\Resources\DataQuality;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataQualityScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scan_type' => $this->scan_type,
            'scope' => $this->scope,
            'status' => $this->status->value ?? $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'duration_seconds' => ($this->started_at && $this->completed_at) ? $this->started_at->diffInSeconds($this->completed_at) : null,
            'records_checked' => $this->records_checked,
            'issues_found' => $this->issues_found,
            'critical_count' => $this->critical_count,
            'high_count' => $this->high_count,
            'medium_count' => $this->medium_count,
            'low_count' => $this->low_count,
            'initiated_by' => $this->initiatedBy ? [
                'id' => $this->initiatedBy->id,
                'name' => $this->initiatedBy->name,
            ] : null,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
