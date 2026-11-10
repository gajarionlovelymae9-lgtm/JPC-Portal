<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\EmployeePortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

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
    public function __construct(private readonly EmployeePortalService $employeePortalService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return ApiResponse::success(
            'Dashboard loaded',
            $this->employeePortalService->dashboard(
                $request->user(),
                $validated['year'] ?? null,
                $validated['month'] ?? null,
            )
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
            'notes' => ['nullable', 'string', 'max:2000'],
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
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'report_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'reporting_period' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ai_summary_status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success(
            'Reports loaded',
            $this->employeePortalService->reports($request->user(), $validated)
        );
    }

    public function report(Request $request, int $report): JsonResponse
    {
        return ApiResponse::success(
            'Report loaded',
            $this->employeePortalService->report($request->user(), $report)
        );
    }

    public function createReport(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reportRules(false));
        $report = $this->employeePortalService->createReport($request->user(), $validated, $this->attachments($request));

        return ApiResponse::success('Report created', ['report' => $this->reportShape($report)], 201);
    }

    public function updateReport(Request $request, int $report): JsonResponse
    {
        $validated = $request->validate($this->reportRules(true));
        $updated = $this->employeePortalService->updateReport($request->user(), $report, $validated, $this->attachments($request));

        return ApiResponse::success('Report updated', ['report' => $this->reportShape($updated)]);
    }

    public function submitReport(Request $request, int $report): JsonResponse
    {
        $validated = $request->validate($this->reportRules(true));
        $result = $this->employeePortalService->submitReport($request->user(), $report, $validated, $this->attachments($request));

        return ApiResponse::success('Report submitted for review', $result);
    }

    public function createRevision(Request $request, int $report): JsonResponse
    {
        $validated = $request->validate([
            'revision_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'title' => ['sometimes', 'string', 'max:255'],
            'report_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'reporting_period' => ['sometimes', 'nullable', 'string', 'max:120'],
            'report_description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'accomplishments' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'completed_tasks' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'completed_tasks_summary' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'challenges_encountered' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'next_plans' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'references' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $revised = $this->employeePortalService->createRevision($request->user(), $report, $validated);

        return ApiResponse::success('Revision created', ['report' => $revised]);
    }

    public function generateReportSummary(Request $request, int $report): JsonResponse
    {
        $validated = $request->validate([
            'regenerate' => ['sometimes', 'boolean'],
        ]);

        $result = $this->employeePortalService->generateReportSummary(
            $request->user(),
            $report,
            (bool) ($validated['regenerate'] ?? false),
        );

        return ApiResponse::success('AI summary processed', $result);
    }

    public function downloadReportFile(Request $request, int $report, int $file)
    {
        return $this->employeePortalService->downloadReportFile($request->user(), $report, $file);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRules(bool $isUpdate): array
    {
        $required = $isUpdate ? ['sometimes'] : ['required'];

        return [
            'task_id' => [$isUpdate ? 'sometimes' : 'required', 'integer'],
            'title' => [...$required, 'string', 'max:255'],
            'report_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'reporting_period' => ['sometimes', 'nullable', 'string', 'max:120'],
            'report_description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'accomplishments' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'completed_tasks' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'completed_tasks_summary' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'challenges_encountered' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'next_plans' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'references' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,txt,png,jpg,jpeg'],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    private function attachments(Request $request): array
    {
        $files = $request->file('attachments', []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return is_array($files) ? array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile)) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportShape(\App\Models\Report $report): array
    {
        return $this->employeePortalService->report(request()->user(), $report->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calendar
    // ─────────────────────────────────────────────────────────────────────────

    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return ApiResponse::success(
            'Calendar loaded',
            $this->employeePortalService->calendar(
                $request->user(),
                $validated['year'] ?? null,
                $validated['month'] ?? null,
            )
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
