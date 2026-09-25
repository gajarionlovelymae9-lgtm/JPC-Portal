<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\PerformancePeriod;
use App\Models\User;
use App\Services\Performance\PerformanceSnapshotService;
use App\Services\Reporting\ReportingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * reports:generate-monthly — Phase 9 scheduled monthly processing (spec item
 * 57).
 *
 * Finalizes the (default: previous) month through the authoritative Phase 6
 * pipeline (PerformanceSnapshotService::calculateMonthlyPeriod → snapshots +
 * achievements/rankings), warms the management reports for admins/hr and
 * notifies leadership that the monthly report is available.
 *
 * Schedule (routes/console.php): first day of the month at 06:00 the report
 * covers the just-finished month.
 */
class GenerateMonthlyReport extends Command
{
    protected $signature = 'reports:generate-monthly {--year=} {--month=} {--force : Recalculate the period even if already calculated}';

    protected $description = 'Finalize the monthly performance period, generate management reports and notify admin/HR';

    public function handle(PerformanceSnapshotService $snapshotService, ReportingService $reportingService): int
    {
        $previous = Carbon::now()->subMonthNoOverflow();
        $year = (int) ($this->option('year') !== null && $this->option('year') !== '' ? $this->option('year') : $previous->year);
        $month = (int) ($this->option('month') !== null && $this->option('month') !== '' ? $this->option('month') : $previous->month);

        $period = PerformancePeriod::findOrCreateMonthly($year, $month);

        $result = null;
        if ($period->calculated_at !== null && ! $this->option('force')) {
            $this->line("Period {$period->name} already calculated at {$period->calculated_at->toDateTimeString()} — skipping recalculation.");
        } else {
            $result = $snapshotService->calculateMonthlyPeriod($year, $month);
            $this->info("Calculated {$result['processed_employees']} employee snapshot(s) for {$period->name}.");
        }

        if ($period->finalized_at === null) {
            $period->forceFill([
                'finalized_at' => now(),
                'status' => 'finalized',
            ])->save();
            $this->info("Period {$period->name} marked finalized.");
        }

        $admins = User::query()->get()->filter(fn (User $user) => in_array($user->roleName(), ['admin', 'hr'], true));

        if ($admins->isNotEmpty()) {
            $this->warmReports($reportingService, $year, $month, $admins);
            $this->notifyLeadership($year, $month, $period, $admins);
        } else {
            $this->warn('No admin/hr users found — skipped report warm-up and notifications.');
        }

        return self::SUCCESS;
    }

    /**
     * Pre-generate (and cache) the management reports as the leader role so
     * the scheduled day's dashboard loads instantly.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $admins
     */
    private function warmReports(ReportingService $reportingService, int $year, int $month, $admins): void
    {
        $actor = $admins->first(fn (User $user) => $user->roleName() === 'admin') ?? $admins->first();

        try {
            $reportingService->generateMonthlyReport($year, $month, $actor);
            $reportingService->generateInstitutionReport(['year' => $year, 'month' => $month], $actor);
            $reportingService->generateAchievementReport(['year' => $year, 'month' => $month], $actor);
        } catch (\Throwable $e) {
            $this->warn('Report warm-up failed: '.$e->getMessage());
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $admins
     */
    private function notifyLeadership(int $year, int $month, PerformancePeriod $period, $admins): void
    {
        $label = Carbon::createFromDate($year, $month, 1)->format('F Y');

        foreach ($admins as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'monthly_report',
                'title' => "Monthly Performance Report — {$label}",
                'message' => "The monthly performance report for {$label} has been generated. Review the report to see snapshots, achievements, rankings and data quality for the period.",
                'data' => [
                    'year' => $year,
                    'month' => $month,
                    'period_name' => $period->name,
                    'url' => "/reporting/monthly?year={$year}&month={$month}",
                ],
                'priority' => 'high',
            ]);
        }

        $this->info("Notified {$admins->count()} admin/hr user(s) about the {$label} monthly report.");
    }
}