<?php

namespace Database\Seeders;

use App\Models\AiPromptVersion;
use Illuminate\Database\Seeder;

/**
 * Seeds the AI prompt/model version registry (Phase 13 §17).
 *
 * Records the current prompt versions per AI purpose so AI output history
 * remains explainable. New versions are always added as new rows (the unique
 * index on purpose+version is never reused).
 */
class AiPromptVersionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateString();

        $entries = [
            [
                'purpose' => 'report_summary',
                'provider' => 'fake',
                'model' => 'jpc-summary-v1',
                'version' => 'v1.0.0',
                'description' => 'NLP report summarization prompt (capstone v1.0.0).',
                'versioned_at' => $now,
            ],
            [
                'purpose' => 'report_validation',
                'provider' => 'fake',
                'model' => 'jpc-validation-v1',
                'version' => 'v1.0.0',
                'description' => 'Report completeness/consistency validation rules.',
                'versioned_at' => $now,
            ],
            [
                'purpose' => 'similarity_check',
                'provider' => 'fake',
                'model' => 'jpc-similarity-v1',
                'version' => 'v1.0.0',
                'description' => 'Titled similarity detection between report versions.',
                'versioned_at' => $now,
            ],
            [
                'purpose' => 'ai_chat',
                'provider' => 'fake',
                'model' => 'jpc-chat-v1',
                'version' => 'v1.0.0',
                'description' => 'AI chat assistant intent detection + tool execution prompts.',
                'versioned_at' => $now,
            ],
            [
                'purpose' => 'insight_generation',
                'provider' => 'fake',
                'model' => 'jpc-insight-v1',
                'version' => 'v1.0.0',
                'description' => 'Auto-generated performance insights for analytics.',
                'versioned_at' => $now,
            ],
        ];

        foreach ($entries as $entry) {
            if (AiPromptVersion::query()->where('purpose', $entry['purpose'])->where('version', $entry['version'])->doesntExist()) {
                AiPromptVersion::create($entry);
            }
        }
    }
}