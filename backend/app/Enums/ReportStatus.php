<?php

namespace App\Enums;

/**
 * ReportStatus — the single source of truth for every report state.
 *
 * Phase 5 (Report Submission → Review → Approval → Version Control).
 * Spec items 12–13: "Centralize report states. Do not scatter strings
 * such as 'approved'/'revision_required' throughout the application."
 *
 * Every value here maps 1:1 to the Phase 5 status literal, and status
 * transitions are validated by ReportWorkflowService (never free-form).
 */
enum ReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case UnderReview = 'under_review';
    case RevisionRequired = 'revision_required';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    /**
     * Statuses the employee can still edit (draft only — submitted
     * versions are immutable per Phase 5 versioning rules).
     */
    public function isEditableByEmployee(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Statuses where OCR/AI processing is pending or in flight.
     */
    public function isProcessing(): bool
    {
        return in_array($this, [self::Processing, self::Resubmitted], true);
    }

    /**
     * Statuses that represent a terminal / non-actionable state for the
     * employee (no further resubmission is possible without a new draft).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled, self::Archived], true);
    }

    /**
     * Human-visible label — used by API resources; do not rely on the
     * raw string in the frontend for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Processing => 'Processing',
            self::UnderReview => 'Under Review',
            self::RevisionRequired => 'Revision Required',
            self::Resubmitted => 'Resubmitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Archived',
        };
    }
}
