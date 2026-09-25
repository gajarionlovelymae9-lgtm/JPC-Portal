# JPC Portal — Troubleshooting Guide

Common problems and how to resolve them.

---

## MySQL shuts down unexpectedly (XAMPP)

**Symptoms:** XAMPP Control Panel shows "MySQL shutdown unexpectedly"; the MySQL
service stops shortly after starting.

**Diagnosis (in order):**

1. Open `C:\xampp\mysql\data\*.err` (e.g. `YOURCOMPUTER.err`) and read the last
   lines. Look for permission errors, an InnoDB crash, a port conflict, or a corrupt
   `ibdata1`/table.
2. Check the Windows Event Viewer (Application log) for port or privilege errors.
3. Verify port 3306 is free (see "Port conflict" below).

**Safe recovery:**

- Corrupt shutdown: stop MySQL in the control panel, then start it again; XAMPP
  usually recovers on the second attempt.
- Corrupt InnoDB log: stop MySQL, remove/rename the `ib_logfile0`/`ib_logfile1`
  (only when MySQL is stopped and you have a backup), restart.
- Permission error: run the XAMPP control panel as Administrator.
- Data dir corruption: restore `C:\xampp\mysql\data` from backup (see
  [`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md)) — never delete data blindly.

---

## Port conflict

**Symptoms:** Apache or MySQL fails to start; `Bind to port ... failed`.

**Identify the conflicting process:**

```bash
netstat -ano | findstr :80        # Apache
netstat -ano | findstr :443
netstat -ano | findstr :3306      # MySQL
netstat -ano | findstr :8000      # php artisan serve
netstat -ano | findstr :5173      # Vite dev server
```

Then map the PID to a program:

```bash
tasklist | findstr <PID>
```

**Fix:** stop the conflicting service, or change the port in `XAMPP\apache\conf\httpd.conf`
(`Listen 80` → another port), MySQL `my.ini` (`port=3306`), or launch
`php artisan serve --port=8001`. Remember to update `VITE_API_BASE_URL` /
`CORS_ALLOWED_ORIGINS` / `SANCTUM_STATEFUL_DOMAINS` if you change ports.

---

## Laravel cannot connect to the database

Check `backend/.env` first, then that the DB actually exists:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=acadtrack_db
DB_USERNAME=root
DB_PASSWORD=...
```

Then:

1. Is MySQL running in the XAMPP Control Panel? Start it.
2. Does the database exist?
   ```sql
   SHOW DATABASES LIKE 'acadtrack_db';
   ```
3. Wrong credentials: XAMPP's default `root` has an empty password.
4. After editing `.env` clear cached config:
   ```bash
   cd backend && php artisan config:clear && php artisan cache:clear
   ```
5. Confirm you're not accidentally pointing at the test DB:
   automated tests force `DB_DATABASE=acadtrack_db_test` via `phpunit.xml`.

---

## Composer problems

**Symptoms:** `composer: command not found`, `composer.lock out of date`,
`PHP version not supported`.

Checks:

```bash
composer -V
php -v              # must be >= 8.2
composer diagnose
```

Fix:

- Re-run `composer install` in `backend/`.
- If `composer.lock` mismatches `composer.json`, run `composer update` only when
  intentionally upgrading (this changes `composer.lock`).
- Clear the local cache: `composer clear-cache`.
- Offline/registry errors: verify connectivity or use a mirror repository.

---

## npm problems

**Symptoms:** `npm install` fails, `vite: command not found`, or build errors.

```bash
cd frontend
node -v                    # >= 20
npm -v
rm -rf node_modules package-lock.json   # clean install (or Remove-Item -Recurse)
npm install
npm run build
```

If `npm run build` fails on types run first:

```bash
npx tsc -b
npx oxlint src
```

(54 oxlint warnings are pre-existing and non-blocking; 0 errors is the target.)

---

## OCR failure

**Symptoms:** uploaded document stuck in `processing`, or OCR status `failed`.

Check:

1. `OCR_PROVIDER` in `.env`:
   - `fake` — offline deterministic engine; no credentials needed. Default.
   - `google_vision` — requires credentials.
2. For `google_vision`:
   - `GOOGLE_VISION_API_KEY` set (REST `annotateImage` route), **or**
   - `GOOGLE_APPLICATION_CREDENTIALS` points to a service-account JSON path that
     exists and is readable.
   - `GOOGLE_CLOUD_PROJECT_ID` set for the service-account route.
   - The Vision API is enabled on the Google Cloud project.
3. File type/size: `REPORT_MAX_FILE_SIZE_KB` and supported mime types.
4. Retry: the queue may have a failed job:
   ```bash
   php artisan queue:work       # worker
   php artisan queue:retry all  # retry failed jobs
   php artisan queue:failed     # list failures
   ```
5. Logs: `backend/storage/logs/laravel-*.log`.

Credentials must live only in the server `.env` / an outside-repo path — never in
`frontend/` or `public/`.

---

## AI failure

**Symptoms:** summary/validation/insight missing or status `failed`.

Check:

1. `AI_PROVIDER` in `.env`:
   - `fake` (default) — offline, works with no network/account.
   - `openai` or an OpenAI-compatible endpoint — requires `AI_API_KEY` (+ optional
     `AI_ENDPOINT`, `AI_MODEL`, `AI_TIMEOUT`).
2. For a real provider: key validity, endpoint reachability, quota, and model id.
3. Timeouts: increase `AI_TIMEOUT` (seconds).
4. Logs: `backend/storage/logs/laravel-*.log` (search for `ai_reports`/`HttpAiProvider`).
5. Requeue the analysis job for the report, or run:
   ```bash
   php artisan queue:retry all
   ```
6. AI requests originate only from the backend — if the key is missing in `.env`
   but present in `frontend/`, it is in the wrong place.

---

## Queue not processing

**Symptoms:** OCR/AI/exports/notifications stay pending.

Check:

1. Worker running?
   ```bash
   cd backend && php artisan queue:work
   ```
   Local: keep a terminal open (or use `queue:listen`). Production: a
   systemd/supervisor service (see [`DEPLOYMENT.md`](DEPLOYMENT.md)).
2. `QUEUE_CONNECTION` matches the intended driver (`database` by default; `sync`
   for tests). If you edited `.env`, clear config:
   ```bash
   php artisan config:clear
   ```
3. Queue tables exist:
   ```sql
   SHOW TABLES LIKE 'jobs';
   ```
   Run `php artisan migrate` if missing (Laravel's `jobs` migration).
4. Inspect failed jobs:
   ```bash
   php artisan queue:failed
   php artisan queue:retry all
   ```

---

## Notifications not sent

**Symptoms:** in-app notification missing, or email never arrives.

Check:

1. **Queue**: is the worker running? (see "Queue not processing").
2. **Mail config**: `MAIL_MAILER` — `log` writes to `storage/logs` (dev-friendly);
   `smtp` requires `MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_*`.
3. **Preferences**: the recipient's `notification_preferences` may disable a channel.
4. **Traceability**: `notification_logs` records channel/status/error — check whether
   the notification is `failed` and read its error.
5. If SMTP is unreachable the app should not crash — confirm the error is logged
   and the in-app notification still exists.

---

## Vite proxy / 401 / CSRF errors on login

- Confirm the Vite dev server is running and proxies `/api` and `/sanctum` to
  `http://127.0.0.1:8000` (`frontend/vite.config.ts`).
- Confirm `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` include your SPA
  origin (`localhost:5173,127.0.0.1:5173`).
- After changing `.env`, run `php artisan config:clear`.
- Login fails with 500? Check `APP_KEY` is set (`php artisan key:generate`) —
  `.env.testing` (if present) shadows `.env` when `APP_ENV=testing`, so keep test
  configuration in `phpunit.xml`.

---

## Still stuck?

1. `backend/storage/logs/laravel-*.log` — newest entry first.
2. `php artisan about` — environment summary.
3. `php artisan route:list` — routes loaded.
4. Re-run tests: `cd backend && php artisan test`.
5. Open an issue with the log excerpt (redact secrets).