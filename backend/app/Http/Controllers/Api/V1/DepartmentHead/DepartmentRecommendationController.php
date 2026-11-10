<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Services\DepartmentHead\DepartmentRecommendationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentRecommendationController extends Controller
{
    public function __construct(
        private readonly DepartmentRecommendationService $recommendations,
    ) {}

    /**
     * The head's submitted recommendations and their review status.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Recommendations loaded successfully.',
            ['recommendations' => $this->recommendations->listMine($request->user())],
        );
    }

    /**
     * Nominees (own department only) and the Admin badge catalogue.
     */
    public function options(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Recommendation options loaded successfully.',
            [
                'employees' => $this->recommendations->employeeOptions($request->user()),
                'badges' => $this->recommendations->badgeOptions(),
            ],
        );
    }

    /**
     * Recommend an employee for official recognition.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'badge_id' => ['required', 'integer', 'exists:badges,id'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ]);

        $award = $this->recommendations->recommend($request->user(), $validated);

        return ApiResponse::created(
            'Recommendation submitted. HR will review it in the pending queue.',
            $award,
        );
    }
}
