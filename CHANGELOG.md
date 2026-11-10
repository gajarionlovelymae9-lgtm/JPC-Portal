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

## [v1.0.0] — 2026-09-25 (Phase 15 release packaging)

Phase 15: final release packaging, deployment handover & maintenance baseline —
documentation-only release. No application code changed.

### Added

- **Documentation (new files):**
  - `docs/INSTALLATION.md` — fresh-machine installation (Laravel + React + DB
    init + env + queue + first login).
  - `docs/RELEASE_NOTES.md` — v1.0.0 release notes (§26).
  - `docs/VERSION_MANAGEMENT.md` — versioning scheme + change-request process
    (§23–§24).
  - `docs/maintenance-log.md` — maintenance-log template and CLI/UI procedure
    (§25; no fabricated history).
  - `docs/user-guides/employee-guide.md` and
    `docs/user-guides/management-guide.md` — consolidated user guides (§29–§30).

### Changed

- **Documentation (updates):**
  - `README.md` — corrected the `SEED_DEVELOPMENT_DATA` default (it defaults to
    `true`; production must set it `false` explicitly), added the new docs to
    the table, added the test database to the install snippet.
  - `docs/DEPLOYMENT.md` — documented all **7** scheduled tasks, the sample-data
    policy warning, the real on-disk storage path
    (`storage/app/private/private/reports`), plus new deployment checklist and
    smoke-test sections.
  - `docs/BACKUP_AND_RESTORE.md` — corrected storage paths and documented
    `system:verify-backups` behavior accurately.
  - `docs/TROUBLESHOOTING.md` — added `optimize:clear`, deploy 500s, React build,
    seeders-on-production and test-database entries.
  - `docs/MAINTENANCE.md` — security-maintenance checklist, scheduled health/
    backup checks, weekly data-quality scan.
  - `docs/GO_LIVE_CHECKLIST.md` — SEED flag gate + new doc references +
    scheduled-task list.
  - `docs/DEVELOPER_HANDOVER.md` — 7 scheduled tasks, queue failed-driver
    `database-uuids`, storage paths, test counts, 78 migrations, warning count 58.
  - `docs/ADMIN_HANDOVER.md` — sample-data policy, first-admin creation, 7
    scheduled tasks, maintenance-log procedure.
  - `docs/ADMIN_GUIDE.md`, `docs/HR_GUIDE.md`, `docs/DEPARTMENT_HEAD_GUIDE.md`,
    `docs/EMPLOYEE_GUIDE.md` — pointer notes to the consolidated user guides
    (existing guides preserved).
  - `docs/maintenance/backup-restore.md`, `docs/maintenance/ocr-maintenance.md`,
    `docs/DATA_PRIVACY.md`, `docs/SECURITY.md` — corrected private storage path.
  - Encoding repair (double-encoded CP1252 mojibake) for 6 final/capstone docs:
    `GO_LIVE_CHECKLIST.md`, `FINAL_DEMO_SCRIPT.md`, `CAPSTONE_SYSTEM_OVERVIEW.md`,
    `CAPSTONE_DEFENSE_QA.md`, `CAPSTONE_PRESENTATION_FLOW.md`,
    `SYSTEM_ACCEPTANCE.md`.

### Security

- Documented that `SEED_DEVELOPMENT_DATA` defaults to `true` — production must
  set `SEED_DEVELOPMENT_DATA=false` explicitly (previously ambiguous in docs).

## Known limitations (v1.0.0)

- AI/OCR rely on provider availability when real providers are enabled;
  offline `fake` engines are the default and keep the system usable.
- Similarity detection is an indicator requiring human review — never truth.
- Frontend bundle is a single ~869 kB chunk (tracked in BACKLOG JPC-002).
- Email delivery depends on mail configuration (dev default logs only).