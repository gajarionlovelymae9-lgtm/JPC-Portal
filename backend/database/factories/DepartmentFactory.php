<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Department',
            'code' => 'DEPT-'.$this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }
}
