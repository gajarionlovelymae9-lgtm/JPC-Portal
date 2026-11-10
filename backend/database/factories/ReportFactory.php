<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Report;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'employee_id' => Employee::factory(),
            'title' => $this->faker->sentence(5),
            'description' => 'Accomplishment report prepared for the assigned task.',
            'submission_date' => now()->subDays(2)->toDateString(),
            'deadline' => now()->addDays(5)->toDateString(),
            'status' => 'submitted',
            'is_late' => false,
            'version' => 1,
            'submitted_at' => now()->subDays(2),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft', 'submitted_at' => null]);
    }

    /**
     * Submitted and waiting for a human reviewer.
     */
    public function readyForReview(): static
    {
        return $this->state(fn (): array => ['status' => 'under_review', 'submitted_at' => now()->subDay()]);
    }

    public function revisionRequired(): static
    {
        return $this->state(fn (): array => ['status' => 'revision_required', 'submitted_at' => now()->subDay()]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'approved_at' => now()->subHour(),
            'submitted_at' => now()->subDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'rejected_at' => now()->subHour(),
            'submitted_at' => now()->subDay(),
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (): array => ['is_late' => true]);
    }

    public function version(int $number): static
    {
        return $this->state(fn (): array => ['version' => $number]);
    }
}
