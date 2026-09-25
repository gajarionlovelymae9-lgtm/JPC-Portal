# JPC Portal — Final System Architecture

| | |
| --- | --- |
| **Project** | JPC Portal — AI-Driven Employee Progress Monitoring & Management System |
| **Organization** | John Paul College |
| **Version** | v1.0.0 |
| **Database** | `acadtrack_db` (MySQL 8 / XAMPP MariaDB compatible) |

## 1. High-Level Architecture

```
                ┌──────────────────────────────────────────────┐
                │             React Frontend (SPA)             │
                │  Vite + TypeScript + React 19                 │
                │  src/pages · src/components · src/services     │
                │  src/hooks · src/types · src/routes            │
                └──────────────▲───────────────────────────────┘
                               │  HTTPS + JSON  (same-origin cookie)
                               │  /api/v1  ·  /sanctum/csrf-cookie
                ┌──────────────▼───────────────────────────────┐
                │              Laravel 12 REST API               │
                │  routes/api.php  (versioned v1 group)          │
                │  Controllers → Form Requests → Services        │
                │  Resources (API responses)                     │
                └──────┬──────────────┬──────────────┬──────────┘
                       │              │              │
        ┌──────────────▼───┐  ┌───────▼────────┐  ┌─▼──────────────┐
        │ Authentication / │  │ Business       │  │ Queues / Jobs  │
        │ RBAC (Sanctum +  │  │ Services       │  │ ProcessReport  │
        │ roles &          │  │ (rules,        │  │ ·AiChat ·DQ    │
        │ permissions)     │  │  scoring,      │  │ Scan ·Export · │
        └──────────────┬───┘  │  audit)        │  │ Notifications  │
                       │      └───────┬────────┘  └───────┬────────┘
        ┌──────────────▼──────────────▼───────────────────▼────────┐
        │              MySQL 8  (acadtrack_db)                     │
        │  users · reports · tasks · performance · ai · audit      │
        └──────────────────────────────────────────────────────────┘
                       ▲                               ▲
        ┌──────────────┴───────────────┐  ┌───────────┴─────────────┐
        │  OCR / AI Services (server)  │  │ Reporting / Analytics   │
        │  Google Vision OCR (opt.)    │  │ ReportingService        │
        │  OpenAI-compatible AI (opt.) │  │ AnalyticsService        │
        │  deterministic fake drivers  │  │ ManagementSummary       │
        │  (default, offline-safe)     │  │ Exports (CSV/XLSX/PDF)  │
        └──────────────────────────────┘  └─────────────────────────┘
```

Every subsystem communicates through the Laravel API only:

- **Browser → API**: the SPA never touches the database. All reads/writes are REST
  calls scoped to `/api/v1` using Sanctum **cookie-based SPA authentication**
  (same-origin; the Vite dev proxy forwards `/api` and `/sanctum`).
- **API → DB**: controllers delegate to **Services**; all queries use Eloquent and
  parameterized query builders (SQL-injection safe).
- **API → Queues**: heavy or async work is dispatched to the `database` queue
  (`QUEUE_CONNECTION=database`) and processed by `php artisan queue:work`.
- **API → AI/OCR**: AI and OCR run **server-side only**. The default providers are
  deterministic offline engines (`AI_PROVIDER=fake`, `OCR_PROVIDER=fake`) so the
  whole pipeline runs with no external accounts.
- **Scheduler**: Laravel scheduler (`routes/console.php`) triggers deadline
  reminders, monthly report generation, and export cleanup.

## 2. Frontend (React SPA)

| Area | Location |
| --- | --- |
| Entry point | `frontend/src/main.tsx`, `frontend/src/App.tsx` |
| Routing | `frontend/src/routes/paths.ts` + `<Routes>` in `App.tsx`, guarded by `ProtectedRoute` (permission-based) |
| API service layer | `frontend/src/services/*` (central `api.ts` with `apiRequest`/`ApiError`) |
| State | React context (`AuthContext`) + hooks (`useReporting`, dataQuality/ai/performance hooks) |
| Types | `frontend/src/types/*.ts` shared with the service layer |
| Styling | Plain CSS (`src/index.css`) with a shared pattern library (task-section, task-table, dashboard-grid, page-state, etc.) |
| Tests | Vitest + Testing Library (`src/**/*.test.{ts,tsx}`) |

Key page groups (under `frontend/src/pages/`):

- `auth/login` — login
- `dashboard` — institution/management dashboard
- `employee` — employee self-service (reports, performance, details)
- `reports` — report management, review, workflow, timeline, versions
- `tasks` — task list/detail/assignments/timeline
- `admin` — employees, departments, positions, users, RBAC
- `departmentHead` — department head portal
- `ai` — AI chat, AI insights
- `DataQuality` — scans, issues
- `Reporting` — reports, analytics hub, exports, management summary
- `notifications`, `messages` — notifications and internal messages

