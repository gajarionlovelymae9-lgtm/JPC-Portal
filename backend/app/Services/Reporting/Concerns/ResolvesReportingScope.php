<?php

namespace App\Services\Reporting\Concerns;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Role-aware query scoping shared by the Phase 9 Reporting and Analytics
 * services.
 *
 * Backend authorization is mandatory (spec item 6): every generator scopes
 * its queries to the caller's role —
 *   admin / hr            → institution-wide (plus any requested filter),
 *   department_head       → locked to their own department (403 otherwise),
 *   employee              → locked to their own employee record.
 *
 * This is defence-in-depth underneath the controller/middleware layer; the AI
 * chat tools (spec item 58) call these services directly with the
 * authenticated user, so the services themselves must never trust the params.
 */
trait ResolvesReportingScope
{
    /**
     * Resolve the effective department scope for the caller.
     *
     * @throws AuthorizationException when a department head requests another
     *                                department or has no linked department.
     */
    protected function effectiveDepartmentId(User $user, ?int $requestedDepartmentId): ?int
    {
        if ($user->roleName() !== 'department_head') {
            return $requestedDepartmentId;
        }

        $ownedDepartmentId = $user->employee ? (int) $user->employee->department_id : null;

        if ($ownedDepartmentId === null) {
            throw new AuthorizationException('Forbidden');
        }

        if ($requestedDepartmentId !== null && $requestedDepartmentId !== $ownedDepartmentId) {
            throw new AuthorizationException('Forbidden');
        }

        return $ownedDepartmentId;
    }

    /**
     * Guarantee the caller is allowed to view this employee's report.
     *
     * @throws AuthorizationException
     */
    protected function assertEmployeeScope(User $user, int $employeeId): void
    {
        if ($user->roleName() === 'employee') {
            $ownId = (int) ($user->employee?->id ?? 0);

            if ($ownId === 0 || $ownId !== $employeeId) {
                throw new AuthorizationException('Forbidden');
            }

            return;
        }

        if ($user->roleName() === 'department_head') {
            $requested = Employee::query()->find($employeeId);

            if ($requested === null) {
                throw new AuthorizationException('Forbidden');
            }

            if ((int) $requested->department_id !== (int) $user->employee?->department_id) {
                throw new AuthorizationException('Forbidden');
            }
        }
    }

    /**
     * Apply the caller's role scope to any query exposing an `employee_id`
     * column (reports, task assignments, performance records, targets,
     * achievements …).
     *
     * @param  Builder  $query
     * @return Builder
     */
    protected function scopeByEmployeeRelation(object $query, User $user, ?int $departmentId = null): object
    {
        $role = $user->roleName();

        if ($role === 'employee') {
            $employeeId = (int) ($user->employee?->id ?? 0);

            if ($employeeId > 0) {
                $query->where('employee_id', $employeeId);
            } else {
                $query->whereRaw('1 = 0');
            }

            return $query;
        }

        if ($role === 'department_head') {
            $ownedDepartmentId = (int) ($user->employee?->department_id ?? 0);

            if ($ownedDepartmentId > 0) {
                $query->whereHas('employee', fn ($q) => $q->where('department_id', $ownedDepartmentId));
            } else {
                $query->whereRaw('1 = 0');
            }

            return $query;
        }

        // Admin / HR: honour the requested (already resolved) department filter.
        if ($departmentId !== null) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        return $query;
    }

    /**
     * Scope an `employees` query itself (admin/hr: requested department or
     * all; department head: own department; employee: own record only).
     *
     * @param  Builder  $query
     * @return Builder
     */
    protected function scopeEmployeeQuery(object $query, User $user, ?int $departmentId = null): object
    {
        $role = $user->roleName();

        if ($role === 'employee') {
            $ownId = (int) ($user->employee?->id ?? 0);

            return $ownId > 0
                ? $query->where('id', $ownId)
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'department_head') {
            $ownedDepartmentId = (int) ($user->employee?->department_id ?? 0);

            return $ownedDepartmentId > 0
                ? $query->where('department_id', $ownedDepartmentId)
                : $query->whereRaw('1 = 0');
        }

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query;
    }

    /**
     * Whether the caller may run institution-wide reports at all.
     */
    protected function isManager(User $user): bool
    {
        return in_array($user->roleName(), ['admin', 'hr', 'department_head'], true);
    }
}
