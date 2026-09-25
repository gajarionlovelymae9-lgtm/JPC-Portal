<?php

namespace App\Http\Resources\DataQuality;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataQualityDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'score' => $this->resource['score'] ?? 100.0,
            'total_issues' => $this->resource['total_issues'] ?? 0,
            'open_issues' => $this->resource['open_issues'] ?? 0,
            'critical' => $this->resource['critical'] ?? 0,
            'high' => $this->resource['high'] ?? 0,
            'medium' => $this->resource['medium'] ?? 0,
            'low' => $this->resource['low'] ?? 0,
            'resolved' => $this->resource['resolved'] ?? 0,
            'dismissed' => $this->resource['dismissed'] ?? 0,
            'by_category' => $this->resource['by_category'] ?? [],
            'by_department' => $this->resource['by_department'] ?? [],
            'recent_scans' => DataQualityScanResource::collection($this->resource['recent_scans'] ?? []),
            'trends' => $this->resource['trends'] ?? [],
        ];
    }
}
