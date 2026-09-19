<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskManagement\UpdateTaskProgressRequest;
use App\Http\Resources\Api\V1\TaskManagement\TaskProgressResource;
use App\Models\TaskAssignment;
use App\Services\TaskManagement\TaskProgressService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaskProgressController extends Controller
{
    public function __construct(
        protected TaskProgressService $service,
    ) {}

    public function store(UpdateTaskProgressRequest $request, TaskAssignment $assignment, TaskProgressService $service): JsonResponse
    {
        $this->authorize('progress', $assignment);

        $employee = $request->user()->employee;
        $progress = $service->updateProgress($assignment, (float) $request->input('percentage'), $request->input('comment'), $employee);

        return ApiResponse::success('Progress updated successfully', new TaskProgressResource($progress));
    }

    public function index(TaskAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return ApiResponse::collection('Progress history retrieved successfully', TaskProgressResource::collection($assignment->progress));
    }
}
