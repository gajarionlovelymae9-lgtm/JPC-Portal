# JPC Portal — Developer Handover

Everything a new developer needs to understand and extend JPC Portal.

## 1. Project structure

```
jpc-portal/
├── backend/                 # Laravel 12 REST API (PHP 8.2)
│   ├── app/
│   │   ├── Console/Commands/      # artisan commands (deadline reminders, monthly report, export cleanup)
│   │   ├── Enums/                 # single-source-of-truth enums (ReportStatus, TaskStatus, ...)
│   │   ├── Http/Controllers/Api/V1/   # versioned controllers (auth, admin, employees, reports, ...)
│   │   ├── Http/Requests/         # validation
│   │   ├── Http/Resources/        # API responses
│   │   ├── Jobs/                  # queue jobs (incl. Jobs/Ai, Jobs/DataQuality, Jobs/Reporting, Jobs/Reports)
│   │   ├── Models/                # Eloquent models + relationships
│   │   ├── Policies/              # per-model authorization
│   │   ├── Providers/             # AppServiceProvider (registrations)
│   │   ├── Services/              # business logic (Employee, Reports, AiReports, AI, DataQuality, Performance, Reporting, Audit, Notifications)
│   ├── config/               # all config (ai, ai_reports, performance, reports, jpc, task, ...)
│   ├── database/
│   │   ├── migrations/       # schema (source of truth; ~90 files)
│   │   └── seeders/          # static + development seeders
│   ├── routes/api.php + console.php
│   ├── tests/                # PHPUnit (Feature + Unit)
│   └── .env.example
├── frontend/                 # React 19 + Vite + TypeScript
│   ├── src/
│   │   ├── components/       # shared components (tables, filters, states, dashboard...)
│   │   ├── hooks/            # custom hooks (auth, reporting, AI, data-quality...)
│   │   ├── pages/            # pages grouped by module
│   │   ├── services/         # typed API service layer (api.ts + per-module services)
│   │   ├── types/            # shared TypeScript types
│   │   ├── utils/            # helpers
│   │   ├── routes/paths.ts   # route path constants
│   │   ├── App.tsx           # routes + guarded layout
│   │   └── main.tsx
│   ├── vite.config.ts        # proxy /api + /sanctum → 127.0.0.1:8000; vitest config
│   └── .env.example
├── docs/                     # architecture, deployment, user guides, security, backups
└── README.md
```

## 2. Important services

| Service | Responsibility |
| --- | --- |
| `app/Services/Reports/ReportWorkflowService.php` | Validated status transitions (submit→review→approve/revision/reject) |
| `app/Services/Reports/ReportVersionService.php` | Immutable versioning + snapshots |
| `app/Services/Reports/ReportExportService.php` | Queued CSV/XLSX/PDF exports, private disk + TTL |
| `app/Services/AiReports/ReportAnalysisService.php` | OCR→AI pipeline driver (summarize/validate/references/similarity) |
| `app/Services/AI/AiChatService.php` | AI chat orchestration (intent→tool→response) |
| `app/Services/AI/AiAuthorizationService.php` | Authorizes AI tool calls per user/role |
| `app/Services/AI/AiToolRegistry.php` | Maps intents to tool implementations |
| `app/Services/DataQuality/*` | Scans + issues + history |
| `app/Services/Performance/*` | Deterministic scoring, periods, breakdowns |
| `app/Services/Reporting/*` | ReportingService (report payloads), AnalyticsService (trends/comparisons/rankings), management summary |
| `app/Services/Employee/*` | Employee lifecycle + self-service |
| `app/Services/Audit/*` | audit_logs/activity_logs writing |
| `app/Services/Notifications/*` | In-app + mail + preferences + logs |

**Gotchas:**

- `ReportingService::cache->remember` closures **must capture** `$period`/`$filters`
  explicitly — uncaptured variables silently drop (PHP warning suppression pattern).
- Status changes must go through the workflow services (never free-form strings).
- Enums live in `app/Enums` and are used everywhere — don't scatter literals.

## 3. Important models & relationships

- `User` (roles, permissions) → `Employee` (department, position) → Department.
- `Report` (tasks, employee, versions, files, references, reviews, approvals,
  timeline, comments, AI results) — the central aggregate.
- `Task`/`TaskAssignment`/`TaskProgress` — task management + review.
- `EmployeePerformanceRecord`/`PerformancePeriod`/`PerformanceScoreBreakdown` —
  scoring.
