<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\EmployeePortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Employee Portal (Phase 3.3-V) — self-service surface.
 *
 * Thin controller: every endpoint delegates to EmployeePortalService so the
 * aggregation contract lives in ONE lint-clean service. Routes are gated by
 * the authenticated user scope inside the service (a user only ever sees
 * their own data — tasks, reports, calendar, etc. — never shared or
 * cross-account data).
 *
 * SECURITY: No employee_id is ever accepted from request input (IDOR-safe).
 * The service resolves the employee from $request->user()->employee.
 */
class EmployeePortalController extends Controller
{
    public function __construct(private readonly EmployeePortalService $employeePortalService)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success(
            'Dashboard loaded',
            $this->employeePortalService->dashboard(request()->user())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tasks
    // ─────────────────────────────────────────────────────────────────────────

    public function tasks(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'page', 'per_page']);

        return ApiResponse::success(
            'Tasks loaded',
            $this->employeePortalService->tasks($request->user(), $filters)
        );
    }

    public function task(Request $request, int $assignmentId): JsonResponse
    {
        return ApiResponse::success(
            'Task loaded',
            $this->employeePortalService->task($request->user(), $assignmentId)
        );
    }

    public function updateTaskProgress(Request $request, int $assignmentId): JsonResponse
    {
        $validated = $request->validate([
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        return ApiResponse::success(
            'Progress updated',
            $this->employeePortalService->updateTaskProgress(
                $request->user(),
                $assignmentId,
                $validated['progress']
            )
        );
    }

    public function submitTask(Request $request, int $assignmentId): JsonResponse
    {
        $validated = $request->validate([
            'notes'       => ['nullable', 'string', 'max:2000'],
            'late_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return ApiResponse::success(
            'Task submitted successfully',
            $this->employeePortalService->submitTask($request->user(), $assignmentId, $validated)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reports
    // ─────────────────────────────────────────────────────────────────────────

    public function reports(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'page', 'per_page']);

        return ApiResponse::success(
            'Reports loaded',
            $this->employeePortalService->reports($request->user(), $filters)
        );
    }

    public function report(Request $request, int $reportId): JsonResponse
    {
        return ApiResponse::success(
            'Report loaded',
            $this->employeePortalService->report($request->user(), $reportId)
        );
    }

    public function createReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id'    => ['required', 'integer'],
            'title'      => ['required', 'string', 'max:500'],
            'content'    => ['nullable', 'string'],
            'references' => ['nullable', 'string'],
        ]);

        $report = $this->employeePortalService->createReport($request->user(), $validated);

        return ApiResponse::success('Report created', ['report' => $report], 201);
    }

    public function updateReport(Request $request, int $reportId): JsonResponse
    {
        $validated = $request->validate([
            'title'      => ['nullable', 'string', 'max:500'],
            'content'    => ['nullable', 'string'],
            'references' => ['nullable', 'string'],
        ]);

        $report = $this->employeePortalService->updateReport($request->user(), $reportId, $validated);

        return ApiResponse::success('Report updated', ['report' => $report]);
    }

    public function submitReport(Request $request, int $reportId): JsonResponse
    {
        $report = $this->employeePortalService->submitReport($request->user(), $reportId);

        return ApiResponse::success('Report submitted for review', ['report' => $report]);
    }

    public function createRevision(Request $request, int $reportId): JsonResponse
    {
        $validated = $request->validate([
            'title'          => ['nullable', 'string', 'max:500'],
            'content'        => ['nullable', 'string'],
            'revision_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $this->employeePortalService->createRevision($request->user(), $reportId, $validated);

        return ApiResponse::success('Revision created', ['report' => $report]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calendar
    // ─────────────────────────────────────────────────────────────────────────

    public function calendar(): JsonResponse
    {
        return ApiResponse::success(
            'Calendar loaded',
            $this->employeePortalService->calendar(request()->user())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Performance
    // ─────────────────────────────────────────────────────────────────────────

    public function performance(): JsonResponse
    {
        return ApiResponse::success(
            'Performance loaded',
            $this->employeePortalService->performance(request()->user())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Targets
    // ─────────────────────────────────────────────────────────────────────────

    public function targets(): JsonResponse
    {
        return ApiResponse::success(
            'Targets loaded',
            $this->employeePortalService->targets(request()->user())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Achievements
    // ─────────────────────────────────────────────────────────────────────────

    public function achievements(): JsonResponse
    {
        return ApiResponse::success(
            'Achievements loaded',
            $this->employeePortalService->achievements(request()->user())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Notifications
    // ─────────────────────────────────────────────────────────────────────────

    public function notifications(): JsonResponse
    {
        return ApiResponse::success(
            'Notifications loaded',
            $this->employeePortalService->notifications(request()->user())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Profile & Security
    // ─────────────────────────────────────────────────────────────────────────

    public function profile(): JsonResponse
    {
        return ApiResponse::success(
            'Profile loaded',
            $this->employeePortalService->profile(request()->user())
        );
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        return ApiResponse::success(
            'Profile updated',
            $this->employeePortalService->updateProfile($request->user(), $validated)
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $this->employeePortalService->changePassword(
            $request->user(),
            $validated['current_password'],
            $validated['password']
        );

        return ApiResponse::success('Password changed successfully', []);
    }
}
