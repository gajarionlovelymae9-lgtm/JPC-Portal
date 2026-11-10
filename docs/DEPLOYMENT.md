# JPC Portal — Deployment Guide

This guide covers the complete deployment sequence for the JPC Portal (Laravel 12
backend + React/Vite frontend) across Development, Testing, Staging, and Production.

No environment-specific value is hard-coded in source code — everything is read from
`.env`. See [`XAMPP_SETUP.md`](XAMPP_SETUP.md) for the local Windows/XAMPP walkthrough
and [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) for common issues.

## 0. Environments at a glance

| Env | `APP_ENV` | DB | Notes |
| --- | --- | --- | --- |
| Development | `local` | `acadtrack_db` | XAMPP, dev seeders on |
| Testing (automated) | `testing` | `acadtrack_db_test` | driven by `phpunit.xml`, never touches production DB |
| Staging | `staging` | e.g. `acadtrack_db_staging` | mirrors production config, debug off (recommended) |
| Production | `production` | `acadtrack_db` | `APP_DEBUG=false`, optimizations on |

## 1. Clone / copy the project

```bash
git clone <repository-url> jpc-portal
cd jpc-portal
```

The repository contains `backend/`, `frontend/`, `docs/`, `README.md`, `.gitignore`.

## 2. Install backend dependencies

```bash
cd backend
composer install --no-dev --optimize-autoloader   # production
# or for local/staging: composer install
```

## 3. Configure `.env`

```bash
cp .env.example .env      # Windows: copy .env.example .env
php artisan key:generate  # MUST be run; APP_KEY is required
```

Set environment-specific values (see [`README.md`](../README.md) for the full table):

```
APP_ENV=local|staging|production
APP_DEBUG=true|false          # always false on production
APP_URL=http://localhost:8000 # public URL of the API

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=acadtrack_db
DB_USERNAME=root
DB_PASSWORD=...

CACHE_STORE=database          # or redis/array per environment
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=log|smtp         # log=dev, smtp=prod with SMTP_* values

OCR_PROVIDER=fake|google_vision   # see §AI/OCR below
AI_PROVIDER=fake|openai
AI_API_KEY=...                     # server-side only

CORS_ALLOWED_ORIGINS=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

> `.env` is ignored by git. Never commit it.

## 4. Create the database

```sql
CREATE DATABASE IF NOT EXISTS acadtrack_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 5. Run migrations

```bash
cd backend
php artisan migrate
```

