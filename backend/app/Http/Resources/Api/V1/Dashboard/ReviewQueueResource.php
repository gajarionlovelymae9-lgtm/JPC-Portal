<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewQueueResource extends JsonResource
{
    public function toArray($request): array
    {
        $latestReview = $this->reviews->first();

        return [
            'assignment_id' => $this->id,
            'task_id' => $this->task_id,
            'task_code' => $this->task?->task_code,
            'task_title' => $this->task?->title,
            'department_id' => $this->task?->department?->id,
            'department_name' => $this->task?->department?->name,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->fullName,
            'status' => $this->status,
            'submitted_at' => $this->updated_at?->toIso8601String(),
            'remarks' => $this->remarks,
            'latest_review' => $latestReview !== null ? [
                'decision' => $latestReview->decision,
                'remarks' => $latestReview->remarks,
                'reviewed_by' => $latestReview->reviewedBy?->name,
                'reviewed_at' => $latestReview->reviewed_at?->toIso8601String(),
            ] : null,
        ];
    }
}