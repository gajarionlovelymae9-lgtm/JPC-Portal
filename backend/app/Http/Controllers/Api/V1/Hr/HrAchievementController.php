<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrAchievementFilterRequest;
use App\Http\Requests\Hr\HrAchievementRejectRequest;
use App\Services\Hr\HrAchievementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAchievementController extends Controller
{
    public function __construct(private readonly HrAchievementService $service) {}

    public function index(HrAchievementFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $paginator = $this->service->paginateRecords($filters);

        return ApiResponse::success('HR achievements loaded successfully.', [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'cards' => $this->service->kpiCards($filters),
        ]);
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success('HR achievement options loaded successfully.', $this->service->options());
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success('HR achievement detail loaded successfully.', $this->service->show($id));
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success('Award verified.', $this->service->verify($id, $request->user()));
    }

    public function reject(HrAchievementRejectRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success('Award rejected.', $this->service->reject($id, $data['reason'], $request->user()));
    }

    public function reports(HrAchievementFilterRequest $request): JsonResponse
    {
        return ApiResponse::success('HR achievement report loaded successfully.', $this->service->reports($request->validated()));
    }

    public function export(HrAchievementFilterRequest $request)
    {
        $filters = $request->validated();
        $format = $filters['format'] ?? 'csv';

        return $this->service->export($filters, $format, $request->user());
    }
}
