<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportReview>
 */
class ReportReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'reviewer_id' => User::factory(),
            'review_type' => 'initial',
            'decision' => 'approved',
            'comments' => null,
            'reviewed_at' => now(),
        ];
    }

    public function approval(): static
    {
        return $this->state(fn (): array => ['decision' => 'approved']);
    }

    public function revision(): static
    {
        return $this->state(fn (): array => [
            'decision' => 'revision_required',
            'comments' => 'Please include the supporting evidence and revise accordingly.',
        ]);
    }

    public function rejection(): static
    {
        return $this->state(fn (): array => [
            'decision' => 'rejected',
            'comments' => 'The report does not meet the submission requirements.',
        ]);
    }

    public function revisionRound(): static
    {
        return $this->state(fn (): array => ['review_type' => 'revision']);
    }
}