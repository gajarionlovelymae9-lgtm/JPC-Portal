<?php

namespace App\Policies;

use App\Enums\Permissions;
use App\Models\AiAnalysis;
use App\Models\User;

class AiAnalysisPolicy
{
    /**
     * Only users with report-browsing permission may list AI analyses.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permissions::REPORTS_VIEW);
    }

    /**
     * A user may only view AI analyses belonging to their own reports.
     */
    public function view(User $user, AiAnalysis $analysis): bool
    {
        $report = $analysis->report;

        return $report->employee_id === $user->id
            || $user->hasPermission(Permissions::REPORTS_UPDATE);
    }
}
