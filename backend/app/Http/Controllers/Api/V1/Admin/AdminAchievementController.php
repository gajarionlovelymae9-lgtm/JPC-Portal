<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\EmployeeBadge;
use App\Services\Admin\AdminAchievementService;
use App\Services\Admin\AdminBadgeManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAchievementController extends Controller
{
    public function __construct(
        private readonly AdminAchievementService $service,
        private readonly AdminBadgeManagementService $badges,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_of_month' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->service->paginate($filters);

        return ApiResponse::success('Achievements loaded successfully.', [
            'summary' => $this->service->summary(),
            'leaders' => $this->service->leaders($filters),
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function badges(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'badge_id' => ['nullable', 'integer', 'exists:badges,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'category' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'in:pending,verified,rejected'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->service->badges($filters);

        return ApiResponse::success('Badge awards loaded successfully.', [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success('Achievement options loaded successfully.', $this->service->options());
    }

    /* ------------------------------------------------------------------ */
    /* Badge definitions */
    /* ------------------------------------------------------------------ */

    public function badgeDefinitions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'string', 'in:1,0,true,false'],
            'category' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->badges->paginateBadges($filters);

        return ApiResponse::success('Badge definitions loaded successfully.', [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeBadge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:badges,name'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/', 'unique:badges,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:40'],
            'tier' => ['nullable', 'string', 'max:30'],
            'criteria_type' => ['nullable', 'string', 'max:40'],
            'criteria_value' => ['nullable', 'array'],
            // Known criteria fields get typed validation; custom keys stay
            // allowed so existing records and future criteria are supported.
            'criteria_value.period' => ['nullable', 'string', 'max:40'],
            'criteria_value.threshold' => ['nullable', 'numeric', 'min:0'],
            'criteria_value.min_score' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'allow_repeats' => ['nullable', 'boolean'],
        ]);

        // `validated()` only returns keys that carry rules, which would drop
        // custom criteria properties when nested rules are present. Keep the
        // raw object so unknown criteria keys round-trip untouched.
        if ($request->exists('criteria_value')) {
            $data['criteria_value'] = $request->input('criteria_value');
        }

        return ApiResponse::success('Badge created successfully.', $this->badges->createBadge($data, $request->user()));
    }

    public function updateBadge(Request $request, Badge $badge): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150', 'unique:badges,name,'.$badge->id],
            'code' => ['sometimes', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/', 'unique:badges,code,'.$badge->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:40'],
            'tier' => ['nullable', 'string', 'max:30'],
            'criteria_type' => ['nullable', 'string', 'max:40'],
            'criteria_value' => ['nullable', 'array'],
            'criteria_value.period' => ['nullable', 'string', 'max:40'],
            'criteria_value.threshold' => ['nullable', 'numeric', 'min:0'],
            'criteria_value.min_score' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'allow_repeats' => ['nullable', 'boolean'],
        ]);

        // Preserve unknown criteria properties (see storeBadge).
        if ($request->exists('criteria_value')) {
            $data['criteria_value'] = $request->input('criteria_value');
        }

        return ApiResponse::success('Badge updated successfully.', $this->badges->updateBadge($badge, $data, $request->user()));
    }

    public function destroyBadge(Request $request, Badge $badge): JsonResponse
    {
        $result = $this->badges->archiveBadge($badge, $request->user());

        return ApiResponse::success(
            $result['action'] === 'deleted'
                ? 'Badge deleted; it had never been awarded.'
                : 'Badge deactivated; its award history is preserved.',
            $result,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Award verification */
    /* ------------------------------------------------------------------ */

    public function awardDetail(int $id): JsonResponse
    {
        $award = EmployeeBadge::with(['employee.department', 'badge', 'academicYear', 'awarder', 'verifier'])->findOrFail($id);

        return ApiResponse::success('Award loaded successfully.', $this->badges->awardDetail($award));
    }

    public function verifyAward(Request $request, int $id): JsonResponse
    {
        $award = EmployeeBadge::findOrFail($id);

        return ApiResponse::success('Award verified.', $this->badges->verifyAward($award, $request->user()));
    }

    public function rejectAward(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $award = EmployeeBadge::findOrFail($id);

        return ApiResponse::success('Award rejected.', $this->badges->rejectAward($award, $data['reason'], $request->user()));
    }

    /* ------------------------------------------------------------------ */
    /* Summaries and evaluation */
    /* ------------------------------------------------------------------ */

    public function summaries(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'category' => ['nullable', 'string', 'max:40'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return ApiResponse::success('Achievement summaries loaded successfully.', [
            'cards' => $this->badges->summaryCards($filters),
            ...$this->badges->summaries($filters),
        ]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $result = $this->badges->evaluateMonth((int) $data['year'], (int) $data['month'], $request->user());

        return ApiResponse::success('Achievement evaluation completed.', $result);
    }
}
