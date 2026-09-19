<?php

namespace Tests\Unit;

use Illuminate\Foundation\Application;
use Tests\TestCase;

class ApplicationFoundationTest extends TestCase
{
    public function test_application_is_laravel_and_boots(): void
    {
        $this->assertInstanceOf(Application::class, $this->app);
        $this->assertTrue($this->app->isBooted());
    }

    public function test_health_api_route_is_registered(): void
    {
        $routes = $this->app->make('router')->getRoutes();

        $healthRoute = collect($routes)->first(fn ($route) => $route->uri() === 'api/v1/health');

        $this->assertNotNull($healthRoute);
        $this->assertSame(['GET', 'HEAD'], $healthRoute->methods());
        $this->assertSame('api.v1.health', $healthRoute->getName());
    }

    public function test_default_log_channel_is_configured(): void
    {
        $this->assertSame(
            config('logging.default'),
            $this->app->make('config')->get('logging.default'),
        );

        $this->assertNotEmpty(config('logging.channels'));
    }
}
