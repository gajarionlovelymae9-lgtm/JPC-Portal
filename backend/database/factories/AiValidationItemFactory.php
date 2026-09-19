<?php

namespace Database\Factories;

use App\Models\AiValidationItem;
use App\Models\AiValidationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiValidationItem>
 */
class AiValidationItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_validation_result_id' => AiValidationResult::factory(),
            'category' => 'completeness',
            'severity' => 'medium',
            'finding' => 'Some required evidence appears to be missing from the report.',
            'evidence' => 'The results section does not reference the supporting document.',
            'recommendation' => 'Add a reference to the supporting evidence before final review.',
        ];
    }
}