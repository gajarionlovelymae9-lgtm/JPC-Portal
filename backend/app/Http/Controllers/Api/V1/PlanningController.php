<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\DepartmentPlan;
use App\Models\EmployeeTarget;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/planning
 *
 * Read-only planning overview for the Admin and HR roles: academic years,
 * annual plans, department plans and the employee targets derived from them.
 *
 * This endpoint intentionally exposes no mutations — planning edits remain
 * out of scope for this phase, and the page renders the seeded planning
 * chain exactly as stored so leadership can inspect it in one request.
 */
class PlanningController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'status']);

        $annualPlans = AnnualPlan::query()
            ->with(['department:id,name', 'academicYear:id,name'])
            ->withCount(['departmentPlans', 'employeeTargets'])
            ->orderByDesc('id')
            ->get(['id', 'academic_year_id', 'department_id', 'title', 'description', 'status', 'approved_at']);

        $departmentPlans = DepartmentPlan::query()
            ->with(['department:id,name', 'annualPlan:id,title'])
            ->orderByDesc('id')
            ->get(['id', 'annual_plan_id', 'department_id', 'title', 'status', 'approved_at']);

        $employeeTargets = EmployeeTarget::query()
            ->with([
                'employee:id,first_name,middle_name,last_name,employee_number,department_id',
                'employee.department:id,name',
            ])
            ->withCount('tasks')
            ->orderByDesc('id')
            ->get(['id', 'annual_plan_id', 'employee_id', 'title', 'target_value', 'target_unit', 'start_date', 'end_date', 'status'])
            ->map(fn (EmployeeTarget $target) => [
                ...$target->toArray(),
                'employee_name' => $target->employee?->full_name,
                'employee_number' => $target->employee?->employee_number,
                'department_name' => $target->employee?->department?->name,
            ]);

        return ApiResponse::success('Planning overview loaded successfully.', [
            'summary' => [
                'academic_years' => $academicYears->count(),
                'annual_plans' => $annualPlans->count(),
                'annual_plans_by_status' => $annualPlans->groupBy('status')->map->count(),
                'department_plans' => $departmentPlans->count(),
                'employee_targets' => $employeeTargets->count(),
                'employee_targets_by_status' => $employeeTargets->groupBy('status')->map->count(),
            ],
            'academic_years' => $academicYears,
            'annual_plans' => $annualPlans,
            'department_plans' => $departmentPlans,
            'employee_targets' => $employeeTargets,
        ]);
    }
}
