<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\EmployeeTargetAdjustmentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTargetAdjustmentRequest>
 */
class EmployeeTargetAdjustmentRequestFactory extends Factory
{
    protected $model = EmployeeTargetAdjustmentRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_target_id' => EmployeeTarget::factory(),
            'employee_id' => Employee::factory(),
            'requested_by' => User::factory(),
            'reason' => $this->faker->paragraph(),
            'request_type' => 'other',
            'proposed_target_value' => null,
            'proposed_target_unit' => null,
            'proposed_start_date' => null,
            'proposed_end_date' => null,
            'proposed_description' => null,
            'status' => EmployeeTargetAdjustmentRequest::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision_note' => null,
            'original_snapshot' => null,
            'applied_at' => null,
        ];
    }

    /**
     * A request that has already been approved.
     */
    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => EmployeeTargetAdjustmentRequest::STATUS_APPROVED,
            'reviewed_at' => now(),
            'applied_at' => now(),
        ]);
    }
}
