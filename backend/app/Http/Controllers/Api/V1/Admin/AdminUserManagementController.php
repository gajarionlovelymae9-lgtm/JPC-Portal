<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Services\Admin\AdminUserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin user management (People Management → User Accounts).
 *
 *   GET    /api/v1/admin/users
 *   POST   /api/v1/admin/users
 *   GET    /api/v1/admin/users/{id}
 *   PUT    /api/v1/admin/users/{id}
 *   DELETE /api/v1/admin/users/{id}          (deactivates; never hard-deletes)
 *   POST   /api/v1/admin/users/{id}/reactivate
 *   POST   /api/v1/admin/users/{id}/suspend           (new)
 *   POST   /api/v1/admin/users/{id}/reset-password    (new)
 *
 * Validation lives in Form Requests whose `authorize()` reads the caller's
 * real permission; service mutations are transactional and audited.
 */
class AdminUserManagementController extends Controller
{
    public function __construct(
        private readonly AdminUserManagementService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'role' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->service->paginate($filters);

        return ApiResponse::success('Users loaded successfully.', [
            'summary' => $this->service->summary(),
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'options' => $this->service->options(),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated(), $request->user());

        return ApiResponse::created('User created successfully.', $user);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success('User loaded successfully.', $this->service->find($id));
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->service->update($id, $request->validated(), $request->user());

        return ApiResponse::success('User updated successfully.', $user);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->service->deactivate($id, $request->user());

        return ApiResponse::success('User deactivated successfully.', $user);
    }

    public function reactivate(Request $request, int $id): JsonResponse
    {
        $user = $this->service->reactivate($id, $request->user());

        return ApiResponse::success('User reactivated successfully.', $user);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $user = $this->service->suspend($id, $request->user());

        return ApiResponse::success('User suspended successfully.', $user);
    }

    public function resetPassword(ResetUserPasswordRequest $request, int $id): JsonResponse
    {
        // The response carries no password — hashed is never echoed back.
        $user = $this->service->resetPassword($id, (string) $request->validated('password'), $request->user());

        return ApiResponse::success('Password reset successfully.', $user);
    }
}
