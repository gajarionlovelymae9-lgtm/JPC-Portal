<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Employee\EmployeeTargetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Admin — read-only review of a single target with its breakdowns, milestones
 * and achievement notes, so administrators can review target detail under the
 * existing `targets.view` permission.
 */
class AdminTargetController extends Controller
{
    public function __construct(private readonly EmployeeTargetService $targetService) {}

    public function show(int $target): JsonResponse
    {
        $detail = $this->targetService->adminTargetDetail($target);

        if ($detail === null) {
            return ApiResponse::error('Target not found', null, 404);
        }

        return ApiResponse::success('Target loaded', $detail);
    }
}
