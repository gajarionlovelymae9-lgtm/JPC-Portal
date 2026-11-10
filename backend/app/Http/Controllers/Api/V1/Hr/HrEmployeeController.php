<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\Hr\HrEmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HR employee management endpoints.
 *
 * Authorization is deliberately doubled up:
 *  - the route group requires the HR portal permission and each route adds the
 *    specific `employees.*` permission, so a caller without `employees.create`
 *    cannot POST even inside the HR portal;
 *  - the Form Request `authorize()` and the explicit `authorize()` calls below
 *    both defer to EmployeePolicy.
 *
 * Either layer alone would be sufficient; both together mean a mistake in the
 * route file cannot silently widen access.
 *
 * The directory list itself stays on HrDashboardController@employees — that
 * endpoint already exists and is contract-tested, so it is not re-implemented
 * here. This controller owns the *management* operations: read one employee in
 * full, create, edit, and account status.
 */
class HrEmployeeController extends Controller
{
    public function __construct(private readonly HrEmployeeService $service) {}

    /**
     * GET /api/v1/hr/employees/{employee}
     *
     * The full profile: personal, employment, organisation, task summary, report
     * summary, performance, data-validation alerts and audit history.
     */
    public function show(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return ApiResponse::success('Employee profile loaded', $this->service->profile($employee));
    }

    /**
     * GET /api/v1/hr/employees/duplicate-name
     *
     * Advisory duplicate check used by the form before saving. It is a warning,
     * never a block: two employees can legitimately share a name.
     */
    public function duplicateName(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        $matches = $this->service->duplicateNames(
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            isset($data['exclude_id']) ? (int) $data['exclude_id'] : null,
        );

        return ApiResponse::success('Duplicate name check complete', [
            'normalized_name' => HrEmployeeService::normalizeName(
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
            ),
            'matches' => $matches,
        ]);
    }

    /**
     * POST /api/v1/hr/employees
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($request->validated(), $request->user());

        return ApiResponse::created(
            'Employee created successfully',
            $this->service->profile($employee),
        );
    }

    /**
     * PUT|PATCH /api/v1/hr/employees/{employee}
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->service->update($employee, $request->validated(), $request->user());

        return ApiResponse::success(
            'Employee updated successfully',
            $this->service->profile($employee),
        );
    }

    /**
     * POST /api/v1/hr/employees/{employee}/account-status
     *
     * Activating/deactivating a login never deletes the account, so the record
     * and its history survive.
     */
    public function accountStatus(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('manageAccount', $employee);

        $data = $request->validate([
            'account_status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        try {
            $employee = $this->service->setAccountStatus(
                $employee,
                UserStatus::from($data['account_status']),
                $request->user(),
            );
        } catch (\DomainException $exception) {
            // No linked account: a 422 tells the operator exactly what is wrong
            // instead of pretending the status changed.
            return ApiResponse::validationError($exception->getMessage(), [
                'account_status' => [$exception->getMessage()],
            ]);
        }

        return ApiResponse::success(
            'Account status updated successfully',
            $this->service->profile($employee),
        );
    }
}
