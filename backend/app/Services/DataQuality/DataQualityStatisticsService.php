<?php

namespace App\Services\DataQuality;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Models\DataQualityIssue;
use App\Models\DataQualityScan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DataQualityStatisticsService
{
    public function __construct(
        private readonly DataQualityIssueService $issueService,
        private readonly DataQualityScoreService $scoreService
    ) {}

    public function getDashboardStatistics(User $user): array
    {
        $baseQuery = DataQualityIssue::query();
        $this->issueService->applyRoleScope($baseQuery, $user);

        $totalIssues = (clone $baseQuery)->count();
        $openIssues = (clone $baseQuery)->whereIn('status', [
            DataQualityIssueStatus::OPEN,
            DataQualityIssueStatus::UNDER_REVIEW,
            DataQualityIssueStatus::CONFIRMED
        ])->count();

        $criticalCount = (clone $baseQuery)->where('severity', DataQualityIssueSeverity::CRITICAL)->whereIn('status', [DataQualityIssueStatus::OPEN, DataQualityIssueStatus::UNDER_REVIEW, DataQualityIssueStatus::CONFIRMED])->count();
        $highCount = (clone $baseQuery)->where('severity', DataQualityIssueSeverity::HIGH)->whereIn('status', [DataQualityIssueStatus::OPEN, DataQualityIssueStatus::UNDER_REVIEW, DataQualityIssueStatus::CONFIRMED])->count();
        $mediumCount = (clone $baseQuery)->where('severity', DataQualityIssueSeverity::MEDIUM)->whereIn('status', [DataQualityIssueStatus::OPEN, DataQualityIssueStatus::UNDER_REVIEW, DataQualityIssueStatus::CONFIRMED])->count();
        $lowCount = (clone $baseQuery)->where('severity', DataQualityIssueSeverity::LOW)->whereIn('status', [DataQualityIssueStatus::OPEN, DataQualityIssueStatus::UNDER_REVIEW, DataQualityIssueStatus::CONFIRMED])->count();
        $resolvedCount = (clone $baseQuery)->where('status', DataQualityIssueStatus::RESOLVED)->count();
        $dismissedCount = (clone $baseQuery)->where('status', DataQualityIssueStatus::DISMISSED)->count();

        $score = $this->scoreService->calculateScore($criticalCount, $highCount, $mediumCount, $lowCount, $resolvedCount);

        // Issues by Category
        $byCategory = (clone $baseQuery)
            ->whereIn('status', [DataQualityIssueStatus::OPEN, DataQualityIssueStatus::UNDER_REVIEW, DataQualityIssueStatus::CONFIRMED])
            ->select('entity_type', DB::raw('count(*) as count'))
            ->groupBy('entity_type')
            ->pluck('count', 'entity_type')
            ->toArray();

        // Issues by Department
        $byDept = (clone $baseQuery)
            ->whereIn('status', [DataQualityIssueStatus::OPEN, DataQualityIssueStatus::UNDER_REVIEW, DataQualityIssueStatus::CONFIRMED])
            ->whereNotNull('department_id')
            ->with('department:id,name')
            ->select('department_id', DB::raw('count(*) as count'))
            ->groupBy('department_id')
            ->get()
            ->map(fn ($r) => [
                'department_id' => $r->department_id,
                'department_name' => $r->department?->name ?? "Department #{$r->department_id}",
                'count' => (int)$r->count,
            ])
            ->toArray();

        // Recent Scans
        $recentScans = DataQualityScan::query()
            ->with('initiatedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Monthly trends (last 6 months)
        $trends = $this->getMonthlyTrends($user);

        return [
            'score' => $score,
            'total_issues' => $totalIssues,
            'open_issues' => $openIssues,
            'critical' => $criticalCount,
            'high' => $highCount,
            'medium' => $mediumCount,
            'low' => $lowCount,
            'resolved' => $resolvedCount,
            'dismissed' => $dismissedCount,
            'by_category' => $byCategory,
            'by_department' => $byDept,
            'recent_scans' => $recentScans,
            'trends' => $trends,
        ];
    }

    public function getMonthlyTrends(User $user, int $months = 6): array
    {
        $trends = [];
        $now = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = (clone $now)->subMonths($i);
            $year = $date->year;
            $month = $date->month;
            $label = $date->format('M Y');

            $query = DataQualityIssue::query()
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month);

            $this->issueService->applyRoleScope($query, $user);

            $count = $query->count();
            $resolved = (clone $query)->where('status', DataQualityIssueStatus::RESOLVED)->count();

            $trends[] = [
                'period' => $label,
                'year' => $year,
                'month' => $month,
                'issues_detected' => $count,
                'issues_resolved' => $resolved,
            ];
        }

        return $trends;
    }
}
