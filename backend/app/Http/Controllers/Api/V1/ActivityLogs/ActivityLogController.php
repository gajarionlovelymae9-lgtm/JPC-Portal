<?php

namespace App\Http\Controllers\Api\V1\ActivityLogs;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Task;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    private const SUBJECT_CLASSES = [
        'Task' => Task::class,
        'Employee' => Employee::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()
            ->with('user')
            ->latest('created_at')
            ->latest('id');

        $query = $this->scopeForRole($query, $request);
        $query = $this->applyFilters($query, $request);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::collection(
            'Activity logs retrieved successfully',
            ActivityLogResource::collection($query->paginate($perPage)),
        );
    }

    public function show(Request $request, ActivityLog $activityLog): JsonResponse
    {
        $visible = $this->scopeForRole(ActivityLog::query(), $request)
            ->whereKey($activityLog->id)
            ->exists();

        if (! $visible) {
            return ApiResponse::error('Forbidden', status: 403);
        }

        return ApiResponse::success('Activity log retrieved successfully', new ActivityLogResource($activityLog->load('user')));
    }

    /**
     * Activity logs are immutable. The endpoints exist so clients receive an
     * explicit 403 (instead of a 404/405) when attempting to alter a record.
     */
    public function immutable(): JsonResponse
    {
        return ApiResponse::error('Activity logs are immutable and cannot be modified.', status: 403);
    }

    /**
     * Admin and HR see the full timeline; department heads only see activity
     * for tasks in their department or employees of their department.
     */
    private function scopeForRole(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user->hasRole(Role::Admin) || $user->hasRole(Role::Hr)) {
            return $query;
        }

        $departmentId = $user->employee?->department_id;

        if (! $departmentId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $where) use ($departmentId): void {
            $where->where(function (Builder $taskScope) use ($departmentId): void {
                $taskScope->where('subject_type', Task::class)
                    ->whereExists(function ($sub) use ($departmentId): void {
                        $sub->selectRaw('1')
                            ->from('tasks')
                            ->whereColumn('tasks.id', 'activity_logs.subject_id')
                            ->where('tasks.department_id', $departmentId);
                    });
            })->orWhereExists(function ($sub) use ($departmentId): void {
                $sub->selectRaw('1')
                    ->from('employees')
                    ->whereColumn('employees.user_id', 'activity_logs.user_id')
                    ->where('employees.department_id', $departmentId);
            });
        });
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('activity_type', $request->input('action'));
        }

        if ($request->filled('subject')) {
            $subjectType = $this->resolveSubjectType((string) $request->input('subject'));

            if ($subjectType) {
                $query->where('subject_type', $subjectType);
            }

            if ($request->filled('subject_id')) {
                $query->where('subject_id', (int) $request->input('subject_id'));
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function (Builder $q) use ($search, $request): void {
                $q->where('description', 'like', $search)
                    ->orWhereJsonContains('metadata->task_code', $request->input('search'));
            });
        }

        return $query;
    }

    private function resolveSubjectType(string $subject): ?string
    {
        if (isset(self::SUBJECT_CLASSES[$subject])) {
            return self::SUBJECT_CLASSES[$subject];
        }

        return class_exists($subject) ? $subject : null;
    }
}