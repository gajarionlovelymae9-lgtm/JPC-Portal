<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrTargetRequest;
use App\Services\Hr\HrTargetService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR target management endpoints.
 *
 * Authorization is doubled up like every other HR management surface: the
 * route group requires the HR portal permission and each route adds the
 * specific `targets.*` permission on top, so holding the portal permission
 * alone is not enough to write.
 *
 * Reads reuse AdminKpiTargetService so HR and Admin derive progress, status
 * and achievement from ONE implementation rather than two that can drift.
 */
class HrTargetController extends Controller
{
    public function __construct(private readonly HrTargetService $service) {}

    /**
     * GET /api/v1/hr/targets — summary cards + paginated register + options.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return ApiResponse::success('HR targets loaded successfully.', $this->service->dashboard($filters));
    }

    /**
     * POST /api/v1/hr/targets
     */
    public function store(HrTargetRequest $request): JsonResponse
    {
        return ApiResponse::created(
            'Target created successfully.',
            $this->service->create($request->validated(), $request->user()),
        );
    }

    /**
     * GET /api/v1/hr/targets/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            return ApiResponse::success('Target loaded successfully.', $this->service->show($id));
        } catch (DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), null, JsonResponse::HTTP_NOT_FOUND);
        }
    }

    /**
     * PUT|PATCH /api/v1/hr/targets/{id}
     */
    public function update(HrTargetRequest $request, int $id): JsonResponse
    {
        try {
            return ApiResponse::success(
                'Target updated successfully.',
                $this->service->update($id, $request->validated(), $request->user()),
            );
        } catch (DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), null, JsonResponse::HTTP_NOT_FOUND);
        }
    }

    /**
     * DELETE /api/v1/hr/targets/{id}
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        try {
            $this->service->delete($id, $request->user());
        } catch (DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), null, JsonResponse::HTTP_NOT_FOUND);
        }

        return ApiResponse::success('Target deleted successfully.', null);
    }
}
