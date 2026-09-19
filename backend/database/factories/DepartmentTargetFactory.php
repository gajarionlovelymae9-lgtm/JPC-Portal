<?php

namespace Database\Factories;

use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentTarget>
 */
class DepartmentTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_plan_id' => DepartmentPlan::factory(),
            'department_id' => fn (array $attributes) => DepartmentPlan::query()
                ->findOrFail($attributes['department_plan_id'])->department_id,
            'target_name' => $this->faker->randomElement([
                'Monthly Faculty Report Completion',
                'Lesson Plan Submission',
                'Student Progress Documentation',
                'Faculty Development Activities',
                'Academic Compliance Documentation',
            ]),
            'target_description' => $this->faker->sentence(),
            'target_value' => $this->faker->randomFloat(2, 1, 100),
            'unit' => $this->faker->randomElement(['percent', 'reports', 'documents']),
            'start_date' => $this->faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'end_date' => $this->faker->dateTimeBetween('now', '+8 months')->format('Y-m-d'),
            'status' => 'active',
            'created_by' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => 'completed']);
    }
}
