<?php

namespace Database\Factories;

use App\Models\AiAnalysis;
use App\Models\AiValidationResult;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiValidationResult>
 */
class AiValidationResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'ai_analysis_id' => AiAnalysis::factory(),
            'task_alignment_score' => 0.9,
            'reference_alignment_score' => 0.85,
            'reference_anchor_score' => 0.7,
            'requirement_match' => 'matched',
            'completeness_score' => 0.88,
            'relevance_score' => 0.92,
            'evidence_score' => 0.8,
            'clarity_score' => 0.9,
            'overall_score' => 0.9,
            'confidence_score' => 0.9,
            'result' => 'pass',
            'explanation' => 'The report aligns with the assigned task and includes supporting evidence.',
        ];
    }

    /**
     * @param  list<float>  $scores  [task, reference, reference_anchor, completeness, relevance, evidence, clarity, overall]
     */
    public function withScores(array $scores, string $result = 'pass'): static
    {
        return $this->state(fn (): array => [
            'task_alignment_score' => $scores[0] ?? 0.9,
            'reference_alignment_score' => $scores[1] ?? 0.85,
            'reference_anchor_score' => $scores[2] ?? 0.7,
            'completeness_score' => $scores[3] ?? 0.88,
            'relevance_score' => $scores[4] ?? 0.92,
            'evidence_score' => $scores[5] ?? 0.8,
            'clarity_score' => $scores[6] ?? 0.9,
            'overall_score' => $scores[7] ?? 0.9,
            'result' => $result,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['result' => 'fail', 'overall_score' => 0.4]);
    }
}