<?php

namespace Database\Factories;

use App\Models\AiAnalysis;
use App\Models\AiSummary;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSummary>
 */
class AiSummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'ai_analysis_id' => AiAnalysis::factory(),
            'summary' => 'The report describes the period deliverables, key accomplishments and next steps.',
            'key_accomplishments' => ['Delivered the required outputs', 'Documented measurable outcomes'],
            'key_findings' => ['All major deliverables were completed'],
            'issues' => [],
            'recommendations' => ['Use the standard template next cycle'],
            'action_items' => ['Apply template improvements'],
            'generated_at' => now(),
        ];
    }
}