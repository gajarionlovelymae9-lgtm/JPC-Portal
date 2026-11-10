<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceLog;
use App\Services\System\MaintenanceLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-only maintenance log API (Phase 13).
 *
 * GET  /admin/maintenance/logs           paginated maintenance log (filterable)
 * POST /admin/maintenance/logs           record a maintenance action (audited)
 */
class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceLogService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success('Maintenance logs loaded successfully.', $this->service->list($request));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'maintenance_type' => 'required|string|max:60',
            'description' => 'required|string|max:4000',
            'affected_module' => 'nullable|string|max:120',
            'result' => 'nullable|in:completed,failed,pending,rolled_back',
            'rollback_required' => 'nullable|boolean',
            'notes' => 'nullable|string|max:4000',
            'performed_at' => 'nullable|date',
            'version' => 'nullable|string|max:20',
        ]);

        $log = $this->service->record($request->user()?->id, $validated);

        return ApiResponse::success('Maintenance action recorded successfully.', $log, 201);
    }

    public function show(MaintenanceLog $maintenanceLog): JsonResponse
    {
        return ApiResponse::success('Maintenance log loaded successfully.', $maintenanceLog->load('performer:id,name,email'));
    }
}
