# JPC Portal — Architecture (Phase 1)

This document describes the Phase 1 foundation architecture and the conventions all
future modules must follow.

## System Layers

```
┌─────────────────────────────────────────────┐
│ React Frontend (Vite + TypeScript SPA)      │
│  src/components | layouts | pages | routes │
│  src/services  | hooks | types | constants │
└──────────────────┬──────────────────────────┘
                   │ REST + JSON (fetch via api service)
                   ▼
┌─────────────────────────────────────────────┐
│ Laravel 12 REST API  (/api, versioned /v1) │
│  Controllers → Services → Models → MySQL    │
│  Global error handling | CORS | Logging     │
└──────────────────┬──────────────────────────┘
                   ▼
┌─────────────────────────────────────────────┐
│ MySQL 8-compatible database (acadtrack_db)  │
└─────────────────────────────────────────────┘
```

**Hard rules**
- React never queries the database directly.
- All data flows through Laravel API routes.
- No business logic inside React components (use service layer + hooks).
- API routes are versioned: `/api/v1/...`.

## Backend (Laravel 12)

Laravel 12 uses `bootstrap/app.php` for routing, middleware and exception
configuration (no `app/Http/Kernel.php`).

### Routing

- `routes/api.php` — versioned API routes under `api/v1`.
- Registered in `bootstrap/app.php` via `->withRouting(api: ..., apiPrefix: 'api')`.
- Route naming convention: `api.v1.<module>.<action>`.

### Middleware

- `throttleApi('60,1')` — API rate limiting in `bootstrap/app.php`.
- Cors — configuration in `config/cors.php`, origins come from
  `CORS_ALLOWED_ORIGINS` (comma-separated). No wildcard by default.

### API Response Envelope (`app/Support/ApiResponse.php`)

All controllers return through this helper:

| Method                | HTTP | Body shape                                    |
| --------------------- | ---- | --------------------------------------------- |
| `ApiResponse::success`| 200  | `{ success, message, data }`                  |
| `ApiResponse::created`| 201  | `{ success, message, data }`                  |
| `ApiResponse::error`  | 500  | `{ success, message, errors? }`               |
| `ApiResponse::validationError` | 422 | `{ success, message, errors }`      |
| `ApiResponse::noContent` | 204 | —                                           |

### Global Error Handling (`bootstrap/app.php`)

API requests (and any client expecting JSON) always receive the envelope:

| Exception                       | HTTP | Message                        |
| ------------------------------- | ---- | ------------------------------ |
| `ValidationException`           | 422  | Validation failed              |
| `AuthenticationException`       | 401  | Unauthenticated                |
| `AuthorizationException`        | 403  | Forbidden                      |
| `ModelNotFoundException`        | 404  | Resource not found             |
| `NotFoundHttpException`         | 404  | Not found                      |
| `MethodNotAllowedHttpException` | 405  | Method not allowed             |
| `ThrottleRequestsException`     | 429  | Too many requests              |
| Any other `Throwable`           | 500  | message (debug) / generic (prod) |

Stack traces are only revealed when `APP_DEBUG=true` (local). Production responses
never expose internals.

### Logging

- Stack channel on `daily` rotation (`storage/logs/laravel-YYYY-MM-DD.log`).
- Future operational events (AI processing, OCR, reports, approvals, auth, errors)
  must use the `Log` facade. Never log passwords, tokens, or secrets.

### Health Check

`GET /api/v1/health` → `App\Http\Controllers\Api\V1\HealthController`

Executes `SELECT 1` against the configured database and reports:

```
{ application, database, api, environment, api_version, timestamp }
```

## Frontend (React + Vite + TypeScript)

### Directory Conventions

| Path                  | Purpose                                        |
| --------------------- | ---------------------------------------------- |
| `src/components/`     | Reusable UI components                         |
| `src/layouts/`        | Page shells (`AppLayout`)                      |
| `src/pages/`          | Route-level pages (placeholders in Phase 1)    |
| `src/routes/`         | Central route path constants                   |
| `src/services/`       | HTTP service layer (wraps `apiRequest`)        |
| `src/hooks/`          | Shared logic hooks (`useHealthStatus`)         |
| `src/constants/`      | Static config (`navigation.ts`)                |
| `src/types/`          | Shared TypeScript types (`api.ts`)             |
| `src/test/`           | Vitest setup                                   |

### Service Layer

`src/services/api.ts` is the only place that calls `fetch`. It:

- reads the base URL from `VITE_API_BASE_URL`,
- normalizes JSON responses and errors into `ApiError`,
- parses the standard envelope `<ApiResponse<T>>`.

Modules must add their own services (e.g. `services/employees.ts`) that import
`apiRequest` — never call `fetch` directly from a component.

### Routing Foundation

Routes registered in `src/App.tsx`:

`/` → redirects to `/dashboard`, plus `/login`, `/dashboard`, `/employees`,
`/departments`, `/planning`, `/targets`, `/tasks`, `/reports`, `/analytics`,
`/achievements`, `/notifications`, `/settings`, and a `*` not-found page.

