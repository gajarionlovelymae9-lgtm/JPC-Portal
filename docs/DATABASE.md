# JPC Portal — Final Database Documentation

| | |
| --- | --- |
| **Database** | `acadtrack_db` |
| **Engine** | InnoDB (MySQL 8; XAMPP MariaDB >= 10.4 compatible) |
| **Charset / Collation** | `utf8mb4` / `utf8mb4_unicode_ci` |
| **Authoritative source** | `backend/database/migrations/*.php` |

> Related references: [`engineer ERD (Mermaid)`](database/erd.md),
> [`schema design reference`](database/schema.sql),
> [`migrations doc`](database/phase-3-3-b-migrations.md).

## Conventions

- Primary keys: `id BIGINT UNSIGNED AUTO_INCREMENT`.
- Foreign keys: `<table>_id` with `RESTRICT` on delete (history preservation);
  `CASCADE` only on pure link tables (`role_user`, `permission_role`).
- Status/enum-like values use `VARCHAR` and are validated by application enums
  (`app/Enums/*`), not MySQL `ENUM`.
- `created_at`/`updated_at` timestamps on all tables; soft deletes via `deleted_at`
  where noted.
- Undeletable history tables keep all rows; only references may be soft-deleted.

## ERD

A full Mermaid ERD is maintained in [`docs/database/erd.md`](database/erd.md).
The major relationships:

```
User ─has─ Employee ─belongs to─ Department
Department ─has─ DepartmentHead (current) / DepartmentPlan / DepartmentTarget
AcademicYear ─has─ AnnualPlan ─has─ EmployeeTarget
AnnualPlan ─scopes─ Task ─has─ TaskAssignment ─for─ Employee
Task ─produces─ Report ─has─ ReportVersion ─has─ ReportFile
Report ─has─ OcrProcessing, AiAnalysis (→ summary/validation/similarity), Review, Approval, Timeline, Comments
Employee ─has─ PerformanceRecord/ProgressSnapshot (→ PerformancePeriod, ScoreBreakdown), Badge, MonthlyAchievement, History
Report ─has─ AiFinding, AiInsight
User ─has─ Notification/NotificationPreference/NotificationLog, Message, Conversation (AiChat), AuditLog, ActivityLog, LoginActivity
DataQualityScan ─has─ DataQualityIssue ─has─ DataQualityIssueHistory
ReportExport (queued/ready files)
```

## Table Groups

### 1. Identity, Authentication & RBAC

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `users` | Application accounts | PK `id`; UK `email`; `name`, `status` (active/suspended/inactive), `password` (hashed), `last_login_at`; soft deletes |
| `roles` | Role catalog | PK `id`; UK `name`; `display_name` |
| `permissions` | Permission catalog | PK `id`; UK `name`; `display_name` |
| `role_user` | User↔Role pivot | FK `role_id`, `user_id` (CASCADE) |
| `permission_role` | Role↔Permission pivot | FK `permission_id`, `role_id` (CASCADE) |
| `personal_access_tokens` | Sanctum tokens (non-SPA flows) | PK `id`; user FK; token hash |
| `login_activities` | Login/logout records | user FK, `ip_address`, `user_agent`, `event` |
| `employees` | Employee profiles | PK `id`; UK `user_id`, UK `employee_number`; FKs `department_id`, `position_id`; `first_name`, `last_name`, `employment_status`; soft deletes |
| `positions` | Position catalog | UK `name`, `code`; `status`; soft deletes |
| `departments` | Department catalog | UK `name`, `code`; `status`; soft deletes |
| `department_heads` | Department head tenure | FKs `department_id`, `employee_id`; `start_date`, `end_date`, `is_current` |

### 2. Planning (Academic Year / Plans / Targets)

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `academic_years` | Academic year periods | UK `name`; `start_date`, `end_date`, `status` |
| `annual_plans` | Institutional/dept annual plan | FKs `academic_year_id`, `department_id`, `created_by`, `approved_by`; `title`, `status`; soft deletes |
| `department_plans` | Department-level planning | FK `annual_plan_id`, `department_id`; `status` |
| `employee_targets` | Employee targets | FK `annual_plan_id`, `employee_id`, optional `department_target_id`; `title`, `target_value`, `target_unit`, `status`; soft deletes |
| `department_targets` | Department targets | FK `department_plan_id`, `department_id`; `title`, `target_value`, `unit`; `status` |

