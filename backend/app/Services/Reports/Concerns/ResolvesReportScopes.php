<?php

namespace App\Services\Reports\Concerns;

use App\Models\Employee;
use App\Models\User;
use App\Services\Dashboard\ManagementDashboardService;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Authorization/scope resolution shared by the reports module.
 *
 * Department heads are always locked to their own department (403 otherwise),
 * and employees are limited to their own employee record. The existing
 * ManagementDashboardService method is the single source of truth for the
 * department-head rule so both surfaces behave identically.
 */
trait ResolvesReportScopes
{
    /**
     * Resolve the effective department scope (admin/hr: any; department
     * head: own only, 403 for anything else; employee: rejected).
     *
     * @throws AuthorizationException
     */
    protected function departmentScope(User $user, ?int $requestedDepartmentId): ?int
    {
        return app(ManagementDashboardService::class)->resolveDepartmentScope($user, $requestedDepartmentId);
    }

    /**
     * Guarantee the authenticated user is allowed to view this employee.
     *
     * Managers pass through (the department scope already covers dept-head
     * restrictions); employees may only ever access their own record; dept
     * heads may only access employees inside their own department.
     *
     * @throws AuthorizationException
     */
    protected function assertEmployeeScope(User $user, Employee $employee): void
    {
        if ($user->roleName() === 'employee') {
            if ($employee->id !== (int) ($user->employee?->id ?? 0)) {
                throw new AuthorizationException('Forbidden');
            }

            return;
        }

        app(ManagementDashboardService::class)->resolveEmployeeScope($user, $employee);
    }
}
