<?php

namespace Tests\Unit\Services\AI;

use App\Enums\AiIntent;
use App\Services\AI\AiIntentDetector;
use Tests\TestCase;

class AiIntentDetectorTest extends TestCase
{
    private AiIntentDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new AiIntentDetector;
    }

    public function test_detects_late_reports_intent_from_keywords(): void
    {
        $this->assertSame(
            AiIntent::LATE_REPORTS->value,
            $this->detector->detect('How many late reports do we have this month?')['intent'],
        );
    }

    public function test_detects_employee_performance_intent(): void
    {
        $this->assertSame(
            AiIntent::EMPLOYEE_PERFORMANCE->value,
            $this->detector->detect('Show my progress report for January 2025')['intent'],
        );
    }

    public function test_detects_department_performance_intent(): void
    {
        $this->assertSame(
            AiIntent::DEPARTMENT_PERFORMANCE->value,
            $this->detector->detect('department performance for march')['intent'],
        );
    }

    public function test_detects_management_summary_intent(): void
    {
        $this->assertSame(
            AiIntent::MANAGEMENT_SUMMARY->value,
            $this->detector->detect('Give me the management summary')['intent'],
        );
    }

    public function test_unknown_message_falls_back_to_default_intent(): void
    {
        $result = $this->detector->detect('Hello there, how are you?');

        $this->assertSame('DEFAULT', $result['intent']);
        $this->assertArrayHasKey('filters', $result);
    }

    public function test_detected_filters_default_to_current_period(): void
    {
        $result = $this->detector->detect('Show progress for all employees');

        $this->assertSame(now()->year, $result['filters']['year']);
        $this->assertSame(now()->month, $result['filters']['month']);
    }

    public function test_month_keyword_overrides_default_month_filter(): void
    {
        $result = $this->detector->detect('Employee performance for march 2025');

        $this->assertSame(2025, $result['filters']['year']);
        $this->assertSame(3, $result['filters']['month']);
    }

    public function test_abbreviated_month_names_are_recognised(): void
    {
        $result = $this->detector->detect('Late reports in feb');

        $this->assertSame(2, $result['filters']['month']);
    }

    public function test_last_n_months_range_is_extracted(): void
    {
        $result = $this->detector->detect('Data quality trend over the last 3 months');

        $this->assertSame(3, $result['filters']['months']);
    }

    public function test_extract_limit_reads_top_n(): void
    {
        $this->assertSame(7, $this->detector->extractLimit('show top 7 performers'));
    }

    public function test_extract_limit_falls_back_to_configured_default(): void
    {
        $limit = config('reports.top_performers_limit', 5);
        $this->assertSame((int) $limit, $this->detector->extractLimit('show the leaderboard'));
    }

    public function test_data_quality_keyword_resolves_to_statistics_first(): void
    {
        $this->assertSame(
            AiIntent::DATA_QUALITY_STATISTICS->value,
            $this->detector->detect('What are the data quality statistics?')['intent'],
        );
    }

    public function test_target_achievement_is_detected_over_generic_target(): void
    {
        $this->assertSame(
            AiIntent::TARGET_ACHIEVEMENT->value,
            $this->detector->detect('target achievement report')['intent'],
        );
    }
}
