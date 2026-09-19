<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportVersion>
 */
class ReportVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'version_number' => 1,
            'submitted_by' => User::factory(),
            'file_path' => null,
            'notes' => null,
            'submitted_at' => now(),
        ];
    }

    public function version(int $number): static
    {
        return $this->state(fn (): array => ['version_number' => $number]);
    }

    public function withFile(): static
    {
        $name = Str::random(40);

        return $this->state(fn (): array => [
            'file_path' => 'reports/'.$name.'.pdf',
        ]);
    }
}