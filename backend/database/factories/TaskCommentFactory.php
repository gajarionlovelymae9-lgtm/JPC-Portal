<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
class TaskCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'task_assignment_id' => null,
            'user_id' => User::factory(),
            'comment' => $this->faker->randomElement([
                'Initial progress submitted.',
                'Supporting document uploaded.',
                'Please complete the missing section.',
                'Reviewed by department head.',
                'Revision completed.',
            ]),
        ];
    }
}
