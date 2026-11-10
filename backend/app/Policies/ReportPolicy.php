<?php

namespace App\Policies;

use App\Enums\Permissions;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Only users with report-browsing permission may list reports.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permissions::REPORTS_VIEW);
    }

    /**
     * A user may view their own report, or any report when they hold a
     * reviewer/management role or permission. Ownership is resolved via the
     * linked employee record (reports.employee_id references employees.id).
     */
    public function view(User $user, Report $report): bool
    {
        if ($this->ownsReport($user, $report)) {
            return true;
        }

        return $user->hasPermission(Permissions::REPORTS_REVIEW)
            || $user->hasPermission(Permissions::REPORTS_APPROVE)
            || $user->hasAnyRole(['admin', 'hr', 'department_head']);
    }

    public function update(User $user, Report $report): bool
    {
        if ($this->ownsReport($user, $report)) {
            return true;
        }

        return $user->hasPermission(Permissions::REPORTS_REVIEW)
            || $user->hasPermission(Permissions::REPORTS_APPROVE)
            || $user->hasAnyRole(['admin', 'hr', 'department_head']);
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->hasPermission(Permissions::REPORTS_DELETE);
    }

    private function ownsReport(User $user, Report $report): bool
    {
        return $user->employee !== null
            && (int) $user->employee->id === (int) $report->employee_id;
    }
}
