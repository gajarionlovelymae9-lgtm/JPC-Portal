<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskManagement\TaskReviewResource;
use App\Models\TaskAssignment;
use App\Services\TaskManagement\TaskReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaskReviewController extends Controller
{
    public function __construct(
        protected TaskReviewService $service,
    ) {}

    public function index(TaskAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return ApiResponse::collection('Reviews retrieved successfully', TaskReviewResource::collection($assignment->reviews));
    }
}
