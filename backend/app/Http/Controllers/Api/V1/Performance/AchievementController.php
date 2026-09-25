<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\EmployeeBadge;
use App\Models\MonthlyAchievement;
use App\Models\PerformancePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    /**
     * Catalog of all system badges and criteria.
     */
    public function index(): JsonResponse
    {
        $badges = Badge::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $badges,
        ]);
    }

    /**
     * Current authenticated employee's badges and achievements.
     */
    public function myAchievements(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'No employee record found.'], 404);
        }

        $badges = EmployeeBadge::where('employee_id', $employee->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->get();

        $monthlyAchievements = MonthlyAchievement::where('employee_id', $employee->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_badges' => $badges->count(),
                'employee_of_month_count' => $monthlyAchievements->where('is_employee_of_month', true)->count(),
                'badges' => $badges,
                'monthly_history' => $monthlyAchievements,
            ],
        ]);
    }

    /**
     * Monthly achievements, top performers, and Employee of the Month.
     */
    public function monthly(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $period = PerformancePeriod::findOrCreateMonthly($year, $month);

        $achievements = MonthlyAchievement::where('year', $year)
            ->where('month', $month)
            ->with(['employee.department', 'employee.position'])
            ->orderBy('rank')
            ->get();

        $eom = $achievements->firstWhere('is_employee_of_month', true);

        return response()->json([
            'success' => true,
            'period' => $period,
            'employee_of_the_month' => $eom,
            'rankings' => $achievements,
        ]);
    }
}
