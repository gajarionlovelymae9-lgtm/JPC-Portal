<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escalations\StoreEscalationRequest;
use App\Models\DepartmentEscalation;
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
        $this->authorize('viewAny', DepartmentEscalation::class);

        $data = $this->escalationService->listEscalations($request->user(), $this->filters($request));

        return ApiResponse::success('Department escalations loaded successfully.', $data);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DepartmentEscalation::class);

        $data = $this->escalationService->summary($request->user(), $request->only(['department_id']));

        return ApiResponse::success('Escalation summary loaded successfully.', $data);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('create', DepartmentEscalation::class);

        $data = $this->escalationService->formOptions($request->user(), $request->only(['department_id']));

        return ApiResponse::success('Escalation form options loaded successfully.', $data);
    }

    public function store(StoreEscalationRequest $request): JsonResponse
    {
        $this->authorize('create', DepartmentEscalation::class);

        $escalation = $this->escalationService->createEscalation($request->user(), $request->validated());

        return ApiResponse::created('Escalation created and forwarded to Administration.', $escalation);
    }

    public function show(Request $request, DepartmentEscalation $escalation): JsonResponse
    {
        $this->authorize('view', $escalation);

        return ApiResponse::success('Escalation loaded successfully.', $this->escalationService->showEscalation($escalation));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->only([
            'department_id',
            'status',
            'priority',
            'type',
            'search',
            'sort',
            'direction',
            'per_page',
            'page',
        ]);
    }
}
