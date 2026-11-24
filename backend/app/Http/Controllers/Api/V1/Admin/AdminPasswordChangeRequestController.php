<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\PasswordChangeRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin — password change request review queue.
 *
 * Thin controller over PasswordChangeRequestService. Reads require
 * `users.view`, decisions require `users.update` (enforced in routes/api.php on
 * top of the `role:admin` group guard). Approving applies the stored password
 * hash; rejecting leaves the account untouched.
 */
class AdminPasswordChangeRequestController extends Controller
{
    public function __construct(private readonly PasswordChangeRequestService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:pending,approved,rejected'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success(
            'Password change requests loaded',
            [
                'items' => $this->service->paginate($filters),
                'summary' => $this->service->summary(),
            ]
        );
    }

    public function show(int $passwordRequest): JsonResponse
    {
        $detail = $this->service->detail($passwordRequest);

        if ($detail === null) {
            return ApiResponse::error('Password change request not found', null, 404);
        }

        return ApiResponse::success('Password change request loaded', $detail);
    }

    public function approve(Request $request, int $passwordRequest): JsonResponse
    {
        $validated = $request->validate([
            'decision_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::success(
            'Password change request approved',
            $this->service->approve(
                $request->user(),
                $passwordRequest,
                $validated['decision_note'] ?? null,
            )
        );
    }

    public function reject(Request $request, int $passwordRequest): JsonResponse
    {
        $validated = $request->validate([
            'decision_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::success(
            'Password change request rejected',
            $this->service->reject(
                $request->user(),
                $passwordRequest,
                $validated['decision_note'] ?? null,
            )
        );
    }
}
