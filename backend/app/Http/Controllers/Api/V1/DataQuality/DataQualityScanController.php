<?php

namespace App\Http\Controllers\Api\V1\DataQuality;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataQuality\RunDataQualityScanRequest;
use App\Http\Resources\DataQuality\DataQualityScanResource;
use App\Models\DataQualityScan;
use App\Services\DataQuality\DataQualityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataQualityScanController extends Controller
{
    public function __construct(
        private readonly DataQualityService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DataQualityScan::class);

        // Clamp server-side: an unvalidated `per_page` would otherwise let a
        // caller request the whole table in one page. 100 is the shared
        // pagination ceiling used by every other paginated endpoint.
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $scans = DataQualityScan::query()
            ->with('initiatedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::collection(
            'Data quality scans retrieved successfully',
            DataQualityScanResource::collection($scans)
        );
    }

    public function store(RunDataQualityScanRequest $request): JsonResponse
    {
        $this->authorize('create', DataQualityScan::class);

        $scanType = $request->input('scan_type', 'full');
        $scope = [];
        if ($request->filled('department_id')) {
            $scope['department_id'] = $request->input('department_id');
        }
        if ($request->filled('employee_id')) {
            $scope['employee_id'] = $request->input('employee_id');
        }

        // Trigger synchronous scan or dispatch job
        $scan = $this->service->triggerScan($scanType, $scope, $request->user());

        return ApiResponse::success(
            'Data quality scan executed successfully',
            new DataQualityScanResource($scan->load('initiatedBy')),
            201
        );
    }

    public function show(Request $request, DataQualityScan $scan): JsonResponse
    {
        $this->authorize('view', $scan);

        $scan->load(['initiatedBy', 'issues' => function ($q) {
            $q->limit(50);
        }]);

        return ApiResponse::success(
            'Data quality scan details retrieved successfully',
            new DataQualityScanResource($scan)
        );
    }
}
