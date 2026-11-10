<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\EmployeeAIInsightsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee AI Insights — advisory self-service analytics.
 *
 * SECURITY: no employee_id is accepted from request input. The employee is
 * resolved from the authenticated user's linked profile inside the service, so
 * one employee can never read another employee's insights.
 */
class EmployeeAIInsightsController extends Controller
{
    public function __construct(private readonly EmployeeAIInsightsService $aiInsightsService) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return ApiResponse::success(
            'AI insights loaded',
            $this->aiInsightsService->insights(
                $request->user(),
                $validated['year'] ?? null,
                $validated['month'] ?? null,
            )
        );
    }
}
