<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Realistic academic task titles so generated tasks read like real work.
     *
     * @var list<string>
     */
    private const TITLES = [
        'Prepare Monthly Faculty Report',
        'Submit Lesson Plan Documentation',
        'Prepare Student Progress Report',
        'Submit Faculty Development Report',
        'Prepare Academic Compliance Documents',
        'Submit Department Activity Report',
        'Prepare Monthly Attendance Summary',
        'Submit Curriculum Monitoring Report',
        'Prepare Student Intervention Documentation',
        'Submit End-of-Month Faculty Accomplishment Report',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_code' => 'TASK-'.$this->faker->unique()->numerify('####-####'),
            'annual_plan_id' => null,
            'department_plan_id' => null,
            'department_target_id' => null,
            'employee_target_id' => null,
            'parent_task_id' => null,
            'title' => $this->faker->randomElement(self::TITLES),
            'description' => 'Documentation task generated for development and testing of the planning module.',
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'draft',
            'start_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'completed_at' => null,
            'estimated_hours' => $this->faker->randomFloat(2, 1, 40),
            'required_output' => 'Completed documentation with supporting narrative.',
            'department_id' => Department::factory(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft', 'completed_at' => null]);
    }

    public function assigned(): static
    {
        return $this->state(fn (): array => ['status' => 'assigned', 'completed_at' => null]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => 'in_progress', 'completed_at' => null]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => 'submitted', 'completed_at' => null]);
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => ['status' => 'under_review', 'completed_at' => null]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'completed_at' => now()->subDay(),
            'due_date' => now()->addDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => 'rejected', 'completed_at' => null]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled', 'completed_at' => null]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => 'in_progress',
            'due_date' => now()->subDays(5),
            'completed_at' => null,
        ]);
    }
}
