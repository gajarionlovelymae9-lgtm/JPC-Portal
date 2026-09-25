<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFeedback>
 */
class UserFeedbackFactory extends Factory
{
    protected $model = UserFeedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => 'bug',
            'subject' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(2),
            'priority' => 'low',
            'status' => UserFeedback::STATUS_OPEN,
            'assigned_to' => null,
            'resolution' => null,
            'resolved_at' => null,
        ];
    }
}