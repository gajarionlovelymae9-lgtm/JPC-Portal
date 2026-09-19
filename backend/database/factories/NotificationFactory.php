<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'system',
            'title' => 'System notice',
            'message' => 'This is a test notification.',
            'data' => [],
            'priority' => 'normal',
            'task_id' => null,
            'reminder_days' => null,
            'read_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (): array => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()->subHour()]);
    }

    public function taskAssigned(): static
    {
        return $this->state(fn (): array => ['type' => 'task_assigned', 'priority' => 'normal']);
    }

    public function deadlineReminder(): static
    {
        return $this->state(fn (): array => [
            'type' => 'deadline_reminder',
            'priority' => 'important',
            'reminder_days' => 1,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (): array => ['priority' => 'urgent']);
    }
}