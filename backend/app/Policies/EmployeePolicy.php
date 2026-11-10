<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

    /**
     * Roles allowed to run HR/Admin employee management at all.
     *
     * This is a role gate only; the permission check below is what actually
     * authorises the action, so a future role that is granted
     * `employees.update` is not silently excluded by a stale hard-coded list.
     */
    private const MANAGEMENT_ROLES = ['admin', 'hr'];

    /**
     * Read the institution-wide employee directory.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user, 'employees.view');
    }

    /**
     * Read one employee's record.
     */
    public function view(User $user, Employee $employee): bool
    {
        return $this->canManage($user, 'employees.view');
    }

    /**
     * Create a new employee record.
     */
    public function create(User $user): bool
    {
        return $this->canManage($user, 'employees.create');
    }

    /**
     * Edit an employee record.
     *
     * An employee may not edit their own HR record through this surface: the
     * self-service `/employee/profile` endpoint owns that path, and HR must not
     * be able to escalate by editing their own row.
     */
    public function update(User $user, Employee $employee): bool
    {
        if ($this->isSelf($user, $employee)) {
            return false;
        }

        return $this->canManage($user, 'employees.update');
    }

    /**
     * Activate/deactivate the linked login account.
     *
     * Uses `employees.update` because the account status is an attribute of the
     * employee record; `users.update` remains the privilege for editing the
     * login itself from the Admin user-management surface.
     */
    public function manageAccount(User $user, Employee $employee): bool
    {
        if ($this->isSelf($user, $employee)) {
            return false;
        }

        return $this->canManage($user, 'employees.update');
    }

    /**
     * An employee's task list is visible to managers or to the employee
     * themselves. Everyone else is denied so private assignments are not
     * leaked across employees (IDOR protection).
     */
    public function viewTasks(User $user, Employee $employee): bool
    {
        if (in_array($user->roleName(), ['admin', 'department_head'], true)
            && $user->hasPermission('tasks.view')) {
            return true;
        }

        return $this->isSelf($user, $employee);
    }

    /**
     * @param  Employee  $employee
     */
    private function canManage(User $user, string $permission): bool
    {
        if (! in_array($user->roleName(), self::MANAGEMENT_ROLES, true)) {
            return false;
        }

        return $user->hasPermission($permission);
    }

    private function isSelf(User $user, Employee $employee): bool
    {
        return $user->employee !== null
            && (int) $user->employee->id === (int) $employee->id;
    }
}
