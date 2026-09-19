<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Services\DepartmentHead\DepartmentTargetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentTargetController extends Controller
{
    public function __construct(
        private readonly DepartmentTargetService $targetService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['department_id']);
        $targets = $this->targetService->listTargets($request->user(), $filters);

        return ApiResponse::success('Department targets loaded successfully.', $targets);
    }
}
