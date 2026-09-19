<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_successful_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'JPC Portal API is running')
            ->assertJsonPath('data.application', 'ok')
            ->assertJsonPath('data.api', 'ok')
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'application',
                    'database',
                    'api',
                    'environment',
                    'api_version',
                    'timestamp',
                ],
            ]);
    }

    public function test_health_endpoint_reports_database_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.database', 'ok');
    }

    public function test_health_endpoint_rejects_wrong_method(): void
    {
        $this->postJson('/api/v1/health')
            ->assertStatus(405)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Method not allowed');
    }

    public function test_unknown_api_route_returns_standard_json_404(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Not found');
    }
}
