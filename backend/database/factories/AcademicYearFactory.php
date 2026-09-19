<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = $this->faker->unique()->numberBetween(1990, 2090);

        return [
            'name' => $year.'-'.($year + 1),
            'start_date' => $year.'-06-01',
            'end_date' => ($year + 1).'-05-31',
            'status' => 'active',
        ];
    }
}
