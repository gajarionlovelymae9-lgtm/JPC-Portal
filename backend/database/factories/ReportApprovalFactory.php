<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportApproval>
 */
class ReportApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'approver_id' => User::factory(),
            'decision' => 'approved',
            'comments' => 'Approved.',
            'approved_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['decision' => 'approved', 'approved_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['decision' => 'rejected']);
    }
}