<?php

namespace Database\Factories;

use App\Models\AiAnalysis;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiAnalysis>
 */
class AiAnalysisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'report_version_id' => null,
            'analysis_type' => 'validation',
            'provider' => 'fake',
            'model' => 'jpc-analysis-v1',
            'analysis_version' => 'v1.0',
            'status' => 'completed',
            'input_hash' => hash('sha256', Str::random(16)),
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'error_message' => null,
        ];
    }

    public function summarization(): static
    {
        return $this->state(fn (): array => ['analysis_type' => 'summarization']);
    }

    public function validation(): static
    {
        return $this->state(fn (): array => ['analysis_type' => 'validation']);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => 'completed', 'completed_at' => now()]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => ['status' => 'processing', 'started_at' => now(), 'completed_at' => null]);
    }

    public function failed(string $message): static
    {
        return $this->state(fn (): array => ['status' => 'failed', 'error_message' => $message, 'completed_at' => now()]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => 'pending', 'started_at' => null, 'completed_at' => null]);
    }
}