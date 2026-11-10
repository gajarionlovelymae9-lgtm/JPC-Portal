<?php

namespace Tests\Unit\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\Tools\GetDepartmentReportTool;
use App\Services\Reporting\ReportingService;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for the GetDepartmentReportTool (Phase 9 reporting tool).
 */
class GetDepartmentReportToolTest extends TestCase
{
    private function toolWith(
        callable $generateDepartmentReport,
        array $say = [],
    ): array {
        $service = $this->mock(ReportingService::class, function (MockInterface $mock) use ($generateDepartmentReport) {
            $mock->shouldReceive('generateDepartmentReport')
                ->andReturnUsing($generateDepartmentReport);
        });

        $tool = new GetDepartmentReportTool($service);

        return [$tool, $say];
    }

    public function test_tool_exposes_its_identity_and_permission(): void
    {
        $service = $this->createMock(ReportingService::class);
        $tool = new GetDepartmentReportTool($service);

        $this->assertSame('GetDepartmentReportTool', $tool->name());
        $this->assertSame('analytics.view', $tool->requiredPermission());
        $this->assertIsArray($tool->parameters());
        $this->assertNotEmpty($tool->description());
    }

    public function test_execute_delegates_to_reporting_service_and_shapes_result(): void
    {
        $user = new User;
        $payload = [
            'period' => '2026-09',
            'department' => ['id' => 3, 'name' => 'College'],
            'metrics' => ['completion_rate' => 100.0],
            'source' => 'department-report',
            'has_data' => true,
        ];

        [$tool] = $this->toolWith(fn (int $departmentId, array $filters, User $caller) => $payload);

        $result = $tool->execute(['department_id' => 3, 'year' => 2026, 'month' => 9], $user);

        $this->assertSame('GetDepartmentReportTool', $result['tool_name']);
        $this->assertSame('2026-09', $result['period']);
        $this->assertSame(3, $result['department']['id']);
        $this->assertSame(100.0, $result['metrics']['completion_rate']);
        $this->assertTrue($result['has_data']);
    }

    public function test_execute_passes_period_filters_through_to_reporting_service(): void
    {
        $user = new User;
        $captured = null;
        $payload = [
            'period' => '2025-03',
            'department' => ['id' => 7, 'name' => 'Engineering'],
            'metrics' => ['completion_rate' => 40.0],
            'source' => 'department-report',
            'has_data' => false,
        ];

        [$tool] = $this->toolWith(function (int $departmentId, array $filters) use (&$captured, $payload) {
            $captured = ['department_id' => $departmentId, 'filters' => $filters];

            return $payload;
        });

        $result = $tool->execute(['department_id' => 7, 'year' => 2025, 'month' => 3], $user);

        $this->assertSame(7, $captured['department_id']);
        $this->assertSame(2025, $captured['filters']['year']);
        $this->assertSame(3, $captured['filters']['month']);
        $this->assertSame('2025-03', $result['period']);
    }
}
