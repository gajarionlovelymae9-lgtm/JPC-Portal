# JPC Portal — Installation Guide

Fresh-install procedure for a new machine (developer machine or server). This
guide takes you from nothing to a running JPC Portal with all four roles able to
log in. It is the formal installation procedure for **JPC Portal v1.0.0**.

Related documents:

- [`XAMPP_SETUP.md`](XAMPP_SETUP.md) — local Windows/XAMPP environment setup.
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — staged production deployment (same
  commands, environment-specific values).
- [`README.md`](../README.md) — quick start and environment variable reference.
- [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) — common problems and fixes.

---

## 1. Prerequisites

| Requirement | Version | Check |
| --- | --- | --- |
| PHP (CLI) | >= 8.2 | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | >= 20 | `node -v` |
| npm | matches Node | `npm -v` |
| MySQL / MariaDB server | MySQL 8 / MariaDB >= 10.4 | service running on `127.0.0.1:3306` |
| Git (optional) | any | `git --version` |

On Windows, the XAMPP bundle (`C:\xampp`) provides PHP 8.2 and MySQL 8 together;
see [`XAMPP_SETUP.md`](XAMPP_SETUP.md).

---

## 2. Obtain the project

```bash
git clone <repository-url> jpc-portal
cd jpc-portal
```

Or copy the release package. The project must arrive complete with:

```
jpc-portal/
├── backend/          # Laravel 12 REST API
├── frontend/         # React 19 + Vite SPA
├── docs/             # full documentation set
├── README.md
├── CHANGELOG.md
├── VERSION
└── .gitignore
```

> The project does **not** include a `database/` directory at the root — the
> schema is managed entirely by Laravel migrations in
> `backend/database/migrations/` (78 migrations). Do not import any raw SQL dump
> unless explicitly instructed; `php artisan migrate` builds the schema.

---

## 3. Create the databases

Create the application database and the isolated test database:

```sql
CREATE DATABASE IF NOT EXISTS acadtrack_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS acadtrack_db_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

- `acadtrack_db` — the live application database.
- `acadtrack_db_test` — **required** only to run the automated backend test
  suite; `phpunit.xml` points `DB_DATABASE=acadtrack_db_test`, so automated
  tests can never touch the development database.

Use credentials from your MySQL instance (XAMPP default: user `root`, empty
password).

---

## 4. Backend (Laravel 12)

### 4.1 Install dependencies

```bash
cd backend
composer install
```

### 4.2 Environment configuration

```bash
copy .env.example .env        # Windows
# cp .env.example .env        # Linux / macOS
```

Generate the application key (required — encryption and cookie signing depend
on it):

```bash
php artisan key:generate
```

### 4.3 Configure `.env`

Edit `backend/.env` and set at minimum:

| Variable | Development example | Notes |
| --- | --- | --- |
| `APP_NAME` | `JPC Portal API` | leave default |
| `APP_ENV` | `local` | `staging`/`production` per environment |
| `APP_DEBUG` | `true` | **must be `false` in production** |
| `APP_URL` | `http://localhost:8000` | public API URL |
| `DB_DATABASE` | `acadtrack_db` | |
| `DB_USERNAME` | `root` | |
| `DB_PASSWORD` | *(empty on XAMPP)* | |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173,http://127.0.0.1:5173` | must include your SPA origin |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,127.0.0.1:5173` | must include your SPA origin |
| `QUEUE_CONNECTION` | `database` | default |
| `CACHE_STORE` | `database` | default |
| `SESSION_DRIVER` | `database` | default |
| `OCR_PROVIDER` | `fake` | offline deterministic engine |
| `AI_PROVIDER` | `fake` | offline deterministic engine |
| `MAIL_MAILER` | `log` | dev; `smtp` in production |

See [`README.md`](../README.md) "Environment configuration" for the full
variable table and AI/OCR options.

### 4.4 Sample data policy

The development seeders insert fictional demo datasets. They are gated by the
single flag `SEED_DEVELOPMENT_DATA`:

- **Default is `true`** (`config/jpc.php`: `env('SEED_DEVELOPMENT_DATA', true)`).
- **On any environment that is not a development sandbox, set
  `SEED_DEVELOPMENT_DATA=false` explicitly.** Setting `APP_ENV=production` alone
  does **not** disable them.
- Development-only passwords come from `SEED_ADMIN_PASSWORD` /
  `SEED_HR_PASSWORD` / `SEED_DEPARTMENT_HEAD_PASSWORD` / `SEED_EMPLOYEE_PASSWORD`
  (fallback `P@ssw0rd2026`). These accounts exist **only** when development
  seeding runs and must be changed/deleted on any real deployment.

For a production-grade start you can run **only the structural seeders**
(roles, permissions, departments, positions, academic years, badges, AI prompt
versions) without demo data; see [`DEPLOYMENT.md`](DEPLOYMENT.md) §6.

### 4.5 Run migrations

```bash
php artisan migrate
```

Production: `php artisan migrate --force`. **Never** run `migrate:fresh` on
`acadtrack_db` — it drops every table. Verify with:

```bash
php artisan migrate:status     # all rows should show Ran
```

### 4.6 Optional: seed roles/permissions/demo data

```bash
php artisan db:seed
```

