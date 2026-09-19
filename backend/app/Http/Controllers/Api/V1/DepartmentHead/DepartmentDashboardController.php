<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Services\DepartmentHead\DepartmentDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentDashboardController extends Controller
{
    public function __construct(
        private readonly DepartmentDashboardService $dashboardService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $deptId = $request->query('department_id') ? (int) $request->query('department_id') : null;
        $data = $this->dashboardService->getDashboard($request->user(), $deptId);

        return ApiResponse::success('Department dashboard loaded successfully.', $data);
    }
}
