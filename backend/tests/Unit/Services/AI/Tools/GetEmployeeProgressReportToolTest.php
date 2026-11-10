<?php

namespace Tests\Unit\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\Tools\GetEmployeeProgressReportTool;
use App\Services\Reporting\ReportingService;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for the GetEmployeeProgressReportTool (Phase 9 reporting tool).
 */
class GetEmployeeProgressReportToolTest extends TestCase
{
    private function toolWith(callable $generateEmployeeReport): GetEmployeeProgressReportTool
    {
        $service = $this->mock(ReportingService::class, function (MockInterface $mock) use ($generateEmployeeReport) {
            $mock->shouldReceive('generateEmployeeReport')
                ->andReturnUsing($generateEmployeeReport);
        });

        return new GetEmployeeProgressReportTool($service);
    }

    public function test_tool_exposes_its_identity_and_permission(): void
    {
        $tool = $this->toolWith(fn () => []);

        $this->assertSame('GetEmployeeProgressReportTool', $tool->name());
        $this->assertSame('reports.view', $tool->requiredPermission());
        $this->assertIsArray($tool->parameters());
    }

    public function test_execute_delegates_with_explicit_employee_and_period(): void
    {
        $captured = null;
        $payload = [
            'period' => '2026-09',
            'employee' => ['id' => 11, 'name' => 'Jane'],
            'metrics' => ['completion_rate' => 75.0],
            'source' => 'employee-progress',
            'has_data' => true,
        ];

        $tool = $this->toolWith(function (int $employeeId, array $filters) use (&$captured, $payload) {
            $captured = ['employee_id' => $employeeId, 'filters' => $filters];

            return $payload;
        });

        $result = $tool->execute(['employee_id' => 11, 'year' => 2026, 'month' => 9], new User);

        $this->assertSame(11, $captured['employee_id']);
        $this->assertSame(2026, $captured['filters']['year']);
        $this->assertSame(9, $captured['filters']['month']);
        $this->assertSame('employee-progress', $result['source']);
        $this->assertTrue($result['has_data']);
    }

    public function test_execute_defaults_employee_to_caller_id_when_not_supplied(): void
    {
        // An unsaved caller has no employee relation → employee_id resolves to 0.
        $captured = null;
        $payload = [
            'period' => '2026-09',
            'employee' => ['id' => 0, 'name' => 'Unknown'],
            'metrics' => [],
            'source' => 'employee-progress',
            'has_data' => false,
        ];

        $tool = $this->toolWith(function (int $employeeId, array $filters) use (&$captured, $payload) {
            $captured = ['employee_id' => $employeeId, 'filters' => $filters];

            return $payload;
        });

        $result = $tool->execute(['year' => 2026, 'month' => 9], new User);

        $this->assertSame(0, $captured['employee_id']);
        $this->assertArrayHasKey('metrics', $result);
    }
}
