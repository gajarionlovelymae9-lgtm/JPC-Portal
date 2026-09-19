<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\Admin\AdminDepartmentManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDepartmentController extends Controller
{
    public function __construct(
        private readonly AdminDepartmentManagementService $departmentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->departmentService->listDepartments($request->all());
        return ApiResponse::success('Departments loaded successfully.', $data);
    }

    public function show(Department $department): JsonResponse
    {
        $data = $this->departmentService->showDepartment($department);
        return ApiResponse::success('Department details loaded successfully.', $data);
    }
}