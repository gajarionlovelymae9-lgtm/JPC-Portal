<?php

namespace App\Policies;

use App\Models\DataQualityIssue;
use App\Models\User;

class DataQualityIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr', 'department_head']) || $user->employee !== null;
    }

    public function view(User $user, DataQualityIssue $issue): bool
    {
        if ($user->hasAnyRole(['admin', 'hr'])) {
            return true;
        }

        if ($user->hasRole('department_head')) {
            $deptId = $user->employee?->department_id;
            return $issue->department_id === null || $issue->department_id === $deptId;
        }

        if ($user->employee) {
            return $issue->employee_id === $user->employee->id;
        }

        return false;
    }

    public function review(User $user, DataQualityIssue $issue): bool
    {
        if ($user->hasAnyRole(['admin', 'hr'])) {
            return true;
        }

        if ($user->hasRole('department_head')) {
            $deptId = $user->employee?->department_id;
            return $issue->department_id === null || $issue->department_id === $deptId;
        }

        return false;
    }

    public function resolve(User $user, DataQualityIssue $issue): bool
    {
        return $this->review($user, $issue);
    }
}
