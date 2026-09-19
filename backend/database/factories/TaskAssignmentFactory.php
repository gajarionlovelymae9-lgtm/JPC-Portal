<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'employee_id' => Employee::factory(),
            'assigned_by' => User::factory(),
            'assigned_at' => now()->subDays(3),
            'start_date' => now()->subDays(3),
            'due_date' => now()->addDays(14),
            'status' => 'assigned',
            'progress_percentage' => 0,
            'completed_at' => null,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (): array => ['status' => 'assigned', 'progress_percentage' => 0, 'completed_at' => null]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => 'in_progress', 'progress_percentage' => 50, 'completed_at' => null]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => 'submitted', 'progress_percentage' => 100, 'completed_at' => null]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'progress_percentage' => 100,
            'completed_at' => now()->subDay(),
            'due_date' => now()->addDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => 'rejected', 'progress_percentage' => 100, 'completed_at' => null]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => 'in_progress',
            'progress_percentage' => 40,
            'due_date' => now()->subDays(4),
            'completed_at' => null,
        ]);
    }
}
