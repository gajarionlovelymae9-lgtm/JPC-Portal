<?php

namespace App\Enums;

/**
 * ReportExportStatus — Phase 9 export pipeline lifecycle (spec items 53-55,
 * 32-33) for large exports:
 *
 *   QUEUED → PROCESSING → COMPLETED
 *                            ↘ FAILED
 *                            ↘ EXPIRED
 *
 * Generates asynchronously (never synchronous large files). EXPIRED is the
 * terminal state used by the secure cleanup job — the file is deleted using
 * private/unpredictable-name storage and must never be re-downloaded after it.
 */
enum ReportExportStatus: string
{
    case Queued = 'QUEUED';
    case Processing = 'PROCESSING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
        };
    }

    /**
     * Terminal states — a completed/failed/expired export can no longer be
     * transitioned, so duplicate submissions cannot silently overwrite it.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Expired], true);
    }

    public function isReadyForDownload(): bool
    {
        return $this === self::Completed;
    }

    /**
     * FAILED is the one non-terminal outcome a user can act on with the retry
     * endpoint: the request itself was valid, only generation failed.
     */
    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }
}
