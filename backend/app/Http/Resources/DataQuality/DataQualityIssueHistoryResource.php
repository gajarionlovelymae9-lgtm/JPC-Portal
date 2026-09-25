<?php

namespace App\Http\Resources\DataQuality;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataQualityIssueHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'previous_status' => $this->previous_status,
            'new_status' => $this->new_status,
            'action' => $this->action,
            'performed_by' => $this->performer ? [
                'id' => $this->performer->id,
                'name' => $this->performer->name,
            ] : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
