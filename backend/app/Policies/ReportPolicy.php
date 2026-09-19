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
     * A user may only view reports belonging to themselves, unless they
     * hold a broader management permission.
     */
    public function view(User $user, Report $report): bool
    {
        return $report->employee_id === $user->id
            || $user->hasPermission(Permissions::REPORTS_UPDATE);
    }

    public function update(User $user, Report $report): bool
    {
        return $report->employee_id === $user->id
            || $user->hasPermission(Permissions::REPORTS_UPDATE);
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->hasPermission(Permissions::REPORTS_DELETE);
    }
}
