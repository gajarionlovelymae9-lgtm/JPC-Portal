<?php

namespace App\Http\Controllers\Api\V1\DataQuality;

use App\Http\Controllers\Controller;
use App\Http\Resources\DataQuality\DataQualityDashboardResource;
use App\Models\DataQualityIssue;
use App\Services\DataQuality\DataQualityService;
use App\Services\DataQuality\DataQualityStatisticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataQualityController extends Controller
{
    public function __construct(
        private readonly DataQualityService $service,
        private readonly DataQualityStatisticsService $statisticsService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DataQualityIssue::class);

        $data = $this->service->getDashboard($request->user());

        return ApiResponse::success(
            'Data quality dashboard metrics retrieved successfully',
            new DataQualityDashboardResource($data)
        );
    }

    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DataQualityIssue::class);

        $data = $this->service->getDashboard($request->user());

        return ApiResponse::success(
            'Data quality statistics retrieved successfully',
            $data
        );
    }

    public function trends(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DataQualityIssue::class);

        $months = (int)$request->input('months', 6);
        $trends = $this->statisticsService->getMonthlyTrends($request->user(), $months);

        return ApiResponse::success(
            'Data quality trends retrieved successfully',
            ['trends' => $trends]
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', DataQualityIssue::class);

        $issues = $this->service->getIssues($request->user(), $request->all(), 1000);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="data-quality-issues-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($issues) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Issue Type', 'Severity', 'Status', 'Entity Type', 'Entity ID', 'Department', 'Employee', 'Title', 'Detected Date']);

            foreach ($issues as $issue) {
                fputcsv($handle, [
                    $issue->id,
                    $issue->issue_type->value ?? $issue->issue_type,
                    $issue->severity->value ?? $issue->severity,
                    $issue->status->value ?? $issue->status,
                    $issue->entity_type,
                    $issue->entity_id,
                    $issue->department?->name ?? 'N/A',
                    $issue->employee ? "{$issue->employee->first_name} {$issue->employee->last_name}" : 'N/A',
                    $issue->title,
                    $issue->created_at?->toDateString(),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
