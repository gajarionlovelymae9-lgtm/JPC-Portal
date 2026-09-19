<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Services\DepartmentHead\DepartmentEscalationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentEscalationController extends Controller
{
    public function __construct(
        private readonly DepartmentEscalationService $escalationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['department_id', 'status']);
        $escalations = $this->escalationService->listEscalations($request->user(), $filters);

        return ApiResponse::success('Department escalations loaded successfully.', $escalations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|in:overdue_task,repeated_revision,missing_report,target_risk,general',
            'reason' => 'required|string|max:2000',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'department_id' => 'nullable|integer',
        ]);

        $res = $this->escalationService->createEscalation($request->user(), $validated);

        return ApiResponse::success('Escalation created and forwarded to Administration.', $res, 201);
    }
}
