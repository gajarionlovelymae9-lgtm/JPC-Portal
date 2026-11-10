# JPC Portal — Automated Testing Guide

This document explains the automated test strategy for the JPC EPMS AI-Driven System
(JPC Portal) v1.0.0. It is the companion to
[`docs/qa/phase-18-automated-test-report.md`](phase-18-automated-test-report.md),
which records the results of the Phase 18 run.

## 1. Stack overview

| Layer | Runner | Command | Purpose |
|---|---|---|---|
| Backend unit + feature tests | PHPUnit 11 (Laravel test runner) | `php artisan test` (from `backend/`) | Eloquent models, services, jobs, policies, controllers, routes, seeders, auth/RBAC |
| Frontend component + service tests | Vitest 5 + React Testing Library + jsdom | `npm test` (from `frontend/`) | React pages, components, service-layer contracts |
| Frontend lint | oxlint | `npm run lint` (from `frontend/`) | Static analysis (0 errors gate) |
| CI | GitHub Actions | `.github/workflows/tests.yml` | Runs backend + frontend on every push/PR |

Pest is **not** installed; the suite runs exclusively on PHPUnit through
`php artisan test`.

## 2. Backend suite

### 2.1 Environment (isolated by design)

`backend/phpunit.xml` pins a dedicated configuration so automated tests never touch the
development database:

- `APP_ENV=testing`
- `DB_DATABASE=acadtrack_db_test` (dedicated MySQL test database)
- `QUEUE_CONNECTION=sync` (jobs run inline unless faked)
- `MAIL_MAILER=array` (mail captured, never sent)
- `CACHE_STORE=array`, `BCRYPT_ROUNDS=4`
- `SESSION_DRIVER=database` (replicates the real SPA cookie flow — the `array`
  driver never issues a session cookie, which breaks logout tests)
- `TELESCOPE_ENABLED=false`, `PULSE_ENABLED=false`

### 2.2 Running

```bash
cd backend
php artisan config:clear        # never run the suite with a stale config cache
php artisan test                # full suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test tests/Feature/Ai           # single area
php artisan test --filter=AiAuthorizationTest
```

> **IMPORTANT (learned in Phase 17):** a stale `bootstrap/cache/config.php` (from
> `php artisan optimize`) freezes `local` + the dev DB into every test process and
> causes spurious `419 CSRF token mismatch` failures. Always
> `php artisan config:clear` before running the suite, and keep the config cache
> disabled while developing tests.

### 2.3 What the suite covers

- **Auth & RBAC** — login/logout/session lifecycle, roles, permissions, IDOR isolation.
- **Planning, tasks & assignments** — task lifecycle, relationship chains, review and transitions.
- **Reports & workflow** — draft/create, submit, review, revision, approval, downstream updates, exports.
- **AI assistant & insights** — chat, conversations, summaries, tool execution, per-role authorization,
  report AI pipelines.
- **Notifications, dashboards, analytics, data quality, achievements, activity logs.**
- **Queues/jobs** — `GenerateReportExportJob` end-to-end with `Queue`/`Bus` fakes plus a
  `QueueContractTest` that guards the AI pipeline job wiring.
- **Models** — casts, scopes, soft-deletes, relationships for the core report and AI models.
- **Routes & seeders** — route-surface regression (every `api/v1/*` route present and named)
  and the role/permission seeder contract.

## 3. Frontend suite

### 3.1 Configuration

Vitest is configured inside `frontend/vite.config.ts` (jsdom environment, globals on,
setup file `src/test/setup.ts`, CSS off).

### 3.2 Running

```bash
cd frontend
npm test          # npm test = vitest run (single pass)
npm run lint      # oxlint — 0 errors gate (pre-existing warnings tolerated)
npm run build     # tsc -b + vite build (type gate)
```

### 3.3 What the suite covers

- **App shell & auth** — routing, sidebar, login page, `ProtectedRoute` permission gating.
- **Services** — contract tests that pin each HTTP method, URL and serialised payload
  (reporting, task, employee portal, health, dashboard services).
- **Employee portal flows** — create report (save draft → confirm → submit),
  revision (loads reviewer feedback, submits, error paths), notifications
  (list/empty/error + unread styling), profile (load/update error/success), dashboard panels.
- **Components** — task progress bar/timeline/table, confirm dialog, system status.

## 4. CI workflow (GitHub Actions)

`.github/workflows/tests.yml` runs two jobs on every push/PR to `main`/`master`:

1. **Backend** — PHP 8.2 + Composer, a MySQL 8 service pre-created with an empty
   `acadtrack_db_test`, `composer install`, `.env` prepared via
   `cp .env.example .env && php artisan key:generate`, then `php artisan test`.
2. **Frontend** — Node 22 + npm cache, `npm ci`, `npm run lint`, `npm test`.

Only deterministic commands run in CI; the AI/OCR providers stay `fake`, so no
external credentials are required.

## 5. Coverage caveat

Neither xdebug nor pcov is installed, so code-coverage percentages are **not measured**.
The Phase 18 report therefore does not claim a coverage figure. Use the test-area matrix
for confidence-by-feature instead. See `docs/qa/phase-18-automated-test-report.md` §8.

## 6. Adding tests

- Backend: mirror the folder convention — `tests/Feature/<Module>/` for HTTP/request tests,
  `tests/Unit/<Module>/` for pure/unit tests. Use `RefreshDatabase` and `RolePermissionSeeder`
  for feature tests. Factories live in `database/factories/` (`AiInsightFactory`,
  `AiChatConversationFactory`, `AiChatMessageFactory`, etc.); shared user bootstrapping
  lives in `tests/Support/CreatesUsers.php`.
- Frontend: place component tests beside the component (`*.test.tsx`) and service tests
  beside the service (`*.test.ts`). Stub `fetch` and interact through user-visible roles
  (buttons, headings, textboxes) — see `frontend/src/pages/LoginPage.test.tsx` for the pattern.