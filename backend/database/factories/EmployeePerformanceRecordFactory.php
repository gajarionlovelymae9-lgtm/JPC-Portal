<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePerformanceRecord>
 */
class EmployeePerformanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-1 year', '-1 month');

        return [
            'employee_id' => Employee::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'period_type' => 'monthly',
            'period_start' => $periodStart->format('Y-m-01'),
            'period_end' => $periodStart->format('Y-m-t'),
            'completion_rate' => $this->faker->randomFloat(2, 45, 100),
            'on_time_rate' => $this->faker->randomFloat(2, 45, 100),
            'approval_rate' => $this->faker->randomFloat(2, 45, 100),
            'quality_score' => $this->faker->randomFloat(2, 45, 100),
            'target_achievement_rate' => $this->faker->randomFloat(2, 45, 100),
        ];
    }
}
