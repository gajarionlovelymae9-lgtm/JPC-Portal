<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // positions.name is uniquely indexed, so the job title must be
            // unique as well: faker's jobTitle() pool repeats within a test.
            'name' => $this->faker->unique()->jobTitle(),
            'code' => 'POS-'.$this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }
}
