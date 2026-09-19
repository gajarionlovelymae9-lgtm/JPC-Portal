<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportReference>
 */
class ReportReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'task_reference_id' => null,
            'reference_text' => $this->faker->sentence(),
            'reference_url' => null,
        ];
    }

    public function withUrl(): static
    {
        return $this->state(fn (): array => ['reference_url' => $this->faker->url()]);
    }
}