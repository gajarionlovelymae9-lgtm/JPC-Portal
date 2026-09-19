<?php

namespace Database\Factories;

use App\Models\AnnualPlan;
use App\Models\DepartmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentPlan>
 */
class DepartmentPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'annual_plan_id' => AnnualPlan::factory(),
            'department_id' => fn (array $attributes) => AnnualPlan::query()
                ->findOrFail($attributes['annual_plan_id'])->department_id,
            'title' => $this->faker->randomElement([
                'Faculty Documentation and Performance Monitoring',
                'Departmental Reporting and Compliance Plan',
                'Instructional Delivery and Documentation Plan',
            ]),
            'description' => $this->faker->paragraph(),
            'status' => 'draft',
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => 'active',
        ]);
    }
}
