<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnualPlan>
 */
class AnnualPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'department_id' => Department::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => 'draft',
        ];
    }
}
