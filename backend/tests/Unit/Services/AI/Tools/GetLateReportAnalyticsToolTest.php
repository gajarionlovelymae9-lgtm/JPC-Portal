<?php

namespace Tests\Unit\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\Tools\GetLateReportAnalyticsTool;
use App\Services\Reporting\ReportingService;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for the GetLateReportAnalyticsTool (Phase 9 reporting tool).
 */
class GetLateReportAnalyticsToolTest extends TestCase
{
    private function toolWith(callable $generateLateReport): GetLateReportAnalyticsTool
    {
        $service = $this->mock(ReportingService::class, function (MockInterface $mock) use ($generateLateReport) {
            $mock->shouldReceive('generateLateReport')
                ->andReturnUsing($generateLateReport);
        });

        return new GetLateReportAnalyticsTool($service);
    }

    public function test_tool_exposes_its_identity_and_permission(): void
    {
        $tool = $this->toolWith(fn () => []);

        $this->assertSame('GetLateReportAnalyticsTool', $tool->name());
        $this->assertSame('analytics.view', $tool->requiredPermission());
        $this->assertIsArray($tool->parameters());
    }

    public function test_execute_trims_large_by_employee_lists_to_a_bounded_slice(): void
    {
        $captured = null;
        $payload = [
            'period' => '2026-09',
            'summary' => ['total_late' => 25],
            'comparison' => ['diff' => 5],
            'by_employee' => array_map(fn (int $i) => ['employee_id' => $i], range(1, 40)),
            'by_department' => [],
            'has_data' => true,
        ];

        $tool = $this->toolWith(function (array $filters) use (&$captured, $payload) {
            $captured = $filters;

            return $payload;
        });

        $result = $tool->execute(['year' => 2026, 'month' => 9], new User);

        $this->assertSame(2026, $captured['year']);
        $this->assertSame(9, $captured['month']);
        $this->assertCount(10, $result['by_employee']);
        $this->assertSame(25, $result['summary']['total_late']);
        $this->assertTrue($result['has_data']);
    }

    public function test_execute_marks_has_data_false_when_service_returns_empty(): void
    {
        $tool = $this->toolWith(fn () => [
            'period' => '2026-09',
            'summary' => [],
            'comparison' => [],
            'by_employee' => [],
            'by_department' => [],
            'has_data' => false,
        ]);

        $result = $tool->execute(['year' => 2026, 'month' => 9], new User);

        $this->assertFalse($result['has_data']);
        $this->assertSame([], $result['by_employee']);
    }
}
