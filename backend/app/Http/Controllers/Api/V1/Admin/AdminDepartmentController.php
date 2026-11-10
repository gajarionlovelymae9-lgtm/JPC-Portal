<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignDepartmentEmployeesRequest;
use App\Http\Requests\Admin\AssignDepartmentHeadRequest;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentStatusRequest;
use App\Models\Department;
use App\Services\Admin\AdminDepartmentManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin department management (People Management → Department Management).
 *
 *   GET    /api/v1/admin/departments                  (existing analytics list)
 *   GET    /api/v1/admin/departments/{department}     (existing analytics detail)
 *   POST   /api/v1/admin/departments                  (new)
 *   PUT    /api/v1/admin/departments/{department}     (new)
 *   POST   /api/v1/admin/departments/{department}/status        (new)
 *   POST   /api/v1/admin/departments/{department}/head          (new)
 *   POST   /api/v1/admin/departments/{department}/employees     (new)
 *   GET    /api/v1/admin/departments/summary                 (new)
 *   GET    /api/v1/admin/departments/options                 (new)
 *   GET    /api/v1/admin/departments/{department}/eligible-heads (new)
 *
 * Every mutation validates through a Form Request whose `authorize()` reads
 * the caller's real permission, and audited through the shared ActivityLog.
 */
class AdminDepartmentController extends Controller
{
    public function __construct(
        private readonly AdminDepartmentManagementService $departmentService
    ) {}

    /* ---------------------------------------------------------------- */
    /* Reads */
    /* ---------------------------------------------------------------- */

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        return ApiResponse::success('Departments loaded successfully.', $this->departmentService->listDepartments($filters));
    }

    public function show(Department $department): JsonResponse
    {
        return ApiResponse::success('Department details loaded successfully.', $this->departmentService->showDepartment($department));
    }

    /**
     * Summary-card counts, always computed from live queries.
     */
    public function summary(): JsonResponse
    {
        return ApiResponse::success('Department summary loaded successfully.', $this->departmentService->summary());
    }

    /**
     * Options for the create/edit/assignment forms.
     */
    public function options(): JsonResponse
    {
        return ApiResponse::success('Department options loaded successfully.', $this->departmentService->options());
    }

    /**
     * Employees eligible to head the given department, plus diagnostics that
     * explain an empty result. Read-only: never grants roles.
     */
    public function eligibleHeads(Department $department): JsonResponse
    {
        return ApiResponse::success(
            'Eligible department heads loaded successfully.',
            $this->departmentService->eligibleHeads($department),
        );
    }

    /* ---------------------------------------------------------------- */
    /* Writes */
    /* ---------------------------------------------------------------- */

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->departmentService->create($request->validated(), $request->user());

        return ApiResponse::created('Department created successfully.', $department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $data = $this->departmentService->update($department, $request->validated(), $request->user());

        return ApiResponse::success('Department updated successfully.', $data);
    }

    public function status(UpdateDepartmentStatusRequest $request, Department $department): JsonResponse
    {
        $data = $this->departmentService->setStatus($department, (string) $request->validated('status'), $request->user());

        return ApiResponse::success(
            $request->validated('status') === 'active' ? 'Department activated successfully.' : 'Department deactivated successfully.',
            $data,
        );
    }

    public function assignHead(AssignDepartmentHeadRequest $request, Department $department): JsonResponse
    {
        $data = $this->departmentService->assignHead($department, (int) $request->validated('employee_id'), $request->user());

        return ApiResponse::success('Department head assigned successfully.', $data);
    }

    public function assignEmployees(AssignDepartmentEmployeesRequest $request, Department $department): JsonResponse
    {
        $result = $this->departmentService->assignEmployees(
            $department,
            array_map('intval', $request->validated('employee_ids')),
            $request->user(),
        );

        return ApiResponse::success(
            sprintf('Assigned %d employee(s) to the department.', $result['transferred']),
            $result['department'],
        );
    }
}