### 3. Task Management

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `tasks` | Task catalog | FK `annual_plan_id`, `department_id`, `created_by`; `code`, `title`, `priority`, `due_date`, `status` (draft/published/in_progress/completed/cancelled); soft deletes |
| `task_assignments` | Task → employee assignment | FKs `task_id`, `employee_id`, `assigned_by`; `progress_percentage` (0–100), `status`, `due_date`, review fields |
| `task_references` | Reference materials per task | FK `task_id`; `title`, `reference_type`, `reference_url`, `file_path`; soft deletes |
| `task_comments` | Task discussion | FK `task_id`, `user_id` |
| `task_attachments` | Task file attachments | FK `task_id`, `uploaded_by`; file metadata |
| `task_progress` | Progress history trail | FK `task_assignment_id`; value changes + actor |
| `task_reviews` | Task-level review decisions | FKs `task_assignment_id`, `reviewer_id`; decision/notes |

### 4. Reports & Workflow

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `reports` | Report record | FKs `task_id`, `employee_id`; `title`, `deadline`, `status` (draft/submitted/processing/under_review/revision_required/resubmitted/approved/rejected/cancelled/archived), `is_late`, `version`, `submitted_at`, `content`, `references`; soft deletes |
| `report_versions` | Versioned report snapshots | FKs `report_id`, `submitted_by`; `version_number`, `file_path`, `submitted_at`, snapshot fields |
| `report_files` | Uploaded documents | FKs `report_id`, `report_version_id`, `uploaded_by`; `original_filename`, `stored_filename`, `file_path`, `mime_type`, `file_size`, `checksum`; soft deletes |
| `report_references` | Report ↔ task reference anchors | FK `report_id`, `task_reference_id`; `reference_text`, `reference_url` |
| `report_reviews` | Manual review records | FKs `report_id`, `reviewer_id`; `review_type`, `decision` (approved/revision_required/rejected), `reviewed_at` |
| `report_review_comments` | Free-text review comments | FKs `report_id`, `review_id`, `user_id` |
| `report_approvals` | Approval records | FKs `report_id`, `approver_id`; `decision`, `comments`, `approved_at` |
| `report_timelines` | Full workflow event trail | FK `report_id`, `user_id`; `event`, `from_status`, `to_status`, `notes`, timestamps |

### 5. OCR & AI Pipeline

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `ocr_processings` | OCR attempts per file | FK `report_file_id`; `provider`, `status`, `raw_text`, `confidence_score`, `completed_at` |
| `ai_analyses` | AI analysis runs | FK `report_id`; `analysis_type`, `provider`, `model`, `status`, `input_hash` |
| `ai_summaries` | Generated summaries | FK `report_id`, `ai_analysis_id`; `summary`, `key_accomplishments`, `recommendations` |
| `ai_validation_results` | Validation outcome | FK `report_id`, `ai_analysis_id`; `overall_score`, `result` |
| `ai_validation_items` | Per-item findings | FK `ai_validation_result_id`; `category`, `severity`, `finding` |
| `ai_similarity_results` | Similarity comparison | FK `report_id`, `compared_report_id`; `similarity_score` (indicator only — never proof), `status` |
| `ai_findings` | AI-generated findings requiring human validation | FK `report_id`; `finding_type`, `severity`, `content`, `status` |
| `ai_insights` | Generated insights | FK `report_id`/entity; `insight_type`, `summary`, `confidence` |

### 6. AI Chat & Tool Calls

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `ai_chat_conversations` | Conversation headers | FK `user_id`; `title`, `status` |
| `ai_chat_messages` | Message bodies (role, content, metadata) | FK `conversation_id`, `user_id` |
| `ai_chat_tool_calls` | Tool invocations bound to a message | FK `conversation_id`, `message_id`; tool name, intent, params, result, timings |

