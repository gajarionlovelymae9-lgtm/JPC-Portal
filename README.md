# JPC Portal — AI-Driven Employee Progress Monitoring System

**JPC Portal** is an AI-driven employee progress monitoring and management platform
for **John Paul College**: annual plans → targets → tasks → reports → OCR/AI
validation → review/approval → performance → achievements, with data-quality
scans, reporting/analytics, report exports, AI Chat, notifications, and a full
audit trail.

| | |
| --- | --- |
| **Version** | v1.0.0 (final release 2026-09-25) |
| **Status** | READY (see [docs/GO_LIVE_CHECKLIST.md](docs/GO_LIVE_CHECKLIST.md)) |
| **License** | Internal / academic |

> **New to the system?** Follow [docs/INSTALLATION.md](docs/INSTALLATION.md) for a
> complete fresh-machine install, and [docs/RELEASE_NOTES.md](docs/RELEASE_NOTES.md)
> for the v1.0.0 release notes.

## Features

- Role-based portals: **Admin**, **HR**, **Department Head**, **Employee**
- Employee / department / position management with history
- Academic year → annual plan → target (department + employee) planning
- Task management (assignments, progress, references, comments, review)
- Report workflow (draft → submitted → under review → approved / revision / rejected)
  with immutable versions, files, timeline, reviewer comments
- **OCR** document processing (offline fake engine by default; optional Google Vision)
- **AI analysis** of reports: summary, validation, reference & similarity indicators,
  findings, insights (offline fake engine by default; optional OpenAI-compatible)
- **Performance** records, score breakdowns, progress snapshots, KPIs
- **Achievements**: badges, monthly achievements, employee of the month
- **Data quality** scans and issue lifecycle
- **Reporting**: employee/department/institution/monthly/achievements/late/pending/
  targets/data-quality/management summary
- **Analytics**: trends, comparisons, rankings, performance distribution
- **Report exports** (CSV / XLSX / PDF, queued, owner-scoped, TTL-managed)
- **AI Chat** with authorized, role-scoped analytical tools
- **Notifications** (in-app + optional mail), preferences, delivery logs
- **Audit & activity logs**
- Private file storage with authorized downloads; full RBAC + IDOR protection

## Technology stack

| Layer | Technology |
| --- | --- |
| Frontend | React 19 + Vite + TypeScript |
| Backend | Laravel 12 (PHP 8.2, RESTful JSON API) |
| Database | MySQL 8 (XAMPP MariaDB compatible), `acadtrack_db` |
| Auth | Laravel Sanctum SPA cookies + roles/permissions |
| Queues | Laravel database queue (`QUEUE_CONNECTION=database`) |
| AI / OCR | Server-side providers (fake offline engine, OpenAI-compatible, Google Vision) |
| Tests | PHPUnit (backend), Vitest + Testing Library (frontend) |

## Requirements

- PHP >= 8.2 (XAMPP recommended on Windows)
- Composer 2.x
- Node.js >= 20 and npm
- MySQL 8 / MariaDB >= 10.4 running on `127.0.0.1:3306`

See [docs/XAMPP_SETUP.md](docs/XAMPP_SETUP.md) for a full local setup walkthrough.

## Installation

### 1. Database

```sql
CREATE DATABASE IF NOT EXISTS acadtrack_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS acadtrack_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; -- for the test suite
```

### 2. Backend (Laravel)

```bash
cd backend
composer install
copy .env.example .env                 # Windows; `cp .env.example .env` on Linux/macOS
php artisan key:generate
# edit .env → DB_DATABASE=acadtrack_db, DB_USERNAME, DB_PASSWORD, CORS/SANCTUM origins
php artisan migrate
php artisan db:seed                     # roles + permissions + demo accounts + sample data (dev only;
                                        # set SEED_DEVELOPMENT_DATA=false on non-development)
php artisan serve                       # → http://localhost:8000
```

> `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` must include your SPA origin
> (`localhost:5173,127.0.0.1:5173`).

