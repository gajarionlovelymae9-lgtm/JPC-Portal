<?php

namespace Database\Factories;

use App\Models\OcrProcessing;
use App\Models\ReportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OcrProcessing>
 */
class OcrProcessingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_file_id' => ReportFile::factory(),
            'provider' => 'fake',
            'status' => 'completed',
            'language' => 'en',
            'raw_text' => 'Sample extracted text describing the report accomplishments and recommendations.',
            'confidence_score' => 0.91,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'error_message' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => 'completed', 'completed_at' => now()]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => ['status' => 'processing', 'started_at' => now(), 'completed_at' => null]);
    }

    /**
     * @param  string  $text  extracted text used by the downstream AI analysis
     */
    public function withText(string $text, ?float $confidence = null): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'raw_text' => $text,
            'confidence_score' => $confidence ?? 0.9,
            'completed_at' => now(),
        ]);
    }

    public function failed(string $message): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}