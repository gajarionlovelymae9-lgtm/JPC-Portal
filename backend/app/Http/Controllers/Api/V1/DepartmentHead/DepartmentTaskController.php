<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskManagement\StoreDepartmentTaskRequest;
use App\Models\Task;
use App\Services\DepartmentHead\DepartmentTaskService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentTaskController extends Controller
{
    public function __construct(
        private readonly DepartmentTaskService $taskService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['department_id', 'status', 'priority', 'search']);
        $tasks = $this->taskService->listTasks($request->user(), $filters);

        return ApiResponse::success('Department tasks loaded successfully.', $tasks);
    }

    public function store(StoreDepartmentTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->createTask($request->user(), $request->validated());

        return ApiResponse::success('Task created and assigned successfully.', $task, 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $data = $this->taskService->showTask($request->user(), $task);

        return ApiResponse::success('Task details loaded successfully.', $data);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'deadline' => 'nullable|date',
            'requirements' => 'nullable|string',
            'instructions' => 'nullable|string',
        ]);

        $res = $this->taskService->updateTask($request->user(), $task, $validated);

        return ApiResponse::success('Task updated successfully.', $res);
    }

    public function assign(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ]);

        $res = $this->taskService->assignEmployees($request->user(), $task, $validated['employee_ids']);

        return ApiResponse::success('Employees assigned successfully.', $res);
    }

    public function cancel(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $res = $this->taskService->cancelTask($request->user(), $task, $validated['reason']);

        return ApiResponse::success('Task cancelled successfully.', $res);
    }
}
