<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry of AI prompt/model versions (Phase 13 AI maintenance).
 *
 * Records provider, model and prompt version for each AI purpose so output
 * history remains explainable when prompts evolve. Seeded by
 * AiPromptVersionSeeder; new versions are added, never overwriting history.
 */
class AiPromptVersion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'provider',
        'model',
        'version',
        'description',
        'versioned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'versioned_at' => 'date',
        ];
    }
}