### 3. Frontend (React)

```bash
cd frontend
npm install
copy .env.example .env
npm run dev                             # → http://localhost:5173 (proxies /api + /sanctum)
```

Open **http://localhost:5173**. The Vite dev proxy routes `/api` and `/sanctum` to
`http://127.0.0.1:8000`, so Sanctum cookie auth works from a single origin.

### 4. Queues & scheduler (for notifications / OCR / exports)

```bash
cd backend
php artisan queue:work        # keep running in a terminal (dev)
php artisan schedule:work     # local scheduler (dev)
```

Production uses a persistent worker + cron; see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Database setup

`acadtrack_db` is managed entirely by Laravel migrations
(`backend/database/migrations/`). Tests use an isolated `acadtrack_db_test`
(configured in `backend/phpunit.xml`) — automated tests can never destroy
development data. **Never run `migrate:fresh` on `acadtrack_db`.**

See [docs/DATABASE.md](docs/DATABASE.md) and [docs/database/erd.md](docs/database/erd.md).

## Environment configuration

### Backend (`backend/.env`)

| Variable | Description | Example |
| --- | --- | --- |
| `APP_NAME` | App name | `JPC Portal API` |
| `APP_ENV` | `local` / `staging` / `production` | `local` |
| `APP_KEY` | Encryption key (via `key:generate`) | *(generated)* |
| `APP_URL` | Public API URL | `http://localhost:8000` |
| `DB_CONNECTION` | Driver | `mysql` |
| `DB_HOST` / `DB_PORT` | DB host / port | `127.0.0.1` / `3306` |
| `DB_DATABASE` | Database | `acadtrack_db` |
| `DB_USERNAME` / `DB_PASSWORD` | DB credentials | `root` / *(empty on XAMPP)* |
| `CACHE_STORE` | Cache driver | `database` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `FILESYSTEM_DISK` | Storage disk | `local` |
| `MAIL_MAILER` | `log` (dev) / `smtp` | `log` |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` | SMTP | `127.0.0.1` / `2525` / `null` / `null` |
| `CORS_ALLOWED_ORIGINS` | SPA origins | `http://localhost:5173,http://127.0.0.1:5173` |
| `SANCTUM_STATEFUL_DOMAINS` | Cookie-auth hosts | `localhost:5173,127.0.0.1:5173` |
| `SESSION_DRIVER` | Session store | `database` |

### AI / OCR configuration

```
# Offline deterministic engines (default — no accounts, works offline):
OCR_PROVIDER=fake
AI_PROVIDER=fake

# Optional real AI (any OpenAI-compatible endpoint):
AI_PROVIDER=openai
AI_ENDPOINT=https://api.openai.com/v1
AI_API_KEY=sk-...
AI_MODEL=gpt-4o-mini
AI_TIMEOUT=120
AI_MAX_TOKENS=2048

# Optional Google Vision OCR:
OCR_PROVIDER=google_vision
GOOGLE_VISION_API_KEY=...                    # REST key route
# or service account:
GOOGLE_CLOUD_PROJECT_ID=...
GOOGLE_APPLICATION_CREDENTIALS=C:\secure\credentials.json
```

**AI and OCR always run server-side.** Keys live only in the server `.env` (never in
`frontend/`, `public/`, or git). See [docs/AI_GOVERNANCE.md](docs/AI_GOVERNANCE.md).

### Frontend (`frontend/.env`)

| Variable | Description | Example |
| --- | --- | --- |
| `VITE_APP_NAME` | Display name | `JPC Portal` |
| `VITE_API_BASE_URL` | API base | `http://localhost:8000/api/v1` (dev, proxied) |

## Running the application

```bash
# Terminal 1 — backend
cd backend && php artisan serve          # http://localhost:8000

# Terminal 2 — queue worker (reports/OCR/exports/notifications)
cd backend && php artisan queue:work

# Terminal 3 — scheduler (deadline reminders, monthly report, cleanup)
cd backend && php artisan schedule:work

# Terminal 4 — frontend
cd frontend && npm run dev               # http://localhost:5173
```

