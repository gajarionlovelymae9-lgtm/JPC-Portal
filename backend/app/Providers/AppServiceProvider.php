<?php

namespace App\Providers;

use App\Models\AiAnalysis;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Notifications\Channels\JpcDatabaseChannel;
use App\Policies\AiAnalysisPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ReportPolicy;
use App\Policies\TaskAssignmentPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskReviewPolicy;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\FakeAiProvider;
use App\Services\AiReports\Ai\HttpAiProvider;
use App\Services\AiReports\Ocr\Contracts\OcrProvider;
use App\Services\AiReports\Ocr\FakeOcrProvider;
use App\Services\AiReports\Ocr\GoogleVisionOcrService;
use App\Services\AI\AiToolRegistry;
use App\Services\AI\Tools\GetCriticalDataQualityIssuesTool;
use App\Services\AI\Tools\GetDataQualityIssuesTool;
use App\Services\AI\Tools\GetDataQualityStatisticsTool;
use App\Services\AI\Tools\GetDataQualityTrendsTool;
use App\Services\AI\Tools\GetDepartmentReportTool;
use App\Services\AI\Tools\GetEmployeeProgressReportTool;
use App\Services\AI\Tools\GetLateReportAnalyticsTool;
use App\Services\AI\Tools\GetManagementSummaryTool;
use App\Services\AI\Tools\GetMonthlyPerformanceReportTool;
use App\Services\AI\Tools\GetPerformanceTrendTool;
use App\Services\AI\Tools\GetRankingTool;
use App\Services\AI\Tools\GetTargetAnalyticsTool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip(),
            );
        });

        // Report browsing is a read-heavy, manager-only surface. Keys by the
        // authenticated user so each account gets its own independent bucket.
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(120)->by(
                (string) ($request->user()?->id ?? $request->ip()),
            );
        });

        // Exports rebuild full datasets and stream files: tighter window, and
        // the key doubles as the audit identity for the export log entries.
        RateLimiter::for('reports-export', function (Request $request) {
            return Limit::perMinute(10)->by(
                (string) ($request->user()?->id ?? $request->ip()),
            );
        });

        // Custom "jpc_database" notification channel writing into the JPC
        // notifications inbox instead of Laravel's morph/UUID database schema.
        NotificationFacade::extend('jpc_database', fn (): JpcDatabaseChannel => new JpcDatabaseChannel);

        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TaskAssignment::class, TaskAssignmentPolicy::class);
        Gate::policy(TaskReview::class, TaskReviewPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(AiAnalysis::class, AiAnalysisPolicy::class);
        app()->bind(AiProvider::class, FakeAiProvider::class);

        // OCR provider is chosen from configuration (fake by default so the
        // whole pipeline runs offline; google_vision once credentials exist).
        app()->bind(OcrProvider::class, function (): OcrProvider {
            $provider = (string) config('ocr.ocr.provider', 'fake');

            return $provider === 'google_vision'
                ? app(GoogleVisionOcrService::class)
                : app(FakeOcrProvider::class);
        });

        $this->registerAiTools();
    }

    /**
     * Bind a singleton AiToolRegistry and register every tool implementation.
     * Tools are resolved from the container so their service dependencies
     * (ReportingService, AnalyticsService, ManagementSummaryService, DQ
     * statistics) are injected automatically.
     */
    private function registerAiTools(): void
    {
        $this->app->singleton(AiToolRegistry::class);

        $tools = [
            GetMonthlyPerformanceReportTool::class,
            GetEmployeeProgressReportTool::class,
            GetDepartmentReportTool::class,
            GetRankingTool::class,
            GetPerformanceTrendTool::class,
            GetLateReportAnalyticsTool::class,
            GetTargetAnalyticsTool::class,
            GetManagementSummaryTool::class,
            GetDataQualityStatisticsTool::class,
            GetDataQualityTrendsTool::class,
            GetCriticalDataQualityIssuesTool::class,
            GetDataQualityIssuesTool::class,
        ];

        $this->app->resolving(AiToolRegistry::class, function (AiToolRegistry $registry) use ($tools) {
            foreach ($tools as $tool) {
                $registry->register($this->app->make($tool));
            }
        });
    }
}