## 3. Backend (Laravel 12)

### 3.1 Request flow

```
HTTP → routes/api.php → Middleware (auth:sanctum, permission, role, throttle)
     → Controller → Form Request (validation) → Service (business logic)
     → Resource (response) → JSON envelope { success, message, data | errors }
```

- **Envelope convention**: every endpoint returns
  `{ "success": bool, "message": str, "data": ... }` or
  `{ "success": false, "message": str, "errors": {...} }`.
- **RBAC middleware**: `permission:<key>` and `role:<role>` gates each route group.

### 3.2 Core services (`backend/app/Services/`)

| Service | Responsibility |
| --- | --- |
| `Employee/*` | Employee lifecycle, profiles, history, self-service (portal) |
| `Reports/*` | Report submission, versioning (`ReportVersionService`), review/approve/reject workflow (`ReportWorkflowService`), export (`ReportExportService`) |
| `AiReports/*` | Pipeline driver (`ReportAnalysisService`), findings engine, AI+OCR adapters (fake / Google Vision / OpenAI-compatible HTTP) |
| `AI/*` | AI chat: intent detection, tool registry, authorization, context builder, response validator, insight generation |
| `DataQuality/*` | Scans and issues detection, history, resolution |
| `Performance/*` | Scoring engine, periods, breakdowns, KPI calculation |
| `Reporting/*` | Aggregated reports `ReportingService`, `AnalyticsService`, management summary, ranking/distribution |
| `Audit/*` | Audit logging of sensitive actions |
| `Notifications/*` | In-app + mail notifications, preferences, delivery logs |

### 3.3 API surface overview (`backend/routes/api.php`, all under `/api/v1`)

- `auth/*` — login, logout, me
- `admin/*` — RBAC catalog, institution dashboard options
- `employees/*`, `departments/*`, `positions/*` — HR/admin management
- `dashboard/*` — management dashboard aggregates
- `tasks/*`, `task-assignments/*`, `me/*` — task management + employee task operations
- `reports/*` — reports CRUD, workflow (submit/review/revision/approve), files, versions, compare
- `reports/*` (Phase 9 group) — employee/department/institution reports, monthly, achievements, late, pending, targets, data quality, management summary
- `analytics/*` — trends, comparisons (departments/employees/periods), rankings, distribution
- `reports/exports/*` — list, queue, show, download report export files
- `performance/*`, `achievements/*` — scoring and achievements
- `department-head/*`, `hr/*` — role-scoped portals
- `ai/*` — AI chat conversations, messages, insights
- `data-quality/*` — scans and issues
- `notifications/*`, `messages/*`, `announcements/*` — communication
- `audit-logs`, `activity-logs` — trail
- `health` — service health (never exposes secrets)

## 4. Authentication & RBAC

- **Mechanism**: Laravel Sanctum **SPA cookie** authentication (not bearer tokens in
  the browser). CSRF protection via `/sanctum/csrf-cookie`; `SANCTUM_STATEFUL_DOMAINS`
  and `CORS_ALLOWED_ORIGINS` list the SPA origin.
- **Roles** (seeded): `admin`, `hr`, `department_head`, `employee`.
- **Permissions**: centered on a permission catalog (`dashboard.view`,
  `employee.view`, `report.review`, `analytics.view`, `reports.export`, ...). Assigned
  to roles in `RolePermissionSeeder`.
- **Enforcement**: route middleware (`permission:`, `role:`) + model policies
  (`*Policy.php`) + service-level scoping (e.g. `ResolvesReportingScope` prevents
  in-service IDOR across reporting endpoints).
- **Employee-only vs management**: analytics and management summary are restricted
  to admin/HR/department head; employees have self-service views scoped by the API.

## 5. Database

- Engine MySQL 8 (XAMPP MariaDB compatible), database `acadtrack_db`, charset
  `utf8mb4`.
- Migrations under `backend/database/migrations/` are the source of truth.
- Subsystem tables: identity & RBAC, planning (academic year, annual plan, targets,
  department plans), tasks, reports (versions/files/references/reviews/approvals/
  timeline/comments), OCR & AI pipeline results (ocr_processings, ai_analyses,
  ai_summaries, ai_validation_results/items, ai_similarity_results), chat & insights
  (ai_chat_conversations/messages/tool_calls, ai_findings, ai_insights), performance
  (records, progress snapshots, periods, breakdowns), achievements (badges, employee
  badges, monthly achievements), data quality (scans, issues, history),
  notifications (notifications, preferences, logs), messages/announcements, exports
  (report_exports), audit (audit_logs, activity_logs), login activities.
