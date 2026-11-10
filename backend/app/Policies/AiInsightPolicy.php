<?php

namespace App\Policies;

use App\Models\AiInsight;
use App\Models\User;

class AiInsightPolicy
{
    public function view(User $user, AiInsight $insight)
    {
        if ($user->hasAnyRole(['admin', 'hr'])) {
            return true;
        } if ($user->employee && $user->employee->id === $insight->employee_id) {
            return true;
        } if ($user->hasRole('department_head') && $user->employee && $user->employee->department_id === $insight->department_id) {
            return true;
        }

return false;
    }
}
