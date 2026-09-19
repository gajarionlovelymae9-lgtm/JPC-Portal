<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => 'FCT-'.$this->faker->unique()->numberBetween(1000, 99999),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional(0.3)->lastName(),
            'last_name' => $this->faker->lastName(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'employment_status' => 'active',
            'employment_type' => 'regular',
            'date_hired' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'date_of_birth' => $this->faker->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'employment_status' => 'active',
            'date_ended' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'employment_status' => 'inactive',
            'date_ended' => $this->faker->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn (): array => [
            'employment_status' => 'on_leave',
            'date_ended' => null,
        ]);
    }

    public function regular(): static
    {
        return $this->state(fn (): array => [
            'employment_type' => 'regular',
        ]);
    }

    public function probationary(): static
    {
        return $this->state(fn (): array => [
            'employment_type' => 'probationary',
        ]);
    }
}
