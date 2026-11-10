<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\EmployeeTargetNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTargetNote>
 */
class EmployeeTargetNoteFactory extends Factory
{
    protected $model = EmployeeTargetNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_target_id' => EmployeeTarget::factory(),
            'employee_id' => Employee::factory(),
            'body' => $this->faker->paragraph(),
        ];
    }
}
