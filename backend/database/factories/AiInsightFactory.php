<?php

namespace Database\Factories;

use App\Enums\AiInsightType;
use App\Models\AiInsight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiInsight>
 */
class AiInsightFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => null,
            'department_id' => null,
            'type' => AiInsightType::PERFORMANCE_INSIGHT,
            'content' => $this->faker->sentence(),
            'metadata' => [],
            'generated_at' => now(),
        ];
    }

    public function report(): static
    {
        return $this->state(fn (): array => ['type' => AiInsightType::REPORT_INSIGHT]);
    }

    public function trend(): static
    {
        return $this->state(fn (): array => ['type' => AiInsightType::TREND_INSIGHT]);
    }

    public function anomaly(): static
    {
        return $this->state(fn (): array => ['type' => AiInsightType::ANOMALY_INSIGHT]);
    }
}
