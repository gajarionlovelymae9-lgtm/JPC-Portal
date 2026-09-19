<?php

namespace Database\Factories;

use App\Models\TaskAssignment;
use App\Models\TaskReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskReview>
 */
class TaskReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_assignment_id' => TaskAssignment::factory(),
            'task_id' => fn (array $attributes) => TaskAssignment::query()
                ->findOrFail($attributes['task_assignment_id'])->task_id,
            'reviewed_by' => User::factory(),
            'decision' => $this->faker->randomElement(['approved', 'rejected', 'needs_revision']),
            'remarks' => $this->faker->randomElement([
                'Documentation is complete and consistent with the required output.',
                'Documentation is incomplete. Please revise and resubmit.',
                'Additional supporting document required.',
            ]),
            'reviewed_at' => now()->subDay(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'decision' => 'approved',
            'remarks' => 'Documentation is complete and consistent with the required output.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'decision' => 'rejected',
            'remarks' => 'Documentation is incomplete. Please revise and resubmit.',
        ]);
    }

    public function needsRevision(): static
    {
        return $this->state(fn (): array => [
            'decision' => 'needs_revision',
            'remarks' => 'Additional supporting document required.',
        ]);
    }
}
