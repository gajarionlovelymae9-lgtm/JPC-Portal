<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escalations\AddEscalationNoteRequest;
use App\Http\Requests\Escalations\AssignEscalationRequest;
use App\Http\Requests\Escalations\UpdateEscalationStatusRequest;
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
        $this->authorize('viewAny', DepartmentEscalation::class);

        $data = $this->escalationService->listEscalations($request->only([
            'status',
            'priority',
            'type',
            'department_id',
            'assigned_to',
            'search',
            'sort',
            'direction',
            'per_page',
            'page',
        ]));

        return ApiResponse::success('Escalations loaded successfully.', $data);
    }

    public function summary(): JsonResponse
    {
        $this->authorize('viewAny', DepartmentEscalation::class);

        return ApiResponse::success('Escalation summary loaded successfully.', $this->escalationService->summary());
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', DepartmentEscalation::class);

        return ApiResponse::success('Escalation options loaded successfully.', $this->escalationService->formOptions());
    }

    public function show(DepartmentEscalation $escalation): JsonResponse
    {
        $this->authorize('view', $escalation);

        return ApiResponse::success('Escalation loaded successfully.', $this->escalationService->showEscalation($escalation));
    }

    public function assign(AssignEscalationRequest $request, DepartmentEscalation $escalation): JsonResponse
    {
        $this->authorize('assign', $escalation);

        $data = $this->escalationService->assign($request->user(), $escalation, $request->validated());

        return ApiResponse::success('Escalation assigned successfully.', $data);
    }

    public function updateStatus(UpdateEscalationStatusRequest $request, DepartmentEscalation $escalation): JsonResponse
    {
        $this->authorize('review', $escalation);

        $data = $this->escalationService->updateStatus($request->user(), $escalation, $request->validated());

        return ApiResponse::success('Escalation status updated successfully.', $data);
    }

    public function addNote(AddEscalationNoteRequest $request, DepartmentEscalation $escalation): JsonResponse
    {
        $this->authorize('addNote', $escalation);

        $data = $this->escalationService->addNote($request->user(), $escalation, $request->validated());

        return ApiResponse::success('Escalation note added successfully.', $data);
    }
}
