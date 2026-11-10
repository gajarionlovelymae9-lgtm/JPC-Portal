<?php

namespace Database\Factories;

use App\Models\EmployeeTarget;
use App\Models\EmployeeTargetMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTargetMilestone>
 */
class EmployeeTargetMilestoneFactory extends Factory
{
    protected $model = EmployeeTargetMilestone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_target_id' => EmployeeTarget::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'threshold_percent' => $this->faker->randomElement([25, 50, 75]),
            'status' => EmployeeTargetMilestone::STATUS_PENDING,
            'achieved_at' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * A milestone checkpoint already reached.
     */
    public function achieved(): static
    {
        return $this->state(fn () => [
            'status' => EmployeeTargetMilestone::STATUS_ACHIEVED,
            'achieved_at' => now(),
        ]);
    }
}
