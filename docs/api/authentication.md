# JPC Portal — Authentication API

Base path: `/api/v1` · All responses use the standard envelope
`{ success, message, data? }`.

Authentication uses **Laravel Sanctum (SPA / cookie-based)**. The React app is served
same-origin through the Vite dev proxy, so the browser session cookie and CSRF cookie
flow work without CORS in development.

---

## GET /sanctum/csrf-cookie

Bootstraps the CSRF cookie. Not versioned.

- **Purpose:** Provide the `XSRF-TOKEN` cookie that must be echoed back as the
  `X-XSRF-TOKEN` header on stateful (cookie-authenticated) requests.
- **Auth required:** No.
- **Request:** `GET`
- **Response:** `204 No Content`
- **Client behaviour:** call once before `/auth/login`; the frontend
  `services/authService.login()` already does this via `ensureCsrfToken()`.

---

## POST /api/v1/auth/login

- **Purpose:** Authenticate a user and start a server-side session.
- **Auth required:** No (rate-limited).
- **Rate limit:** 5 attempts per minute per email+IP → `429 Too Many Requests`.

### Request

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

### Validation rules

| Field      | Rules                     |
| ---------- | ------------------------- |
| `email`    | required, valid email     |
| `password` | required, string          |

### Success — 200

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "id": 1,
    "name": "JPC Administrator",
    "email": "admin@jpc.local",
    "status": "active",
    "role": "admin",
    "permissions": ["dashboard.view", "users.view"],
    "last_login_at": "2026-09-14T05:44:31+00:00"
  }
}
```

### Error cases

| Case                       | HTTP | Body                                                        |
| -------------------------- | ---- | ----------------------------------------------------------- |
| Missing/invalid email/pass | 422  | `{ success, message: "Validation failed", errors: {...} }`  |
| Wrong credentials          | 401  | `{ success: false, message: "These credentials do not match our records." }` |
| Inactive/suspended account | 401  | Same generic message (account status never revealed)        |
| Rate limit exceeded        | 429  | `{ success: false, message: "Too many requests" }`          |

> On failure, a `failed` row is written to `login_activities`. On success a `login`
> row is written and `users.last_login_at` is updated.

---

## POST /api/v1/auth/logout

- **Purpose:** Destroy the current session and revoke the authentication context.
- **Auth required:** Yes (`auth:sanctum`).

### Success — 200

```json
{ "success": true, "message": "Logout successful", "data": [] }
```

### Error cases

| Case                     | HTTP | Body                                       |
| ------------------------ | ---- | ------------------------------------------ |
| Not authenticated        | 401  | `{ success: false, message: "Unauthenticated" }` |

The most recent open `login` activity row for the user is stamped with `logged_out_at`.

---

## GET /api/v1/auth/me

- **Purpose:** Return the currently authenticated user’s profile.
- **Auth required:** Yes (`auth:sanctum`).
- **Session persistence:** the React app calls this on startup to restore state.

### Success — 200

```json
{
  "success": true,
  "message": "Authenticated user",
  "data": {
    "id": 1,
    "name": "JPC Administrator",
    "email": "admin@jpc.local",
    "status": "active",
    "role": "admin",
    "permissions": ["dashboard.view", "users.view"],
    "last_login_at": "2026-09-14T05:44:31+00:00"
  }
}
```

Never returns the password hash or any internal/sensitive field.

### Error cases

| Case              | HTTP | Body                                        |
| ----------------- | ---- | ------------------------------------------- |
| Not authenticated | 401  | `{ success: false, message: "Unauthenticated" }` |

---

## Role & permission summary (admin demo)

- **Purpose:** Exposes the current role → permission matrix; demonstrates the
  `permission:settings.manage` authorization middleware.
- **Auth required:** Yes (`auth:sanctum`), requires `settings.manage`.

### GET /api/v1/admin/rbac

| Role             | HTTP | Body                                               |
| ---------------- | ---- | -------------------------------------------------- |
| Admin            | 200  | `{ success, message, data: { roles, catalog } }`    |
| HR/Dept Head/Employee | 403 | `{ success: false, message: "Forbidden" }`        |
| Unauthenticated  | 401  | `{ success: false, message: "Unauthenticated" }`    |

---

## Authorization middleware

Backend authorization is the source of truth. Middleware aliases are registered in
`bootstrap/app.php`:

| Alias          | Usage                          | Fail → HTTP |
| -------------- | ------------------------------ | ----------- |
| `auth:sanctum` | protected endpoints            | 401         |
| `role:admin`   | exact role gate, comma-list    | 403         |
| `permission:settings.manage` | granular gate, comma-list | 403  |

Example (from `routes/api.php`):

```php
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'permission:settings.manage'])
    ->group(fn () => Route::get('rbac', RbacController::class));
```

## Development test accounts

Seeded by `php artisan db:seed` (development only — never for production data).

| Role           | Email                    | Password      |
| -------------- | ------------------------ | ------------- |
| Admin          | `admin@jpc.local`        | `P@ssw0rd2026` |
| HR             | `hr@jpc.local`           | `P@ssw0rd2026` |
| Department Head| `department.head@jpc.local` | `P@ssw0rd2026` |
| Employee       | `employee@jpc.local`     | `P@ssw0rd2026` |

Passwords are stored hashed (Laravel `bcrypt`, `BCRYPT_ROUNDS` respected).