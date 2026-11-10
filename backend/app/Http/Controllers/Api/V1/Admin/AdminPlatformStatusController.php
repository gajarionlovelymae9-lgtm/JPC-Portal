<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPlatformStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/admin/platform-status
 *
 * Reports the live health of every platform component. Nothing here is
 * hardcoded: the service reads the real system-health checks at request time.
 */
class AdminPlatformStatusController extends Controller
{
    public function __construct(
        private readonly AdminPlatformStatusService $statusService
    ) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponse::success('Platform status loaded successfully.', $this->statusService->status());
    }
}
