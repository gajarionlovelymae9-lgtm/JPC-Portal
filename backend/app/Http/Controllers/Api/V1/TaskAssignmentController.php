<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskManagement\AssignTaskRequest;
use App\Http\Requests\TaskManagement\ReviewTaskRequest;
use App\Http\Requests\TaskManagement\SubmitTaskRequest;
use App\Http\Resources\Api\V1\TaskManagement\TaskAssignmentResource;
use App\Http\Resources\Api\V1\TaskManagement\TaskProgressResource;
use App\Http\Resources\Api\V1\TaskManagement\TaskReviewResource;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\TaskManagement\TaskAssignmentService;
use App\Services\TaskManagement\TaskReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskAssignmentController extends Controller
{
    public function __construct(
        protected TaskAssignmentService $assignmentService,
        protected TaskReviewService $reviewService,
    ) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        return ApiResponse::collection('Assignments retrieved successfully', TaskAssignmentResource::collection($task->assignments()->paginate(15)));
    }

    public function store(AssignTaskRequest $request, Task $task, TaskAssignmentService $service): JsonResponse
    {
        $this->authorize('assign', $task);
        $this->authorize('create', TaskAssignment::class);

        $employee = Employee::query()->findOrFail($request->validated()['employee_id']);
        $assignment = $service->assignEmployee($task, $employee, $request->user(), $request->validated());

        return ApiResponse::created('Assignment created successfully', new TaskAssignmentResource($assignment));
    }

    public function show(TaskAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return ApiResponse::success('Assignment retrieved successfully', new TaskAssignmentResource($assignment->load(['employee', 'task'])));
    }

    public function accept(TaskAssignment $assignment, TaskAssignmentService $service): JsonResponse
    {
        $this->authorize('accept', $assignment);
        $assignment = $service->acceptAssignment($assignment, $assignment->employee);

        return ApiResponse::success('Assignment accepted successfully', new TaskAssignmentResource($assignment));
    }

    public function start(TaskAssignment $assignment, TaskAssignmentService $service): JsonResponse
    {
        $this->authorize('start', $assignment);
        $assignment = $service->startAssignment($assignment, $assignment->employee);

        return ApiResponse::success('Assignment started successfully', new TaskAssignmentResource($assignment));
    }

    public function submit(SubmitTaskRequest $request, TaskAssignment $assignment, TaskAssignmentService $service): JsonResponse
    {
        $this->authorize('submit', $assignment);
        $assignment = $service->submitAssignment($assignment, $assignment->employee, $request->validated()['remarks'] ?? null);

        return ApiResponse::success('Assignment submitted successfully', new TaskAssignmentResource($assignment));
    }

    public function review(ReviewTaskRequest $request, TaskAssignment $assignment, TaskReviewService $service): JsonResponse
    {
        $this->authorize('review', $assignment);

        $decision = $request->validated()['decision'];
        $remarks = $request->validated()['remarks'];

        $review = match ($decision) {
            'approved' => $service->approve($assignment, $request->user(), $remarks),
            'rejected' => $service->reject($assignment, $request->user(), $remarks),
            'needs_revision' => $service->requestRevision($assignment, $request->user(), $remarks),
        };

        return ApiResponse::success('Review recorded successfully', new TaskReviewResource($review));
    }

    public function resubmit(TaskAssignment $assignment, TaskAssignmentService $service): JsonResponse
    {
        $this->authorize('resubmit', $assignment);
        $assignment = $service->resubmitAssignment($assignment, $assignment->employee);

        return ApiResponse::success('Assignment resubmitted successfully', new TaskAssignmentResource($assignment));
    }

    public function progress(TaskAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return ApiResponse::collection('Progress history retrieved successfully', TaskProgressResource::collection($assignment->progress));
    }

    public function reviews(TaskAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return ApiResponse::collection('Reviews retrieved successfully', TaskReviewResource::collection($assignment->reviews));
    }
}
