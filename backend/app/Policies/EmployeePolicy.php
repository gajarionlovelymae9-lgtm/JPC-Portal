<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

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

        return $user->employee !== null
            && (int) $user->employee->id === (int) $employee->id;
    }
}
