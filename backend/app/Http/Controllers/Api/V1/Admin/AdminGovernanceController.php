<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\Admin\AdminGovernanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Governance: audit trail and the notification centre.
 *
 *   GET /api/v1/admin/audit-trail
 *   GET /api/v1/admin/notifications
 *   GET /api/v1/admin/governance-summary
 *   GET /api/v1/admin/governance-options
 */
class AdminGovernanceController extends Controller
{
    public function __construct(
        private readonly AdminGovernanceService $service
    ) {}

    public function auditTrail(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'entity_type' => ['nullable', 'string', 'max:191'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'in:audit_log,activity_log'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->service->auditTrail($filters);

        return ApiResponse::success('Audit trail loaded successfully.', [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', 'string', 'max:20'],
            'read' => ['nullable', 'string', 'in:read,unread'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'recipient' => ['nullable', 'string', 'max:120'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->service->notifications($filters);

        return ApiResponse::success('Notifications loaded successfully.', [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/v1/admin/notifications/departments — options for department filter. */
    public function departments(): JsonResponse
    {
        return ApiResponse::success('Departments loaded successfully.', $this->service->options()['departments'] ?? []);
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::success('Governance summary loaded successfully.', $this->service->summary());
    }

    /** GET /api/v1/admin/governance-options — unchanged contract. */
    public function options(): JsonResponse
    {
        return ApiResponse::success('Governance options loaded successfully.', $this->service->options());
    }

    /* ------------------------------------------------------------------ */
    /* Notification centre detail actions (Phases 3-5) */
    /* ------------------------------------------------------------------ */

    /** GET /api/v1/admin/notifications-centre/{notification} — View Details modal. */
    public function show(Notification $notification): JsonResponse
    {
        return ApiResponse::success('Notification details loaded successfully.', $this->service->details($notification));
    }

    /** GET /api/v1/admin/notifications-centre/{notification}/recipients — View Recipients drawer. */
    public function recipients(Notification $notification): JsonResponse
    {
        return ApiResponse::success('Notification recipients loaded successfully.', $this->service->recipients($notification));
    }

    /** POST /api/v1/admin/notifications-centre/{notification}/read — Mark as Read. */
    public function markRead(Notification $notification): JsonResponse
    {
        return ApiResponse::success('Notification marked as read.', $this->service->markRead($notification));
    }
}
