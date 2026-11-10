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
            // Department names are unique in the schema, so the factory must
            // not reuse a random company name within the same test.
            'name' => $this->faker->unique()->company().' Department',
            'code' => 'DEPT-'.$this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }
}