### 7. Performance & Achievements

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `performance_periods` | Named scoring periods | `code`, `start_date`, `end_date`, `status` |
| `employee_performance_records` | Period scores | FKs `employee_id`, `academic_year_id`, optional `performance_period_id`; `period_type`, `period_start/end`, `overall_score`, `performance_status` |
| `performance_score_breakdowns` | Score components | FK `performance_record_id`; `category`, `score`, `weight` |
| `employee_progress_snapshots` | Rolling progress | FKs `employee_id`, `academic_year_id`; `snapshot_date`, `completion_rate`, `overall_score`, `is_finalized` |
| `badges` | Badge catalog | UK `name`, `code`; `criteria_type`, `criteria_value`, `is_active`; soft deletes |
| `employee_badges` | Awarded badges | FKs `employee_id`, `badge_id`, `academic_year_id`, `awarded_by`; period, `awarded_at`, `criteria_snapshot` |
| `monthly_achievements` | Monthly rankings | FK `employee_id`, `academic_year_id`; `month`, `year`, `overall_score`, `rank`, `is_employee_of_month` |
| `employee_histories` | Personnel history | FK `employee_id`; event/change trail |

### 8. Data Quality

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `data_quality_scans` | Recent scan runs | `scan_type`, `status`, summary counts, `started_at`, `completed_at` |
| `data_quality_issues` | Detected issues | FK `scan_id`; `entity_type`, `entity_id`, `issue_type`, `severity`, `status` (open/in_progress/resolved/dismissed), `details` |
| `data_quality_issue_histories` | Issue lifecycle | FK `issue_id`; status transitions, actors, timestamps |

### 9. Reporting & Exports

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `report_exports` | Generated export files | FK `user_id`; `export_type`, `filters`, `status` (queued/processing/ready/failed), `file_path`, `expires_at` |

### 10. Notifications, Messages, Announcements

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `notifications` | In-app notifications | FK `user_id`; `type`/`title`/`message`/`data`, `read_at`, `status`, routing metadata |
| `notification_preferences` | Per-user channel routing | FK `user_id`; channel flags |
| `notification_logs` | Delivery trace | FKs `notification_id`, `user_id`; channel, status, error |
| `messages` | Internal one-to-one/multi messages | FKs `sender_id`, `recipient_id`, conversation refs; `read_at` |
| `announcements` | Broadcast announcements | author, `title`, `body`, `published_at` |

### 11. Escalations & Audit

| Table | Purpose | Keys / notes |
| --- | --- | --- |
| `department_escalations` | Department escalation workflow | FKs department/employee; subject, reason, status |
| `audit_logs` | Who/what/when mutations | FK `user_id`; `action`, `entity_type`, `entity_id`, `old_values`, `new_values` (polymorphic, no formal FK) |
| `activity_logs` | Broad activity stream | FK `user_id`; `activity_type`, `description`, `subject_type`, `subject_id`, `metadata` |

### 12. Framework

| Table | Purpose |
| --- | --- |
| `sessions` | DB session store (SESSION_DRIVER=database) |
| `cache` | DB cache store |
| `jobs` / `job_batches` / `failed_jobs` | Queue backend |
| `password_reset_tokens` | Password resets |

## Status Fields (single source of truth)

- Report: `draft, submitted, processing, under_review, revision_required,
  resubmitted, approved, rejected, cancelled, archived` — `app/Enums/ReportStatus.php`.
- Task: `draft, published, in_progress, completed, cancelled` — `app/Enums/TaskStatus.php`.
- Assignment: `assigned, in_progress, submitted, under_review, revision_required,
  approved, rejected, cancelled` (validated transitions by service).
- Report export: `queued, processing, ready, failed` — `app/Enums/ReportExportStatus.php`.
- Data-quality issue: `open, in_progress, resolved, dismissed`.
- Scan: `pending, running, completed, failed`.

## Data Integrity & Verification

- `migrate:fresh` must **never** be run in production — use `php artisan migrate`.
- Integrity checks (foreign keys, status enumerations, history) are covered by
  feature tests; see [`docs/database/database-verification.md`](database/database-verification.md).
- Test database `acadtrack_db_test` is created/used only by automated tests and is
  isolated from `acadtrack_db` (see `phpunit.xml`).