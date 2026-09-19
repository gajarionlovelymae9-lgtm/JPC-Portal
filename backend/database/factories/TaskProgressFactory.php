<?php

namespace Database\Factories;

use App\Models\TaskAssignment;
use App\Models\TaskProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskProgress>
 */
class TaskProgressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $previous = $this->faker->randomElement([0, 25, 50, 75]);
        $new = min(100, $previous + 25);

        return [
            'task_assignment_id' => TaskAssignment::factory(),
            'task_id' => fn (array $attributes) => TaskAssignment::query()
                ->findOrFail($attributes['task_assignment_id'])->task_id,
            'employee_id' => fn (array $attributes) => TaskAssignment::query()
                ->findOrFail($attributes['task_assignment_id'])->employee_id,
            'previous_percentage' => $previous,
            'new_percentage' => $new,
            'status' => 'in_progress',
            'comment' => $this->faker->randomElement([
                'Initial progress submitted.',
                'Supporting document uploaded.',
                'Draft section completed and reviewed internally.',
                'Pending final proofreading before submission.',
            ]),
            'updated_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'previous_percentage' => 75,
            'new_percentage' => 100,
            'status' => 'completed',
        ]);
    }
}
