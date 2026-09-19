<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Services\DepartmentHead\DepartmentPerformanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentPerformanceController extends Controller
{
    public function __construct(
        private readonly DepartmentPerformanceService $performanceService
    ) {}

    public function performance(Request $request): JsonResponse
    {
        $filters = $request->only(['department_id']);
        $data = $this->performanceService->getPerformance($request->user(), $filters);

        return ApiResponse::success('Department performance loaded successfully.', $data);
    }

    public function achievements(Request $request): JsonResponse
    {
        $filters = $request->only(['department_id']);
        $data = $this->performanceService->getAchievements($request->user(), $filters);

        return ApiResponse::success('Department achievements loaded successfully.', $data);
    }
}
