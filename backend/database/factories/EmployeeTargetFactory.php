<?php

namespace Database\Factories;

use App\Models\AnnualPlan;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTarget>
 */
class EmployeeTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'annual_plan_id' => AnnualPlan::factory(),
            'employee_id' => Employee::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'target_value' => $this->faker->randomFloat(2, 1, 100),
            'target_unit' => $this->faker->randomElement(['reports', 'documents', 'percent', 'units']),
            'start_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'end_date' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'status' => 'active',
        ];
    }
}