- Full reference: see [`DATABASE.md`](DATABASE.md), [`database/erd.md`](database/erd.md),
  [`database/schema.sql`](database/schema.sql).

## 6. Queues & Jobs

`QUEUE_CONNECTION=database` (default). Workers run `php artisan queue:work`. Jobs
live under `backend/app/Jobs/` (plus `Jobs/Ai`, `Jobs/DataQuality`, `Jobs/Reporting`,
`Jobs/Reports`):

- `ProcessReportDocumentJob` / `ProcessReportOcr*` — OCR extraction on uploaded files
- `AnalyzeReport*` (summarize, validate, references, similarity)
- `Ai/ProcessAiChatJob` — process an AI chat message with context + tools
- `Ai/GenerateAiInsightJob` — background insight generation
- `DataQuality/RunDataQualityScanJob` — scan dataset for quality issues
- `Reporting/GenerateReportExportJob` — build CSV/XLSX/PDF export
- `SendNotificationJob` — deliver notifications (in-app/mail)

Queue batch setup: `php artisan queue:batch-table` migrations are already in place
(`jobs`, `job_batches`, `failed_jobs`, `cache`).

## 7. Scheduled Tasks (`routes/console.php`)

| Command | Schedule |
| --- | --- |
| `tasks:send-deadline-reminders` | daily 07:00 |
| `tasks:send-overdue-notifications` | daily 07:30 |
| `reports:generate-monthly` | monthly on the 1st at 06:00 |
| `reports:cleanup-exports` | daily 03:15 (TTL-driven export cleanup) |

Run `php artisan schedule:work` locally; configure cron `* * * * * php artisan
schedule:run >> /dev/null 2>&1` on a server (see [`DEPLOYMENT.md`](DEPLOYMENT.md)).

## 8. OCR / AI Services

- **Default (offline-safe)**: `OCR_PROVIDER=fake`, `AI_PROVIDER=fake` — deterministic
  deterministic engines generate text, confidence, findings, summaries, and insights
  locally so the full workflow works with no network or accounts.
- **OCR (optional)**: `OCR_PROVIDER=google_vision` uses
  `GoogleVisionOcrService` (REST `annotateImage`, `GOOGLE_VISION_API_KEY` or
  `GOOGLE_APPLICATION_CREDENTIALS`). Server-side only.
- **AI (optional)**: `AI_PROVIDER=openai` (or an OpenAI-compatible endpoint via
  `AI_ENDPOINT`) uses `HttpAiProvider`. All AI requests originate from the backend;
  keys live in the server `.env`, never in the browser bundle.
- All results are stored per report (analysis/summary/validation/similarity) and
  **always require human review** — AI never makes final approval decisions.

## 9. Notifications

- In-app notifications (DB table `notifications`) with preference routing
  (`notification_preferences`), delivery log (`notification_logs`), and optional
  email via `SendNotificationJob`.
- If mail is unavailable the application does not crash — failures are logged and
  notification status remains traceable.

## 10. Reporting / Analytics

- `ReportingService` produces phase-9 report payloads (employee/department/
  institution/monthly/achievements/late/pending/targets/data-quality/management
  summary) with in-service ownership scoping.
- `AnalyticsService` computes trends, comparisons, rankings, and distributions from
  the normalized tables.
- Exports are generated in the queue (`GenerateReportExportJob`) as CSV/XLSX/PDF,
  stored on a **private** disk (`FILESYSTEM_DISK=local` under `storage/app/private`),
  served through authenticated controllers (never public URLs), and cleaned up by TTL.

## 11. Audit Trail

- `audit_logs` records sensitive mutations (who, what, before/after).
- `activity_logs` records broad user activity.
- A centralized audit service is used by services that mutate authoritative data.

## 12. Environment & Configuration

- Backend config keys live in `backend/config/*.php`, read from `.env`
  (see [`README.md`](../README.md) and [`DEPLOYMENT.md`](DEPLOYMENT.md)).
- Frontend Vite uses `VITE_API_BASE_URL`; development proxies `/api` and `/sanctum`
  to `http://127.0.0.1:8000`.
- `.env` is never committed; `.env.example` holds safe placeholders.

## 13. Testing

- Backend: PHPUnit via `php artisan test`. Isolation uses `acadtrack_db_test`
  (configured in `phpunit.xml`); automated tests can never destroy `acadtrack_db`.
- Frontend: Vitest (`npm test`), oxlint (`npm run lint`), type-check + production
  build (`npm run build`).
- See [`DEVELOPER_HANDOVER.md`](DEVELOPER_HANDOVER.md) for the test inventory.