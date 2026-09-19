<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentHead>
 */
class DepartmentHeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'employee_id' => Employee::factory(),
            'start_date' => $this->faker->dateTimeBetween('-3 years', '-1 year')->format('Y-m-d'),
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => [
            'is_current' => true,
        ]);
    }
}
