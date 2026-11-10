# JPC Portal — Hotfix: Login "CSRF token mismatch." (HTTP 419)

## 1. Document control
- **Product:** JPC EPMS AI-Driven System v1.0.0 (JPC Portal)
- **Type:** Production blocker — dev / live-verification hotfix
- **Date:** 2026-09-26
- **Environment:** Windows (Laragon-compatible) · Laravel 12 · Sanctum SPA · Vite dev proxy · PHP `-S 127.0.0.1:8000` · frontend dev server `127.0.0.1:5173`
- **Status:** **VERIFIED — login works; full regression green.**

## 2. Symptom
Entering valid admin credentials (`admin@jpc.local`) on the login page returned
`{"success":false,"message":"CSRF token mismatch."}` (HTTP 419) instead of reaching
the dashboard.

## 3. Root cause (proven by reproduction)
The Sanctum SPA flow requires **same-origin cookies**. In dev the frontend was configured
with an **absolute, cross-origin** API base URL:

```
VITE_API_BASE_URL=http://localhost:8000/api/v1   (frontend/.env)
```

while the Vite dev proxy targets `http://127.0.0.1:8000` for both `/api` and `/sanctum`
(`frontend/vite.config.ts`). Consequences:

1. `ensureCsrfToken()` fetched `/sanctum/csrf-cookie` **through the proxy** → the browser
   stored `XSRF-TOKEN` and `jpc_portal_api_session` cookies keyed to **`http://127.0.0.1:5173`**.
2. The login POST used the **absolute** base URL → it hit **`http://localhost:8000`** directly
   (different host). Host-only session cookies are not sent cross-host, so Laravel started a
   **fresh session**; the `X-XSRF-TOKEN` header was validated against the new session token,
   producing the mismatch.

Reproduction (curl, same jar):
- Cookie primed from `127.0.0.1:8000`, login POST to `localhost:8000` → **419** ✅ reproduced.
- Cookie primed and POSTed to the same host → **passed CSRF** (reached validation) — proving
  host split, not token handling, was the cause.

## 4. Fix
Make the frontend fully same-origin through the existing Vite proxy (which already proxies
`/api` and `/sanctum` to `127.0.0.1:8000`):

| File | Before | After |
|------|--------|-------|
| `frontend/.env` | `VITE_API_BASE_URL=http://localhost:8000/api/v1` | `VITE_API_BASE_URL=/api/v1` |
| `frontend/.env.example` | `VITE_API_BASE_URL=http://localhost:8000/api/v1` | `VITE_API_BASE_URL=/api/v1` |

No backend change. CSRF/VerifyCsrfToken stays fully enforced. `api.ts` already falls back
to `/api/v1` when the var is absent, and every request uses `credentials: 'include'`.

## 5. Verification
- Restarted Vite and replayed the exact browser flow through the proxy:
  - `GET http://127.0.0.1:5173/sanctum/csrf-cookie` → 204 with XSRF-TOKEN + session cookies.
  - `POST http://127.0.0.1:5173/api/v1/auth/login` with real `X-XSRF-TOKEN` → **200 OK,
    `{"success":true,...}` admin profile + permissions**.
- `php artisan optimize:clear` → cleared (config/cache/compiled/events/routes/views).
- Sessions table confirmed present (`php artisan tinker`).
- Backend suite: **507 passed / 2409 assertions / 0 failures**.
- Frontend suite: **105 passed / 0 failures**; lint **0 errors** (+58 pre-existing warnings, 0 new).

## 6. Commands to run
```bash
# after pulling changes
cd frontend && npm run dev          # restarted to re-read .env
cd backend && php artisan optimize:clear
php artisan test                    # 507 passed
cd ../frontend && npm test -- --run # 105 passed
```
Manual login check: open `http://127.0.0.1:5173` (or `http://localhost:5173`), log in as
`admin@jpc.local` / the configured dev password → dashboard loads.