<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Employee\EmployeeTargetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin — target adjustment review queue.
 *
 * Thin controller over EmployeeTargetService. Reads require `targets.view`,
 * decisions require `targets.update` (enforced in routes/api.php on top of the
 * `role:admin` group guard).
 */
class AdminTargetAdjustmentController extends Controller
{
    public function __construct(private readonly EmployeeTargetService $targetService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:pending,approved,rejected'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success(
            'Adjustment requests loaded',
            [
                'items' => $this->targetService->paginateAdjustments($filters),
                'summary' => $this->targetService->adjustmentSummary(),
            ]
        );
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::success('Adjustment summary loaded', $this->targetService->adjustmentSummary());
    }

    public function show(int $adjustment): JsonResponse
    {
        $detail = $this->targetService->adjustmentDetail($adjustment);

        if ($detail === null) {
            return ApiResponse::error('Adjustment request not found', null, 404);
        }

        return ApiResponse::success('Adjustment request loaded', $detail);
    }

    public function approve(Request $request, int $adjustment): JsonResponse
    {
        $validated = $request->validate([
            'decision_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::success(
            'Adjustment request approved',
            $this->targetService->approveAdjustment(
                $request->user(),
                $adjustment,
                $validated['decision_note'] ?? null,
            )
        );
    }

    public function reject(Request $request, int $adjustment): JsonResponse
    {
        $validated = $request->validate([
            'decision_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::success(
            'Adjustment request rejected',
            $this->targetService->rejectAdjustment(
                $request->user(),
                $adjustment,
                $validated['decision_note'] ?? null,
            )
        );
    }
}
