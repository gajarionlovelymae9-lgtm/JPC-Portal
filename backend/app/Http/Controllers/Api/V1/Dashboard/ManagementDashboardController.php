<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Dashboard\DepartmentProgressResource;
use App\Http\Resources\Api\V1\Dashboard\EmployeeProgressResource;
use App\Http\Resources\Api\V1\Dashboard\OverdueTaskResource;
use App\Http\Resources\Api\V1\Dashboard\ReviewQueueResource;
use App\Http\Resources\Api\V1\TaskManagement\TaskResource;
use App\Models\Employee;
use App\Models\Task;
use App\Services\Dashboard\ManagementDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementDashboardController extends Controller
{
    public function __construct(
        protected ManagementDashboardService $service,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        return ApiResponse::success(
            'Dashboard options retrieved successfully',
            $this->service->options($request->user(), $departmentId),
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        return ApiResponse::success(
            'Dashboard summary retrieved successfully',
            $this->service->summary($request->user(), $request->query(), $departmentId),
        );
    }

    public function employees(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        $employees = $this->service->employeeProgress($request->user(), $request->query(), $departmentId);

        return ApiResponse::collection('Employee progress retrieved successfully', EmployeeProgressResource::collection($employees));
    }

    public function departments(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        $departments = $this->service->departmentProgress($request->user(), $request->query(), $departmentId);

        return ApiResponse::collection('Department progress retrieved successfully', DepartmentProgressResource::collection($departments));
    }

    public function tasks(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        $tasks = $this->service->tasks($request->user(), $request->query(), $departmentId);

        return ApiResponse::collection('Tasks retrieved successfully', TaskResource::collection($tasks));
    }

    public function overdue(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        $overdue = $this->service->overdueTasks($request->user(), $request->query(), $departmentId);

        return ApiResponse::collection('Overdue tasks retrieved successfully', OverdueTaskResource::collection($overdue));
    }

    public function reviewQueue(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        $queue = $this->service->reviewQueue($request->user(), $request->query(), $departmentId);

        return ApiResponse::collection('Review queue retrieved successfully', ReviewQueueResource::collection($queue));
    }

    public function monthlyProgress(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        return ApiResponse::success(
            'Monthly progress retrieved successfully',
            $this->service->monthlyProgress($request->user(), $request->query(), $departmentId),
        );
    }

    public function monthlyAchievement(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        return ApiResponse::success(
            'Monthly achievement retrieved successfully',
            $this->service->monthlyAchievement($request->user(), $request->query(), $departmentId),
        );
    }

    public function targetVsActual(Request $request): JsonResponse
    {
        $departmentId = $this->authorizeAndScope($request);

        return ApiResponse::success(
            'Target vs actual retrieved successfully',
            $this->service->targetVsActual($request->user(), $request->query(), $departmentId),
        );
    }

    public function employeeProgress(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('viewAnalytics', Task::class);
        $this->service->resolveEmployeeScope($request->user(), $employee);

        return ApiResponse::success(
            'Employee progress detail retrieved successfully',
            $this->service->employeeDetailProgress($request->user(), $employee, $request->query()),
        );
    }

    private function authorizeAndScope(Request $request): ?int
    {
        $this->authorize('viewAnalytics', Task::class);

        return $this->service->resolveDepartmentScope($request->user(), $request->integer('department_id') ?: null);
    }
}