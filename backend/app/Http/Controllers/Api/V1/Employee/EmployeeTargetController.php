<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\EmployeeTargetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * My Targets — employee self-service workspace (breakdown, milestones,
 * adjustment requests, achievement notes, AI target suggestions).
 *
 * Thin controller: every handler delegates to EmployeeTargetService, which
 * resolves the employee from the authenticated user. No `employee_id` is ever
 * accepted, and a target that is not the caller's is answered as 404.
 */
class EmployeeTargetController extends Controller
{
    public function __construct(private readonly EmployeeTargetService $targetService) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Targets loaded',
            $this->targetService->listForEmployee($request->user())
        );
    }

    /**
     * Advisory, target-only suggestions derived from the caller's own targets.
     * Registered before `/targets/{target}` so the literal path is not captured
     * by the wildcard.
     */
    public function suggestions(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Target suggestions loaded',
            $this->targetService->suggestions($request->user())
        );
    }

    public function show(Request $request, int $target): JsonResponse
    {
        return ApiResponse::success(
            'Target loaded',
            $this->targetService->employeeTargetDetail($request->user(), $target)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Breakdown (sub-targets)
    // ─────────────────────────────────────────────────────────────────────────

    public function breakdowns(Request $request, int $target): JsonResponse
    {
        return ApiResponse::success(
            'Sub-targets loaded',
            $this->targetService->breakdowns($request->user(), $target)
        );
    }

    public function storeBreakdown(Request $request, int $target): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'target_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return ApiResponse::created(
            'Sub-target added',
            $this->targetService->createBreakdown($request->user(), $target, $validated)
        );
    }

    public function updateBreakdown(Request $request, int $target, int $breakdown): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'target_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return ApiResponse::success(
            'Sub-target updated',
            $this->targetService->updateBreakdown($request->user(), $target, $breakdown, $validated)
        );
    }

    public function destroyBreakdown(Request $request, int $target, int $breakdown): JsonResponse
    {
        $this->targetService->deleteBreakdown($request->user(), $target, $breakdown);

        return ApiResponse::success('Sub-target removed', []);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Milestones
    // ─────────────────────────────────────────────────────────────────────────

    public function milestones(Request $request, int $target): JsonResponse
    {
        return ApiResponse::success(
            'Milestones loaded',
            $this->targetService->milestones($request->user(), $target)
        );
    }

    public function storeMilestone(Request $request, int $target): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'threshold_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return ApiResponse::created(
            'Milestone added',
            $this->targetService->createMilestone($request->user(), $target, $validated)
        );
    }

    public function updateMilestone(Request $request, int $target, int $milestone): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'threshold_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return ApiResponse::success(
            'Milestone updated',
            $this->targetService->updateMilestone($request->user(), $target, $milestone, $validated)
        );
    }

    public function destroyMilestone(Request $request, int $target, int $milestone): JsonResponse
    {
        $this->targetService->deleteMilestone($request->user(), $target, $milestone);

        return ApiResponse::success('Milestone removed', []);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Adjustment requests
    // ─────────────────────────────────────────────────────────────────────────

    public function adjustments(Request $request, int $target): JsonResponse
    {
        return ApiResponse::success(
            'Adjustment requests loaded',
            $this->targetService->adjustments($request->user(), $target)
        );
    }

    public function storeAdjustment(Request $request, int $target): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'request_type' => ['sometimes', 'nullable', 'in:target_value,deadline,period,description,other'],
            'proposed_target_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'proposed_target_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'proposed_start_date' => ['sometimes', 'nullable', 'date'],
            'proposed_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:proposed_start_date'],
            'proposed_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        return ApiResponse::created(
            'Adjustment request submitted',
            $this->targetService->createAdjustment($request->user(), $target, $validated)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Achievement notes
    // ─────────────────────────────────────────────────────────────────────────

    public function notes(Request $request, int $target): JsonResponse
    {
        return ApiResponse::success(
            'Notes loaded',
            $this->targetService->notes($request->user(), $target)
        );
    }

    public function storeNote(Request $request, int $target): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        return ApiResponse::created(
            'Note added',
            $this->targetService->createNote($request->user(), $target, $validated)
        );
    }

    public function updateNote(Request $request, int $target, int $note): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        return ApiResponse::success(
            'Note updated',
            $this->targetService->updateNote($request->user(), $target, $note, $validated)
        );
    }

    public function destroyNote(Request $request, int $target, int $note): JsonResponse
    {
        $this->targetService->deleteNote($request->user(), $target, $note);

        return ApiResponse::success('Note removed', []);
    }
}
