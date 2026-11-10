<?php

namespace App\Services\AiReports\Ai;

/**
 * Detects prompt-injection attempts embedded in untrusted document content.
 *
 * The pipeline never executes content found in a document; this guard exists
 * so injection attempts are surfaced for the human reviewer (as inert flag
 * evidence) instead of silently disappearing from the analysis.
 */
final class PromptInjectionGuard
{
    /**
     * @var list<string>
     */
    private const PATTERNS = [
        '/\bignore\s+(all|any|the|above|previous|prior|earlier)\s+(instructions?|prompts?|rules?|context|message)/i',
        '/\b(forget|disregard|override|bypass)\s+\w*\s*(instructions?|prompts?|rules?|context|system)/i',
        '/\bdo not follow\b/i',
        '/\bact\s+(as|like)\s+(an?\s+)?(system|assistant|administrator|developer)\b/i',
        '/\bsystem\s+prompt\b/i',
    ];

    public function containsInstructionOverride(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Label shown alongside evidence that an injection attempt was contained.
     */
    public static function containmentLabel(): string
    {
        return 'Embedded instructions were treated as data and not executed.';
    }
}
