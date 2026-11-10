<?php

namespace Database\Factories;

use App\Models\EmployeeTarget;
use App\Models\EmployeeTargetBreakdown;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTargetBreakdown>
 */
class EmployeeTargetBreakdownFactory extends Factory
{
    protected $model = EmployeeTargetBreakdown::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_target_id' => EmployeeTarget::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'target_value' => $this->faker->randomFloat(2, 1, 50),
            'target_unit' => 'units',
            'progress' => 0,
            'status' => EmployeeTargetBreakdown::STATUS_PENDING,
            'completed_at' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * A sub-target that has been fully completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'progress' => 100,
            'status' => EmployeeTargetBreakdown::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