Health check: `GET http://localhost:8000/api/v1/health`.

## Default / demo accounts

Seeded by `php artisan db:seed` (development only — **change on any real
deployment**):

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@jpc.local` | `P@ssw0rd2026` |
| HR | `hr@jpc.local` | `P@ssw0rd2026` |
| Department Head | `departmenthead@jpc.local` | `P@ssw0rd2026` |
| Employee | `employee@jpc.local` | `P@ssw0rd2026` |

Passwords are stored hashed. Development passwords come from
`SEED_ADMIN_PASSWORD`, `SEED_HR_PASSWORD`, `SEED_DEPARTMENT_HEAD_PASSWORD`,
`SEED_EMPLOYEE_PASSWORD` (fallback `P@ssw0rd2026`). The development seeders are
gated by `SEED_DEVELOPMENT_DATA`, which **defaults to `true`** — production
must set `SEED_DEVELOPMENT_DATA=false` explicitly (setting `APP_ENV=production`
alone does not disable them). The demo dataset is entirely fictional.

## Testing

```bash
# Backend (uses isolated acadtrack_db_test)
cd backend && php artisan test           # 406 passed / 1880 assertions

# Frontend
cd frontend && npm test                  # 87 passed (Vitest)
cd frontend && npm run lint              # oxlint — 0 errors (58 pre-existing benign warnings)
cd frontend && npm run build             # tsc -b + production build
```

## Deployment

Follow [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md): per-environment `.env` → migrate
(`--force` in production; never `migrate:fresh`) → `php artisan optimize` → queue
worker + scheduler → frontend `npm run build` → verify. Backups/restore:
[docs/BACKUP_AND_RESTORE.md](docs/BACKUP_AND_RESTORE.md). Maintenance:
[docs/MAINTENANCE.md](docs/MAINTENANCE.md).

## Monitoring & maintenance (Phase 13)

The system ships with built-in monitoring and maintenance tooling:

- **System Health dashboard** (admin): `/admin/system-health` — application,
  database, queue, storage, AI provider, OCR provider, and notification status,
  plus failed-job counters, security/auth-failure signals, and redacted recent
  critical errors.
- **Health API** (admin only): `GET /api/v1/admin/system-health` and per-component
  endpoints (`/database`, `/queue`, `/storage`, `/ai`, `/ocr`, `/history`).
- **Maintenance log** (admin + CLI): record and audit every maintenance action via
  `/admin/maintenance` or `php artisan system:record-maintenance`.
- **Backup verification** (guard): `php artisan system:verify-backups` — never
  claims a backup is valid unless a restore was actually logged.
- **Feedback**: any user submits via `/feedback` (bug / incorrect information /
  UI / AI / OCR / feature request); admins manage in `/admin/feedback`.
- **AI quality feedback**: users rate AI output (`POST /api/v1/feedback/ai`);
  verdicts are recorded **without** altering original AI results or scores.
- **Scheduled monitoring**: daily 05:00 `system:check-health`, 05:30
  `system:verify-backups`, weekly Mondays a full queued data-quality scan.

Operations runbooks: [docs/operations/](docs/operations) (monitoring,
incident-response, backup-recovery, maintenance). Maintenance runbooks:
[docs/maintenance/](docs/maintenance) (monitoring, backup-restore,
incident-response, troubleshooting, security, AI, OCR, database, release-process,
monthly checklist). Change log: [CHANGELOG.md](CHANGELOG.md).

## Known limitations (v1.0.0)

- Real AI/OCR depend on provider availability/network/quota; offline `fake` engines
  are the default so the pipeline works with no external accounts.
- OCR accuracy depends on document quality.
- Similarity detection is an indicator — **not** proof of plagiarism — and requires
  human validation.
- AI findings are assistive; humans make every approval/rejection decision.
- Email delivery depends on mail configuration (optional; failures log, never crash).
- Frontend ships as a single ~869 kB chunk (code-splitting is a documented
  future enhancement; no runtime impact).
- Scale/performance depends on infrastructure (XAMPP single-machine default).

## Troubleshooting

See [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) for MySQL shutdowns, port
conflicts, DB connection issues, Composer/npm problems, OCR/AI failures, queue and
notification problems.

## Documentation

| Topic | Doc |
| --- | --- |
| Installation (fresh machine) | [docs/INSTALLATION.md](docs/INSTALLATION.md) |
| Architecture | [docs/FINAL_ARCHITECTURE.md](docs/FINAL_ARCHITECTURE.md) |
| Database & ERD | [docs/DATABASE.md](docs/DATABASE.md), [docs/database/erd.md](docs/database/erd.md) |
| Deployment | [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), [docs/XAMPP_SETUP.md](docs/XAMPP_SETUP.md) |
| Backup & restore | [docs/BACKUP_AND_RESTORE.md](docs/BACKUP_AND_RESTORE.md) |
| Maintenance | [docs/MAINTENANCE.md](docs/MAINTENANCE.md), [docs/maintenance-log.md](docs/maintenance-log.md), [docs/maintenance/](docs/maintenance) (monitoring, backup-restore, incident-response, troubleshooting, security, AI, OCR, database, release-process, monthly-checklist) |
| Operations (monitoring / incidents / backup / maintenance) | [docs/operations/](docs/operations) ([monitoring.md](docs/operations/monitoring.md), [incident-response.md](docs/operations/incident-response.md), [backup-recovery.md](docs/operations/backup-recovery.md), [maintenance.md](docs/operations/maintenance.md)) |
| Readiness record | [docs/maintenance/maintenance-readiness.md](docs/maintenance/maintenance-readiness.md) |
| Continuous improvement | [docs/BACKLOG.md](docs/BACKLOG.md) |
| Release notes & versioning | [docs/RELEASE_NOTES.md](docs/RELEASE_NOTES.md), [docs/VERSION_MANAGEMENT.md](docs/VERSION_MANAGEMENT.md) |
| Change log | [CHANGELOG.md](CHANGELOG.md) |
| Troubleshooting | [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) |
| Security | [docs/SECURITY.md](docs/SECURITY.md) |
| AI Governance | [docs/AI_GOVERNANCE.md](docs/AI_GOVERNANCE.md) |
| Privacy | [docs/DATA_PRIVACY.md](docs/DATA_PRIVACY.md) |
| Employee user guide | [docs/user-guides/employee-guide.md](docs/user-guides/employee-guide.md), [docs/EMPLOYEE_GUIDE.md](docs/EMPLOYEE_GUIDE.md) |
| Management user guide (Admin / HR / Dept-Heads) | [docs/user-guides/management-guide.md](docs/user-guides/management-guide.md), [docs/ADMIN_GUIDE.md](docs/ADMIN_GUIDE.md), [docs/HR_GUIDE.md](docs/HR_GUIDE.md), [docs/DEPARTMENT_HEAD_GUIDE.md](docs/DEPARTMENT_HEAD_GUIDE.md) |
| Handovers | [docs/DEVELOPER_HANDOVER.md](docs/DEVELOPER_HANDOVER.md), [docs/ADMIN_HANDOVER.md](docs/ADMIN_HANDOVER.md) |
| Capstone overview | [docs/CAPSTONE_SYSTEM_OVERVIEW.md](docs/CAPSTONE_SYSTEM_OVERVIEW.md) |
| Demo script | [docs/FINAL_DEMO_SCRIPT.md](docs/FINAL_DEMO_SCRIPT.md) |
| Go-live checklist | [docs/GO_LIVE_CHECKLIST.md](docs/GO_LIVE_CHECKLIST.md) |
| Future work | [docs/FUTURE_ENHANCEMENTS.md](docs/FUTURE_ENHANCEMENTS.md) |