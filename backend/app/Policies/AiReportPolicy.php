<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class AiReportPolicy
{
    public function summarize(User $user, Report $report)
    {
        if ($user->hasAnyRole(['admin', 'hr', 'department_head'])) {
            return true;
        }

return $user->employee && $user->employee->id === $report->employee_id;
    }
}
