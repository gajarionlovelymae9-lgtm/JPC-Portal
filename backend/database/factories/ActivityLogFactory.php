<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'activity_type' => 'task.created',
            'description' => 'task.created',
            'subject_type' => Task::class,
            'subject_id' => Task::factory(),
            'metadata' => [],
            'ip_address' => null,
            'user_agent' => null,
        ];
    }

    public function login(): static
    {
        return $this->state(fn (): array => [
            'activity_type' => 'auth.login',
            'description' => 'Signed in to the portal.',
            'subject_type' => null,
            'subject_id' => null,
            'metadata' => ['method' => 'password'],
        ]);
    }
}