- `DataQualityScan`/`DataQualityIssue`/`DataQualityIssueHistory`.
- `AiChatConversation`/`AiChatMessage`/`AiChatToolCall`.
- `ReportExport`.

## 4. API conventions

- Base: `/api/v1`. Envelope: `{ success, message, data | errors }`.
- Auth: Sanctum SPA cookie (`/sanctum/csrf-cookie` then login).
- RBAC: route middleware (`permission:`, `role:`) + policies + service scoping.
- Resources define the exact JSON shape the frontend types expect — changing one
  requires updating `frontend/src/types/*` and service return types.

## 5. Database overview

- `acadtrack_db` (dev data), `acadtrack_db_test` (test isolation, set in
  `phpunit.xml`). **Never run `migrate:fresh` on `acadtrack_db`.**
- See [`DATABASE.md`](DATABASE.md) and [`database/erd.md`](database/erd.md).

## 6. Queue jobs

`backend/app/Jobs/*`:

- `Reports/ProcessReportOcrJob`, `ProcessReportFileOcr`, `AnalyzeReport*`
- `Ai/ProcessAiChatJob`, `Ai/GenerateAiInsightJob`
- `DataQuality/RunDataQualityScanJob`
- `Reporting/GenerateReportExportJob`
- `SendNotificationJob`

Queues use `QUEUE_CONNECTION=database`. Run `php artisan queue:work`; monitor
`php artisan queue:failed`.

## 7. Scheduled tasks (`routes/console.php`)

- `tasks:send-deadline-reminders` daily 07:00
- `tasks:send-overdue-notifications` daily 07:30
- `reports:generate-monthly` monthly on 1st at 06:00
- `reports:cleanup-exports` daily 03:15

Run with `php artisan schedule:work` locally; cron `schedule:run` in production.

## 8. AI integration

- Env flags: `AI_PROVIDER=fake|openai`, `AI_ENDPOINT`, `AI_API_KEY`, `AI_MODEL`,
  `AI_TIMEOUT`, `AI_MAX_TOKENS`, `AI_TEMPERATURE`.
- `fake` = deterministic offline engine (default; test-safe).
- Real provider: `HttpAiProvider` (OpenAI-compatible HTTP).
- All calls server-side; keys in `.env` only.

## 9. OCR integration

- `OCR_PROVIDER=fake|google_vision`; `GOOGLE_VISION_API_KEY` or
  `GOOGLE_APPLICATION_CREDENTIALS`.
- `GoogleVisionOcrService` uses the REST `annotateImage` API.
- Server-side only; credentials never in `frontend/`/`public/`.

## 10. Environment variables

See [`README.md`](../README.md) (Environment Setup) — the canonical list with
examples. `.env.example` for both apps holds safe placeholders.

## 11. Testing

Backend:

```bash
cd backend
php artisan test          # full suite (uses acadtrack_db_test, never acadtrack_db)
php artisan test --filter=ReportWorkflowTest
```

Frontend:

```bash
cd frontend
npm test                  # vitest
npm run lint              # oxlint (0 errors; 54 pre-existing warnings)
npm run build             # tsc -b + production build
```

**CI-style check:** run all three before shipping. Never weaken the `phpunit.xml`
isolation settings.

## 12. Deployment

Follow [`DEPLOYMENT.md`](DEPLOYMENT.md): env per environment → migrate (--force in
prod) → optimize → queue worker → scheduler → build frontend → verify health.

## 13. Known limitations

- Frontend bundle is a single ~846 kB chunk (code-splitting is a future
  enhancement; no runtime impact).
- XAMPP `mysql.exe`/MariaDB client quirks on some machines (use
  `php artisan`/PDO for DB ops; the MariaDB server itself is fine).
- Real AI/OCR depend on provider availability and quotas (offline fake engines are
  the default).
- Similarity scores and AI findings are indicators requiring human validation.
- `.env.testing` must not be introduced (it shadows `.env` key when
  `APP_ENV=testing`); keep test config in `phpunit.xml`.

## 14. Maintenance recommendations

- Keep migrations additive; never edit an applied migration.
- Always back up before applying migrations (see [`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md)).
- Follow [`MAINTENANCE.md`](MAINTENANCE.md) daily/weekly/monthly checklist.
- Update docs when you change behavior — the docs/ tree mirrors the system.