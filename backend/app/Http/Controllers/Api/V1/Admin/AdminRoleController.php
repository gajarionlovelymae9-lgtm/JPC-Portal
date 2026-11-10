<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Models\Role;
use App\Services\Admin\AdminRoleManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Roles & Access (People Management tab).
 *
 *   GET /api/v1/admin/roles                    → nullable catalogue
 *   PUT /api/v1/admin/roles/{role}/permissions  → admin-only; blocks the
 *      Administrator role and the elevated permission deny-list.
 */
class AdminRoleController extends Controller
{
    public function __construct(
        private readonly AdminRoleManagementService $service
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success('Roles and permissions loaded successfully.', $this->service->catalogue());
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $catalogue = $this->service->syncPermissions(
            $role,
            $request->validated('permissions'),
            $request->user(),
        );

        return ApiResponse::success('Role permissions updated successfully.', $catalogue);
    }
}
