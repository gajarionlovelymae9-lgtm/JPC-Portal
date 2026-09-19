<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentEscalation;
use App\Services\Admin\AdminEscalationManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEscalationController extends Controller
{
    public function __construct(
        private readonly AdminEscalationManagementService $escalationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->escalationService->listEscalations($request->all());
        return ApiResponse::success('Escalations loaded successfully.', $data);
    }

    public function resolve(Request $request, DepartmentEscalation $escalation): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:under_review,resolved,closed',
            'resolution_notes' => 'required|string|max:2000',
        ]);

        $res = $this->escalationService->resolveEscalation($request->user(), $escalation, $validated);
        return ApiResponse::success('Escalation status updated successfully.', $res);
    }
}