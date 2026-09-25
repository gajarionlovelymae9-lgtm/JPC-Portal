<?php

namespace App\Http\Resources\DataQuality;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataQualityIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scan_id' => $this->scan_id,
            'issue_type' => $this->issue_type->value ?? $this->issue_type,
            'category' => method_exists($this->issue_type, 'category') ? $this->issue_type->category() : 'General',
            'severity' => $this->severity->value ?? $this->severity,
            'status' => $this->status->value ?? $this->status,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'name' => "{$this->employee->first_name} {$this->employee->last_name}",
                'employee_number' => $this->employee->employee_number,
            ] : null,
            'title' => $this->title,
            'description' => $this->description,
            'detected_value' => $this->detected_value,
            'expected_value' => $this->expected_value,
            'detected_by' => $this->detected_by,
            'reviewed_by' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null,
            'resolved_by' => $this->resolver ? [
                'id' => $this->resolver->id,
                'name' => $this->resolver->name,
            ] : null,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_notes' => $this->resolution_notes,
            'histories' => DataQualityIssueHistoryResource::collection($this->whenLoaded('histories')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
