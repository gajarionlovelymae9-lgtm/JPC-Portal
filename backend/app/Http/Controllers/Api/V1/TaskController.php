<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskManagement\StoreTaskRequest;
use App\Http\Requests\TaskManagement\UpdateTaskRequest;
use App\Http\Resources\Api\V1\TaskManagement\TaskResource;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\TaskManagement\TaskService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::query()->with(['department', 'assignments']);

        $allowedSorts = ['created_at', 'updated_at', 'due_date', 'priority', 'status', 'title'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'created_at';
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }
        if ($request->filled('employee_id')) {
            $query->whereHas('assignments', fn ($q) => $q->where('employee_id', $request->input('employee_id')));
        }
        if ($request->filled('annual_plan_id')) {
            $query->where('annual_plan_id', $request->input('annual_plan_id'));
        }
        if ($request->filled('department_plan_id')) {
            $query->where('department_plan_id', $request->input('department_plan_id'));
        }
        if ($request->filled('department_target_id')) {
            $query->where('department_target_id', $request->input('department_target_id'));
        }
        if ($request->filled('employee_target_id')) {
            $query->where('employee_target_id', $request->input('employee_target_id'));
        }
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->input('due_date_from'));
        }
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->input('due_date_to'));
        }
        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('task_code', 'like', $search)
                    ->orWhere('title', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $tasks = $query->paginate($perPage);

        return ApiResponse::collection('Tasks retrieved successfully', TaskResource::collection($tasks));
    }

    public function store(StoreTaskRequest $request, TaskService $service): JsonResponse
    {
        $task = $service->createTask($request->validated(), $request->user());

        return ApiResponse::created('Task created successfully', new TaskResource($task));
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return ApiResponse::success('Task retrieved successfully', new TaskResource($task->load(['annualPlan', 'departmentPlan', 'departmentTarget', 'employeeTarget', 'assignments', 'progress', 'reviews'])));
    }

    public function update(UpdateTaskRequest $request, Task $task, TaskService $service): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $service->updateTask($task, $request->validated(), $request->user());

        return ApiResponse::success('Task updated successfully', new TaskResource($task));
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $task->delete();

        return ApiResponse::noContent();
    }

    public function cancel(Request $request, Task $task, TaskService $service): JsonResponse
    {
        $this->authorize('update', $task);
        $task = $service->cancelTask($task, $request->user());

        return ApiResponse::success('Task cancelled successfully', new TaskResource($task));
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', Task::class);

        return ApiResponse::success('Task summary retrieved successfully', [
            'total_tasks' => Task::query()->count(),
            'completed_tasks' => Task::query()->where('status', 'completed')->count(),
            'pending_tasks' => Task::query()->pending()->count(),
            'overdue_tasks' => Task::query()->overdue()->count(),
            'submitted_tasks' => Task::query()->where('status', 'submitted')->count(),
            'under_review_tasks' => Task::query()->where('status', 'under_review')->count(),
            'rejected_tasks' => Task::query()->where('status', 'rejected')->count(),
            'cancelled_tasks' => Task::query()->where('status', 'cancelled')->count(),
        ]);
    }

    public function monthlyProgress(Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', Task::class);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $total = Task::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();
        $completed = Task::query()
            ->where('status', 'completed')
            ->whereYear('completed_at', $year)
            ->whereMonth('completed_at', $month)
            ->count();
        $pending = Task::query()
            ->pending()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();
        $overdue = Task::query()
            ->overdue()
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->count();
        $submitted = Task::query()
            ->where('status', 'submitted')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();
        $underReview = Task::query()
            ->where('status', 'under_review')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        return ApiResponse::success('Monthly progress retrieved successfully', [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'on_time' => $completed,
            'late' => $overdue,
            'submitted' => $submitted,
            'under_review' => $underReview,
        ]);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return ApiResponse::error('No employee profile found.', null, 403);
        }

        $this->authorize('viewAny', Task::class);

        $query = Task::query()
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
            ->with('assignments', 'department');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $tasks = $query->paginate($perPage);

        return ApiResponse::collection('My tasks retrieved successfully', TaskResource::collection($tasks));
    }

    public function myTasksSummary(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return ApiResponse::error('No employee profile found.', null, 403);
        }

        $assignmentIds = $employee->taskAssignments()->pluck('id');

        return ApiResponse::success('My task summary retrieved successfully', [
            'total' => $assignmentIds->count(),
            'completed' => $employee->taskAssignments()->where('status', 'completed')->count(),
            'in_progress' => $employee->taskAssignments()->where('status', 'in_progress')->count(),
            'submitted' => $employee->taskAssignments()->where('status', 'submitted')->count(),
            'under_review' => $employee->taskAssignments()->where('status', 'under_review')->count(),
            'rejected' => $employee->taskAssignments()->where('status', 'rejected')->count(),
            'overdue' => $employee->taskAssignments()->overdue()->count(),
        ]);
    }

    public function myUpcomingTasks(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return ApiResponse::error('No employee profile found.', null, 403);
        }

        $assignments = $employee->taskAssignments()
            ->whereIn('status', ['assigned', 'accepted', 'in_progress'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', now()->startOfDay()->toDateString())
            ->with(['task' => fn ($q) => $q->with('department')])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TaskAssignment $assignment): array => [
                'assignment_id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'task_code' => $assignment->task->task_code,
                'title' => $assignment->task->title,
                'priority' => $assignment->task->priority,
                'due_date' => $assignment->due_date?->toDateString(),
                'progress_percentage' => (float) $assignment->progress_percentage,
                'status' => $assignment->status,
            ]);

        return ApiResponse::success('Upcoming tasks retrieved successfully', $assignments);
    }

    public function employeeTasks(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('viewTasks', $employee);

        $query = Task::query()
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
            ->with('assignments', 'department')
            ->with('progress');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $tasks = $query->paginate($perPage);

        return ApiResponse::collection('Employee tasks retrieved successfully', TaskResource::collection($tasks));
    }
}
