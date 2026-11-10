<?php

namespace Tests\Feature\Database;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 18 — Route surface regression guard.
 *
 * The portal routes are the contract the API client depends on. A route that
 * is accidentally removed or renamed breaks every consumer silently, so this
 * test locks the full v1 surface for the workflows covered in the manual QA
 * passes (auth, tasks, reports, revisions, reviews, notifications, profile,
 * AI assistant, analytics, exports, employee portal and department head).
 */
class RouteSurfaceTest extends TestCase
{
    /** @var array<string, list<string>> */
    private const EXPECTED = [
        'auth' => [
            'api/v1/auth/login', 'api/v1/auth/logout', 'api/v1/auth/me',
        ],
        'employee portal' => [
            'api/v1/employee/dashboard',
            'api/v1/employee/reports',
            'api/v1/employee/reports/{report}',
            'api/v1/employee/reports/{report}/submit',
            'api/v1/employee/reports/{report}/revision',
            'api/v1/employee/tasks',
            'api/v1/employee/tasks/{taskAssignment}',
            'api/v1/employee/tasks/{taskAssignment}/progress',
            'api/v1/employee/tasks/{taskAssignment}/submit',
            'api/v1/employee/targets',
            'api/v1/employee/calendar',
            'api/v1/employee/notifications',
            'api/v1/employee/profile',
            'api/v1/employee/security/password',
            'api/v1/employee/performance',
            'api/v1/employee/achievements',
        ],
        'report lifecycle' => [
            'api/v1/reports',
            'api/v1/reports/{report}',
            'api/v1/reports/{report}/submit',
            'api/v1/reports/{report}/revision',
            'api/v1/reports/{report}/approve',
            'api/v1/reports/{report}/reject',
            'api/v1/reports/{report}/request-revision',
            'api/v1/reports/{report}/archive',
            'api/v1/reports/{report}/cancel',
            'api/v1/reports/{report}/timeline',
            'api/v1/reports/{report}/versions',
            'api/v1/reports/{report}/versions/{versionNumber}',
        ],
        'AI assistant' => [
            'api/v1/ai/chat',
            'api/v1/ai/conversations',
            'api/v1/ai/conversations/{conversation}',
            'api/v1/ai/insights',
            'api/v1/ai/insights/performance',
            'api/v1/ai/insights/reports',
            'api/v1/ai/reports/{report}/summarize',
        ],
        'AI processing pipeline' => [
            'api/v1/ai-processing/{report}',
            'api/v1/ai-processing/{report}/ocr',
            'api/v1/ai-processing/{report}/process',
            'api/v1/ai-processing/{report}/analyze',
            'api/v1/ai-processing/{report}/ai-summary',
            'api/v1/ai-processing/{report}/ai-validation',
            'api/v1/ai-processing/{report}/review',
            'api/v1/ai-processing/{report}/documents',
            'api/v1/ai-processing/{report}/processing-status',
        ],
        'reporting & analytics' => [
            'api/v1/reports/management-summary',
            'api/v1/reports/late',
            'api/v1/reports/pending',
            'api/v1/reports/top-performers',
            'api/v1/reports/overview',
            'api/v1/reports/departments',
            'api/v1/reports/employees/{employee}',
            'api/v1/analytics/completion-trend',
            'api/v1/analytics/performance-distribution',
            'api/v1/analytics/rankings',
        ],
        'exports' => [
            'api/v1/reports/exports',
            'api/v1/reports/exports/{export}',
            'api/v1/reports/exports/{export}/download',
        ],
        'notifications' => [
            'api/v1/notifications',
            'api/v1/notifications/{notification}',
            'api/v1/notifications/unread-count',
            'api/v1/notifications/read-all',
            'api/v1/notifications/preferences',
        ],
        'department head' => [
            'api/v1/department-head/dashboard',
            'api/v1/department-head/reports',
            'api/v1/department-head/reports/{report}',
            'api/v1/department-head/reports/{report}/review',
            'api/v1/department-head/employees',
            'api/v1/department-head/tasks',
            'api/v1/department-head/tasks/{task}',
            'api/v1/department-head/tasks/{task}/assign',
        ],
        'admin' => [
            'api/v1/admin/rbac',
            'api/v1/admin/departments',
            'api/v1/admin/reports',
            'api/v1/admin/reports/{report}',
            'api/v1/admin/reports/{report}/override',
            'api/v1/admin/system-health',
            'api/v1/admin/system-health/queue',
            'api/v1/admin/system-health/ocr',
            'api/v1/admin/executive-dashboard',
            'api/v1/admin/ai/ocr',
            'api/v1/admin/ai/retry/{job}',
        ],
    ];

    #[Test]
    public function all_expected_v1_routes_are_registered(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $registered = collect($router->getRoutes()->getRoutes())
            ->map(fn (Route $route) => $route->uri)
            ->all();

        $missing = [];

        foreach (self::EXPECTED as $group => $uris) {
            foreach ($uris as $uri) {
                if (! in_array($uri, $registered, true)) {
                    $missing[] = "[{$group}] {$uri}";
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Missing API routes:'.PHP_EOL.implode(PHP_EOL, $missing),
        );
    }

    #[Test]
    public function every_expected_route_has_a_name(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri, 'api/v1/')) {
                $this->assertNotNull(
                    $route->getName(),
                    "API route [{$route->uri}] has no name; named routes are the portal contract.",
                );
            }
        }
    }
}
