<?php

namespace App\Enums;

/**
 * Canonical notification events (used in notification preferences,
 * event dispatches, scheduler jobs, and in-app bell routing).
 */
enum NotificationEvent: string
{
    // Tasks
    case TaskAssigned = 'task_assigned';
    case TaskUpdated = 'task_updated';
    case TaskDeadline = 'task_deadline';
    case TaskDueToday = 'task_due_today';
    case TaskOverdue = 'task_overdue';
    case TaskCompleted = 'task_completed';

    // Reports
    case ReportSubmitted = 'report_submitted';
    case ReportUnderReview = 'report_under_review';
    case ReportApproved = 'report_approved';
    case RevisionRequired = 'revision_required';
    case ReportRejected = 'report_rejected';
    case ReportVersionSubmitted = 'report_version_submitted';

    // AI & OCR
    case OcrCompleted = 'ocr_completed';
    case OcrFailed = 'ocr_failed';
    case AiValidationCompleted = 'ai_validation_completed';
    case AiProcessingFailed = 'ai_processing_failed';

    // Targets & Achievements
    case TargetReminder = 'target_reminder';
    case TargetAtRisk = 'target_at_risk';
    case AchievementEarned = 'achievement_earned';
    case BadgeEarned = 'badge_earned';
    case MonthlyAchievement = 'monthly_achievement';

    // HR & Governance
    case HrEmployeeReview = 'hr_employee_review';
    case HrMissingInfo = 'hr_missing_info';
    case HrDuplicateDetected = 'hr_duplicate_detected';

    // Escalations
    case EscalationCreated = 'escalation_created';
    case EscalationUpdated = 'escalation_updated';
    case EscalationResolved = 'escalation_resolved';

    // System & Announcements
    case AnnouncementPublished = 'announcement_published';
    case MessageReceived = 'message_received';
    case System = 'system';
    case DataIssue = 'data_issue';
    case ReviewAssigned = 'review_assigned';
    case ReviewSla = 'review_sla';
    case ReviewOverdue = 'review_overdue';

    /**
     * Events that cannot be disabled by user preferences (institutional policy).
     */
    public function isCritical(): bool
    {
        return match ($this) {
            self::TaskAssigned,
            self::RevisionRequired,
            self::TaskOverdue,
            self::EscalationCreated,
            self::EscalationResolved,
            self::AiProcessingFailed,
            self::OcrFailed,
            self::DataIssue,
            self::System => true,
            default => false,
        };
    }

    /**
     * Get default category label.
     */
    public function category(): string
    {
        return match ($this) {
            self::TaskAssigned, self::TaskUpdated, self::TaskDeadline, self::TaskDueToday, self::TaskOverdue, self::TaskCompleted => 'task',
            self::ReportSubmitted, self::ReportUnderReview, self::ReportApproved, self::RevisionRequired, self::ReportRejected, self::ReportVersionSubmitted, self::ReviewAssigned, self::ReviewSla, self::ReviewOverdue => 'report',
            self::OcrCompleted, self::OcrFailed, self::AiValidationCompleted, self::AiProcessingFailed => 'ai_ocr',
            self::TargetReminder, self::TargetAtRisk => 'target',
            self::AchievementEarned, self::BadgeEarned, self::MonthlyAchievement => 'achievement',
            self::HrEmployeeReview, self::HrMissingInfo, self::HrDuplicateDetected => 'hr',
            self::EscalationCreated, self::EscalationUpdated, self::EscalationResolved => 'escalation',
            self::AnnouncementPublished, self::System, self::DataIssue, self::MessageReceived => 'system',
        };
    }
}