With `SEED_DEVELOPMENT_DATA=false` this runs only the structural seeders
(roles, permissions, departments, positions, academic years, badges, AI prompt
versions) — **no** demo users or fictional data.

### 4.7 Storage

```bash
php artisan storage:link       # creates public/storage -> storage/app/public
```

Uploaded report documents are stored on the private `local` disk and are served
only through authorized download routes.

### 4.8 Serve the backend

```bash
php artisan serve              # http://localhost:8000
```

Health check: `GET http://localhost:8000/api/v1/health` returns
`{ "success": true, ... }`.

---

## 5. Frontend (React 19)

### 5.1 Install dependencies

```bash
cd frontend
npm install
```

### 5.2 Environment configuration

```bash
copy .env.example .env         # Windows
# cp .env.example .env         # Linux / macOS
```

The frontend `.env` contains no secrets (values are compiled into the static
bundle):

| Variable | Development example |
| --- | --- |
| `VITE_APP_NAME` | `JPC Portal` |
| `VITE_API_BASE_URL` | `http://localhost:8000/api/v1` |

### 5.3 Development server

```bash
npm run dev                    # http://localhost:5173
```

The Vite dev server proxies `/api` and `/sanctum` to `http://127.0.0.1:8000`
(`frontend/vite.config.ts`), so Sanctum SPA cookie authentication works from a
single origin.

### 5.4 Production build

```bash
npm run build                  # tsc -b && vite build  -> frontend/dist
```

`npm run lint` runs oxlint (0 errors is the target; pre-existing benign
warnings are acceptable — 58 at v1.0.0).

---

## 6. Queues and scheduler

The application uses the **database queue** driver by default
(`QUEUE_CONNECTION=database`). OCR, AI analysis, exports, notifications and the
weekly data-quality scan all run as queued jobs.

```bash
# Terminal 1 — queue worker
cd backend
php artisan queue:work

# Terminal 2 — scheduler (local)
php artisan schedule:work
```

Production uses a persistent worker (systemd/supervisor) and cron
(`php artisan schedule:run` every minute); see [`DEPLOYMENT.md`](DEPLOYMENT.md)
§8–§9.

Failed jobs are stored in the `failed_jobs` table (`database-uuids` driver):

```bash
php artisan queue:failed       # list
php artisan queue:retry all    # retry all failed
php artisan queue:flush        # discard all failed
```

---

## 7. AI configuration (optional)

Default is the offline `fake` engine — no configuration required. To use a real
OpenAI-compatible provider, set in `backend/.env`:

```
AI_PROVIDER=openai
AI_ENDPOINT=https://api.openai.com/v1
AI_API_KEY=sk-...              # server-side only — never in frontend/git
AI_MODEL=gpt-4o-mini
AI_TIMEOUT=120
AI_MAX_TOKENS=2048
```

AI runs server-side; availability is external and provider failures are logged —
the application stays usable (see [`AI_GOVERNANCE.md`](AI_GOVERNANCE.md)).

---

## 8. Google Vision OCR configuration (optional)

Default is the offline `fake` engine — no configuration required. To enable
Google Cloud Vision, set in `backend/.env`:

```
OCR_PROVIDER=google_vision
GOOGLE_VISION_API_KEY=...              # REST key route
# or a service account:
GOOGLE_CLOUD_PROJECT_ID=...
GOOGLE_APPLICATION_CREDENTIALS=C:\secure\credentials.json
```

Credentials must be stored outside `frontend/`, `public/`, and the repository.
OCR runs server-side only.

---

## 9. First login

After backend + frontend are running, open `http://localhost:5173` and log in.

- **With dev seeding enabled** (`SEED_DEVELOPMENT_DATA=true`, default in
  `local`): use the development accounts (see [`README.md`](../README.md)
  "Default / demo accounts"). Change these passwords before any real use.
- **With structural seeding only** (no demo data): create the first
  administrator through `php artisan tinker` or the admin API. Example:

  ```bash
  php artisan tinker
  ```

  ```php
  $role = App\Models\Role::where('name', 'admin')->first();
  $user = App\Models\User::firstOrCreate(
      ['email' => 'admin@yourcollege.edu'],
      ['name' => 'System Administrator', 'password' => 'ChangeMe!2026']
  );
  $user->role_id = $role->id;
  $user->status = 'active';
  $user->save();
  ```

  > The `User` model casts `password` to `hashed`, so a plain-text value is
  > hashed automatically on save; do **not** pre-apply `bcrypt()`.

> Never keep default/development passwords in production. Password policy:
> minimum length enforced by `Password::min(8)` + confirmation on applicable
> flows; accounts can be suspended/reactivated by HR/admin.

---

## 10. Verification checklist

- [ ] `GET /api/v1/health` → `success: true`
- [ ] `php artisan migrate:status` → all `Ran`
- [ ] `php artisan queue:work` processes jobs (no unacked backlog)
- [ ] `php artisan schedule:list` → 7 scheduled tasks listed
- [ ] Frontend loads at the SPA origin; login works for at least one role
- [ ] `npm run build` completes with no errors
- [ ] `php artisan system:check-health` → `ok`

Next: see [`GO_LIVE_CHECKLIST.md`](GO_LIVE_CHECKLIST.md) and
[`DEPLOYMENT.md`](DEPLOYMENT.md) before production go-live.