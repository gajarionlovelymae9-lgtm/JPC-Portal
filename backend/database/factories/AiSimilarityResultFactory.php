<?php

namespace Database\Factories;

use App\Models\AiSimilarityResult;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSimilarityResult>
 */
class AiSimilarityResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'compared_report_id' => Report::factory(),
            'similarity_score' => 0.05,
            'status' => 'normal',
            'explanation' => 'Similarity is below the warning threshold.',
        ];
    }

    public function similar(float $score = 0.85): static
    {
        return $this->state(fn (): array => [
            'similarity_score' => $score,
            'status' => 'similar',
            'explanation' => 'Similarity exceeds the advisory threshold.',
        ]);
    }
}