<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\DepartmentHead\DepartmentEmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentEmployeeController extends Controller
{
    public function __construct(
        private readonly DepartmentEmployeeService $employeeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['department_id', 'status', 'search']);
        $employees = $this->employeeService->listEmployees($request->user(), $filters);

        return ApiResponse::success('Department employees loaded successfully.', $employees);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $data = $this->employeeService->showEmployee($request->user(), $employee);

        return ApiResponse::success('Employee details loaded successfully.', $data);
    }
}
