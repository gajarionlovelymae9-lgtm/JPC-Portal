<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PeopleManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * People Management header summary.
 *
 *   GET /api/v1/admin/people/summary
 */
class AdminPeopleManagementController extends Controller
{
    public function __construct(
        private readonly PeopleManagementService $service
    ) {}

    public function summary(): JsonResponse
    {
        return ApiResponse::success('People Management summary loaded successfully.', $this->service->summary());
    }
}
