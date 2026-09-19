<?php

namespace App\Enums;

/**
 * AiMessageRole — the single source of truth for AI chat message roles.
 *
 * Phase 7 (AI Chat Assistant & Intelligent Analytics).
 * Spec item 2: "AiMessageRole.php — user, assistant, tool, system".
 *
 * Internal `system` role must never be exposed to users via API resources.
 */
enum AiMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
    case System = 'system';

    /**
     * Whether this role should be visible in user-facing API responses.
     */
    public function isPublic(): bool
    {
        return $this !== self::System;
    }

    /**
     * Human-visible label — used by API resources for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Assistant => 'Assistant',
            self::Tool => 'Tool',
            self::System => 'System',
        };
    }
}