**Production rule:** use `php artisan migrate --force` (see
["Safe migration procedure"](#safe-migration-procedure) below). **NEVER** use
`migrate:fresh` in production.

## 6. Seed (only when appropriate)

```bash
php artisan db:seed
```

- On local/staging this creates roles, permissions, departments, positions, the
  academic year, the four demo accounts and the fictional development dataset.
- **Production (and staging) must set `SEED_DEVELOPMENT_DATA=false` in `.env`
  explicitly.** The flag is the single gate for demo data and it **defaults to
  `true`** (`config/jpc.php` reads `env('SEED_DEVELOPMENT_DATA', true)`), so
  setting `APP_ENV=production` ALONE does **not** disable the development
  seeders.
- With `SEED_DEVELOPMENT_DATA=false`, `php artisan db:seed` runs only the
  **structural** seeders (roles, permissions, departments, positions, academic
  year, badges, AI prompt versions) and creates **no** demo users or fictional
  data. First real administrator accounts are then created via
  `php artisan tinker` or the admin API (see [`INSTALLATION.md`](INSTALLATION.md)
  §9).

## 7. Configure storage (private files)

```bash
cd backend
php artisan storage:link   # public symlinks if you use the public disk
```

- Uploaded report documents are stored on the **private** `local` disk and are
  served through authenticated controllers only — never through public URLs.
  The on-disk path is `storage/app/private/private/reports` (the report upload
  code stores under `private/reports/...` on the default `local` disk, whose
  root is already `storage/app/private`; both halves are private). Include this
  whole subtree in backups.
- Exports live under `storage/app/private/report_exports` and are cleaned by the
  daily `reports:cleanup-exports` task.

## 8. Configure queue

```bash
cd backend
php artisan queue:work
```

For production, run a persistent process/systemd service:

```ini
# /etc/systemd/system/jpc-queue.service
[Service]
WorkingDirectory=/var/www/jpc-portal/backend
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
```

## 9. Configure scheduler

For production add a cron entry:

```
* * * * * cd /var/www/jpc-portal/backend && php artisan schedule:run >> /dev/null 2>&1
```

For local development run `php artisan schedule:work`.

Scheduled tasks (defined in `routes/console.php`) — **seven** in total:

- 07:00 daily — `tasks:send-deadline-reminders`
- 07:30 daily — `tasks:send-overdue-notifications`
- 06:00 on the 1st — `reports:generate-monthly`
- 03:15 daily — `reports:cleanup-exports`
- 05:00 daily — `system:check-health` (writes `system_health_checks`)
- 05:30 daily — `system:verify-backups` (backup-freshness verdict)
- 06:30 Mondays — weekly full data-quality scan
  (`RunDataQualityScanJob('full', null, null)`)

All seven run through the single `schedule:run` cron entry (or
`schedule:work` in development); there is no separate cron per task.

## 10. Configure mail

Set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. If mail is unavailable the application logs
the failure and keeps the notification record (it does not crash).

## 11. Configure AI

Backend `.env`:

```
AI_PROVIDER=fake          # deterministic offline engine (default, works offline)
# or:
AI_PROVIDER=openai
AI_ENDPOINT=https://api.openai.com/v1   # or any OpenAI-compatible endpoint
AI_API_KEY=sk-...
AI_MODEL=gpt-4o-mini      # any model id
AI_TIMEOUT=120
```

All AI calls happen on the backend. Keys are never exposed to the browser.

## 12. Configure OCR (Google Vision, optional)

```
OCR_PROVIDER=google_vision
GOOGLE_VISION_API_KEY=...        # REST key route
# or service account:
GOOGLE_CLOUD_PROJECT_ID=...
GOOGLE_APPLICATION_CREDENTIALS=/secure/path/credentials.json
```

Credentials must be stored outside `frontend/`, `public/`, and the git repository.
OCR runs server-side only. `OCR_PROVIDER=fake` is the offline default.

## 13. Install & build the frontend

```bash
cd frontend
npm install
npm run build              # outputs to frontend/dist
```

Check the API URL (`frontend/.env`):

```
VITE_API_BASE_URL=/api/v1          # behind the same origin/proxy
# or, for a separately hosted API:  VITE_API_BASE_URL=https://api.example.com/api/v1
```

The build must complete with no errors. For local dev, use `npm run dev`
(Vite proxies `/api` and `/sanctum` to `http://127.0.0.1:8000`).

## 14. Serve the backend

```bash
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

For production, host through Apache/Nginx; configure:

- document root → `backend/public`
- a proxy/alias for `/api`, `/sanctum` and the frontend assets to the SPA build
- HTTPS (TLS) and CORS origins matching the SPA URL

## 15. Serve the frontend (SPA)

For production, serve `frontend/dist` as static content (Apache/Nginx). Configure
URL rewriting so all non-asset routes serve `index.html` (SPA fallback) OR serve the
SPA through the same host as the API while keeping `/api` routed to Laravel.

## 16. Verify the application

1. `GET /api/v1/health` returns `{ success: true, message: "JPC Portal API is running", ... }`.
2. Log in with each role (see README demo accounts).
3. Open the SP dashboard; confirm loading, empty, error and success states.
4. Submit a report (fake AI/OCR runs offline) and confirm the pipeline completes.
5. Run one queue worker + `schedule:work` and confirm notifications/exports flow.

## 17. Laravel production optimizations

Run after configuring `.env` and confirming settings:

```bash
cd backend
php artisan optimize            # config, routes, events, views cache
php artisan config:cache        # explicit config cache
php artisan route:cache         # explicit route cache
```

> Run these on the deployed host. Do NOT run `config:cache` with a wrong `.env`
> (stale config) and do not run it in a way that caches environment-specific values
> across hosts. Clearing: `php artisan optimize:clear`.

## 18. Safe migration procedure (production)

```bash
cd backend
php artisan down                       # optional maintenance mode
# 1. Back up the database FIRST (see BACKUP_AND_RESTORE.md)
# 2. Deploy the new code (composer install occurs in release dir)
php artisan config:cache
php artisan migrate --force
# 3. Verify:
php artisan migrate:status             # all rows show Ran
# 4. Bring the app back up, then clear/refresh caches as needed
php artisan up
```

**Why `--force`?** In a non-local environment Laravel blocks migrations in
production; `--force` is the explicit confirmation that the operator intends to run
them there. It does not bypass backups — a backup must be taken first.

**Why never `migrate:fresh`?** It drops every table and loses data. Always use
`php artisan migrate`.

**Verifying success:** `php artisan migrate:status` lists every migration with
`Ran`/`Pending`; all entries should be `Ran`; spot-check new tables exist:
`SHOW TABLES LIKE '%report_exports%'`.

## 19. Production deployment checklist

Run through this before declaring a go-live, together with
[`GO_LIVE_CHECKLIST.md`](GO_LIVE_CHECKLIST.md):

- [ ] `backend/.env` exists with correct values; `APP_KEY` generated;
      `APP_DEBUG=false`.
- [ ] **`SEED_DEVELOPMENT_DATA=false` is set** (defaults to `true` — do not
      rely on `APP_ENV=production` alone).
- [ ] `composer install --no-dev --optimize-autoloader` completed.
- [ ] Database created; `php artisan migrate --force` applied; `migrate:status`
      all `Ran`.
- [ ] `php artisan db:seed --force` (structural only on production) — or the
      first administrator created via tinker/admin API.
- [ ] `php artisan storage:link` completed; private storage subtree exists.
- [ ] `php artisan optimize` ran with the correct `.env`.
- [ ] Queue worker running (`queue:work` persistent service).
- [ ] Cron installed for `schedule:run`; `php artisan schedule:list` shows the
      seven tasks.
- [ ] Frontend `npm run build` completed with no errors; `frontend/dist` served
      with SPA fallback; `/api` proxied to Laravel.
- [ ] CORS/Sanctum origins match the SPA URL; HTTPS enabled.
- [ ] Backup job + off-machine copies configured and verified (see
      [`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md)).
- [ ] `php artisan system:check-health` returns ok.
- [ ] Exit criteria recorded in the maintenance log (type `Release`).

## 20. Deployment smoke test

After deploy, on the production URL:

1. `GET /api/v1/health` → `{ "success": true }`.
2. Log in as each deployed role; open the dashboard.
3. Create a draft report, upload a PDF, submit it → status moves to
   `submitted`.
4. Reviewer (HR/Dept Head/Admin) approves → status `approved`; check the
   timeline shows each step.
5. Queue worker processed the jobs (`php artisan queue:failed` empty; queue
   size returns to 0 after processing).
6. Create an export → confirm it becomes `ready` and downloads.
7. Confirm one notification was delivered/stored without error.

If any step fails, see [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) and the
`storage/logs/laravel-*.log` files, then fix before go-live.