All module pages render a shared `<PlaceholderPage>` "Coming soon" state in Phase 1.

### Layout

`AppLayout` renders a responsive shell: persistent sidebar (off-canvas on mobile),
sticky header (page title, notification placeholder, profile placeholder) and a main
content outlet.

## Security Foundations (Phase 1)

- `.env` is git-ignored; `.env.example` contains safe placeholders.
- No API keys or credentials in code or committed files.
- CORS locked to configured origins (`http://localhost:5173,http://127.0.0.1:5173` in development).
- `APP_DEBUG` disabled in production.
- Error responses sanitized.
- API rate limiting enabled.
- Database credentials read only from the environment.

## Testing

| Suite     | Framework        | Command            |
| --------- | ---------------- | ------------------ |
| Backend   | PHPUnit (Laravel)| `cd backend && php artisan test` |
| Frontend  | Vitest + Testing Library | `cd frontend && npm test` |

Covered in Phase 1:

- Backend: app boots, health route registered, health endpoint JSON shape, error
  handling (404/405), live MySQL connection via health test.
- Frontend: app renders, layout/navigation renders, placeholder pages, health
  status success/error/loading states, health service behavior.

## Authentication & RBAC (Phase 2)

Auth uses **Laravel Sanctum SPA (cookie)** — no JWTs.

### Auth flow

```
Browser (localhost:5173)  ── same-origin via Vite proxy ──►  Laravel (localhost:8000)
  GET /sanctum/csrf-cookie  → sets XSRF-TOKEN cookie
  POST /api/v1/auth/login   (X-XSRF-TOKEN header) → sets session cookie
  GET  /api/v1/auth/me      (session cookie) → restores state on page refresh
  POST /api/v1/auth/logout  → invalidates session
```

- `bootstrap/app.php`: `$middleware->statefulApi()` enables Sanctum stateful sessions.
- CORS + `SANCTUM_STATEFUL_DOMAINS` are locked to the Vite origins.
- `EnsureFrontendRequestsAreStateful` only boots the session for requests whose
  `Origin`/`Referer` matches a stateful domain (real browsers always send these).
- CSRF: mutating requests send `X-XSRF-TOKEN` (read from the `XSRF-TOKEN` cookie) via
  `src/services/api.ts`; the backend verifies it for stateful requests.

### Authorization model

Centralized, extensible:

- `app/Enums/Permissions.php` — single source of truth (catalog + role assignments,
  ~42 permissions prepared for future modules).
- `app/Enums/Role.php`, `app/Enums/UserStatus.php` — role/status enums.
- Tables: `roles`, `permissions`, `permission_role` (many-to-many), `users.role_id`,
  `users.status`, `users.last_login_at`, `login_activities`.
- `User::role()` / `hasRole()` / `hasPermission()` / `permissionNames()` helpers.
- Middleware aliases (`role`, `permission`) in `bootstrap/app.php`:
  - `app/Http/Middleware/CheckRole.php`
  - `app/Http/Middleware/CheckPermission.php`
  - Both throw `AccessDeniedHttpException` → rendered as 403 JSON.

**Rule:** backend authorization is the source of truth; the React sidebar/route guards
are UI convenience only.

### Endpoints (added in Phase 2)

| Method | URI                          | Auth / gate                                |
| ------ | ---------------------------- | ------------------------------------------ |
| POST   | `/api/v1/auth/login`         | public, `throttle:login` (5/min)           |
| POST   | `/api/v1/auth/logout`        | `auth:sanctum`                             |
| GET    | `/api/v1/auth/me`            | `auth:sanctum`                             |
| GET    | `/api/v1/admin/rbac`         | `auth:sanctum` + `permission:settings.manage` |

Handled by `app/Http/Controllers/Api/V1/Auth/AuthController`.
Login activity recorded via `app/Services/Auth/LoginActivityService.php` (foundation for
the future audit trail; complete audit system is intentionally out of scope).

### Frontend auth

- `src/auth/AuthProvider.tsx` + `src/auth/useAuth.ts` — global auth state
  (user, role, permissions, loading, error; restores session via `/me`).
- `src/services/authService.ts` — login/logout/me wrappers.
- `src/components/ProtectedRoute.tsx` — redirects to `/login`, renders
  `AccessDenied` for missing role/permission.
- `src/pages/LoginPage.tsx` — validated form with loading/error states.
- `src/components/Sidebar.tsx` — filters nav items by the user’s permissions.
- `src/components/Header.tsx` — shows the user name and a sign-out action.

## Conventions for Future Phases

1. Add API modules under `app/Http/Controllers/Api/V1/<Module>Controller.php`.
2. Register routes inside the `v1` group in `routes/api.php` as `api.v1.*`.
3. Use Form Requests for validation, Services for business logic, Resources for
   API transformation.
4. Add a matching frontend service under `src/services/` and pages under `src/pages/`.
5. Keep API responses wrapped by `App\Support\ApiResponse`.
6. Never expose secrets, stack traces, or filesystem paths in API responses.