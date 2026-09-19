<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskReference>
 */
class TaskReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => $this->faker->randomElement([
                'CHED Memorandum Order',
                'JPC Faculty Manual',
                'Department Reporting Guidelines',
            ]),
            'description' => $this->faker->sentence(),
            'reference_type' => $this->faker->randomElement(['document', 'link', 'policy']),
            'reference_url' => null,
            'file_path' => null,
        ];
    }
}
