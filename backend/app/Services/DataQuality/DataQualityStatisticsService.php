<?php

namespace App\Services\DataQuality;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualityScanStatus;
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

        $scanSummary = $this->getScanSummary();
        $totalIssues = (clone $baseQuery)->count();
        $openIssues = (clone $baseQuery)->whereIn('status', [
            DataQualityIssueStatus::OPEN,
            DataQualityIssueStatus::UNDER_REVIEW,
            DataQualityIssueStatus::CONFIRMED,
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
                'count' => (int) $r->count,
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

        // Record coverage: of the records covered by the latest completed
        // scan, the ones without any detected issue count as complete.
        $recordsWithIssues = 0;
        $totalRecords = (int) ($scanSummary['records_checked'] ?? 0);
        if ($totalRecords > 0 && ($scan = DataQualityScan::query()->where('status', DataQualityScanStatus::COMPLETED)->orderByDesc('completed_at')->orderByDesc('id')->first())) {
            $concatExpr = DB::connection()->getDriverName() === 'sqlite' ? "entity_type || '-' || entity_id" : "CONCAT(entity_type, '-', entity_id)";
            $recordsWithIssues = DataQualityIssue::query()
                ->where('scan_id', $scan->id)
                ->distinct()
                ->count(DB::raw($concatExpr));
        }

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
            'scan_summary' => array_merge($scanSummary, [
                'total_records' => $totalRecords,
                'records_with_issues' => (int) $recordsWithIssues,
                'complete_records' => max(0, $totalRecords - (int) $recordsWithIssues),
            ]),
            'by_category' => $byCategory,
            'by_department' => $byDept,
            'recent_scans' => $recentScans,
            'trends' => $trends,
        ];
    }

    /**
     * Coverage metadata for the dashboard's Data Quality Score card.
     *
     * The score itself is derived from issues, which can legitimately be zero
     * before any scan has ever run. `evaluated` distinguishes "checked and
     * clean" from "never checked" so the UI can render N/A instead of a
     * misleading 100%.
     *
     * @return array{evaluated: bool, records_checked: int, issues_found: int, latest_scan_at: string|null, latest_records_checked: int, latest_issues_found: int}
     */
    public function getScanSummary(): array
    {
        $completed = DataQualityScan::query()
            ->where('status', DataQualityScanStatus::COMPLETED);

        $latest = (clone $completed)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        return [
            'evaluated' => $latest !== null,
            // Coverage comes from the most recent completed scan: summing every
            // scan would double count records that were re-checked.
            'records_checked' => (int) ($latest->records_checked ?? 0),
            'issues_found' => (int) ($latest->issues_found ?? 0),
            'latest_scan_at' => $latest?->completed_at?->toIso8601String(),
            'latest_records_checked' => (int) ($latest->records_checked ?? 0),
            'latest_issues_found' => (int) ($latest->issues_found ?? 0),
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
