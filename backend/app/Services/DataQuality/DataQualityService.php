<?php

namespace App\Services\DataQuality;

use App\Models\DataQualityIssue;
use App\Models\DataQualityScan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DataQualityService
{
    public function __construct(
        private readonly DataQualityScanner $scanner,
        private readonly DataQualityIssueService $issueService,
        private readonly DataQualityResolutionService $resolutionService,
        private readonly DataQualityStatisticsService $statisticsService
    ) {}

    public function triggerScan(string $scanType = 'full', ?array $scope = null, ?User $user = null): DataQualityScan
    {
        return $this->scanner->runScan($scanType, $scope, $user);
    }

    public function getDashboard(User $user): array
    {
        return $this->statisticsService->getDashboardStatistics($user);
    }

    public function getIssues(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->issueService->getFilteredIssues($user, $filters, $perPage);
    }

    public function reviewIssue(DataQualityIssue $issue, User $user, ?string $notes = null): DataQualityIssue
    {
        return $this->resolutionService->review($issue, $user, $notes);
    }

    public function confirmIssue(DataQualityIssue $issue, User $user, ?string $notes = null): DataQualityIssue
    {
        return $this->resolutionService->confirm($issue, $user, $notes);
    }

    public function dismissIssue(DataQualityIssue $issue, User $user, string $reason): DataQualityIssue
    {
        return $this->resolutionService->dismiss($issue, $user, $reason);
    }

    public function resolveIssue(DataQualityIssue $issue, User $user, string $notes): DataQualityIssue
    {
        return $this->resolutionService->resolve($issue, $user, $notes);
    }
}
