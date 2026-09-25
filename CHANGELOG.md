# Changelog

All notable changes to the JPC Portal are documented here.

Categories: **Added**, **Changed**, **Fixed**, **Security**, **Deprecated**,
**Removed**.

## [v1.0.0] — 2026-09-23

Baseline release of the JPC Portal (Phases 1–13).

### Added

- **System Health & Monitoring (Phase 13)**
  - `system_health_checks`, `maintenance_logs`, `user_feedback`,
    `ai_feedback`, `ai_prompt_versions` tables with models + factories.
  - `app/Services/System/` health services — Application, Database (size,
    orphans, migration drift), Queue (failed jobs by type), Storage
    (private-disk free space, export files), AI provider (failure rate,
    durations), OCR provider (confidence, failure rate) — plus
    `SystemMonitoringService` dashboard/history aggregation.
  - Admin-only health API: `/admin/system-health[/dashboard|/database|/queue|
    /storage|/ai|/ocr|/history]` and `/admin/maintenance/logs`.
  - User feedback API (any role): `POST /feedback`, `GET /feedback/mine`,
    `POST /feedback/ai`; admin management at `/admin/feedback`.
  - Maintenance logging with audit trail via `MaintenanceLogService`.
  - Commands: `system:check-health`, `system:record-maintenance`,
    `system:verify-backups` (never claims an unverified backup is valid).
  - Scheduled monitoring: daily `system:check-health` (05:00),
    `system:verify-backups` (05:30), weekly queued full data-quality scan.
  - Frontend: System Health dashboard, Maintenance Log, Feedback Center
    (admin) and Feedback (all roles), service layer, nav integration.
  - `AiPromptVersionSeeder` prompt/model version registry.
  - Backend suite `SystemMaintenanceApiTest` (17 tests) + frontend wiring.

### Changed

- **Documentation** — added `docs/maintenance/*` (monitoring, backup-restore,
  incident-response, troubleshooting, security-maintenance, ai-maintenance,
  ocr-maintenance, database-maintenance, release-process, monthly-checklist),
  `docs/BACKLOG.md`, this CHANGELOG; README updated with monitoring/maintenance
  sections.

### Fixed

- Dev DB migration drift (Phase 12): `report_exports` migration applied on
  `acadtrack_db` (73/73 → 78/78 including the 5 Phase 13 tables).
- Health metrics now compute live from `ai_analyses` / `ocr_processings`
  instead of the hardcoded values previously shown in the admin AI monitor.

### Security

- Health/feedback/maintenance endpoints strictly `role:admin` (403 for other
  roles, 401 unauthenticated — covered by tests).
- Health responses expose aggregate counts only — no credentials, prompts,
  or log contents; critical-error strings are redacted.
- No secrets in logs; no tracked secrets; no dangerous HTML rendering.

### Deprecated

- *(none)*

### Removed

- *(none)*

## [v1.0.0-final] — 2026-09-25

Phase 14 final release: full demo-scenario hardening. Every portal surface
exercised by the four demo personas now returns 200 in a live end-to-end run.

### Added

- `DevelopmentDeliveryMonitoringSeeder` — demo samples for notifications
  (6 `notification_logs`) and audit (5 `audit_logs`), gated behind
  `SEED_DEVELOPMENT_DATA` and idempotent (skips when data already exists).
- `tests/Feature/Api/Dashboard/PortalSurfaceRegressionTest.php` (10 tests)
  covering all Phase 14 surfaces: admin executive dashboard, admin
  departments, HR employees, department-head tasks, department-head +
  employee report detail, AI insights, messaging recipients, escalations.

### Fixed

- **Admin executive dashboard** 500 — phantom `task.deadline` column (now
  `due_date`) and `submitted_at` (now `completed_at`) in
  `AdminExecutiveDashboardService`.
- **Admin report center** 500 — phantom `task.deadline` / `task.requirements`
  (now `due_date` / `required_output`); report detail rewritten to use plural
  relations (`aiAnalyses` / `aiSummaries` / `aiValidationResults` /
  `ocrProcessings`); OCR `extracted_text` → `raw_text`; AI summary parsed from
  JSON via `extractSummary()`; `Report::ocrProcessings()` rebuilt as
  `hasManyThrough(OcrProcessing, ReportFile)` (the default `report_id` FK does
  not exist).
- **Admin departments** 500 — undefined `head` relationship (now
  `departmentHeads` + current-head lookup).
- **HR employees** 500 — `ApiResponse::collection` on a plain `Collection`
  (now `ApiResponse::success`).
- **Department-head tasks** 500 — undefined `creator` relationship (now
  `createdBy`).
- **Department-head + employee report detail** 500 — phantom singular AI/OCR
  relations, `report_versions.version` column (now `version_number`),
  `ocr_processings.report_id` (via the `hasManyThrough` fix), `is_read` (now
  `read_at`), `completion_percentage` (now `progress_percentage`).
- **AI insights** 500 — `hasRole()` given an array (role filtering now uses
  `roleName()` with the correct `department_head` value).
- **Messaging recipients + announcements** 500 — phantom `users.is_active`
  column (now `users.status = 'active'`).
- **Department escalations** 500 — UTF-8 BOM stripped from
  `DepartmentEscalation` model and escalation migration (was corrupting
  route/model parsing).

### Security

- Report-detail endpoints retain strict department/ownership scoping
  (403 guards verified in regression + live E2E).

## Known limitations (v1.0.0)

- AI/OCR rely on provider availability when real providers are enabled;
  offline `fake` engines are the default and keep the system usable.
- Similarity detection is an indicator requiring human review — never truth.
- Frontend bundle is a single ~869 kB chunk (tracked in BACKLOG JPC-002).
- Email delivery depends on mail configuration (dev default logs only).