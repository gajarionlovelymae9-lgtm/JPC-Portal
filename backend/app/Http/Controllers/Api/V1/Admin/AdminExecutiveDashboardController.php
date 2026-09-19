<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminExecutiveDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminExecutiveDashboardController extends Controller
{
    public function __construct(
        private readonly AdminExecutiveDashboardService $dashboardService
    ) {}

    public function dashboard(): JsonResponse
    {
        $data = $this->dashboardService->getDashboard();
        return ApiResponse::success('Executive dashboard loaded successfully.', $data);
    }
}