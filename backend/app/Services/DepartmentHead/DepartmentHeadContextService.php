<?php

namespace App\Services\DepartmentHead;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Support\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class DepartmentHeadContextService
{
    /**
     * Resolve the current department for the authenticated user.
     */
    public function resolveDepartment(User $user, ?int $explicitDepartmentId = null): Department
    {
        if ($user->hasRole('admin')) {
            if ($explicitDepartmentId) {
                return Department::findOrFail($explicitDepartmentId);
            }
            return Department::firstOrFail();
        }

        $employee = $user->employee;
        if (! $employee || ! $employee->department_id) {
            throw new HttpResponseException(
                ApiResponse::error('You are not assigned to any department.', Response::HTTP_FORBIDDEN)
            );
        }

        return $employee->department;
    }

    /**
     * Ensure the given employee belongs to the head's department.
     */
    public function authorizeEmployee(Department $department, Employee $employee): void
    {
        if ($employee->department_id !== $department->id) {
            throw new HttpResponseException(
                ApiResponse::error('Employee does not belong to your department.', Response::HTTP_FORBIDDEN)
            );
        }
    }

    /**
     * Ensure the given task belongs to the head's department.
     */
    public function authorizeTask(Department $department, Task $task): void
    {
        if ($task->department_id && $task->department_id !== $department->id) {
            throw new HttpResponseException(
                ApiResponse::error('Task does not belong to your department.', Response::HTTP_FORBIDDEN)
            );
        }
    }

    /**
     * Ensure the given report was submitted by an employee in the head's department.
     */
    public function authorizeReport(Department $department, Report $report): void
    {
        $reportEmployee = $report->employee ?? Employee::find($report->employee_id);
        if (! $reportEmployee || $reportEmployee->department_id !== $department->id) {
            throw new HttpResponseException(
                ApiResponse::error('Report does not belong to your department.', Response::HTTP_FORBIDDEN)
            );
        }
    }
}
