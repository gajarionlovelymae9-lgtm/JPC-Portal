# JPC Portal

**JPC Portal** is an AI-driven employee progress monitoring platform for **John Paul College**.

![Phase](https://img.shields.io/badge/phase-3.1D%20seeders-blue)

The system monitors employees, departments, annual plans, targets, tasks, submitted
reports, OCR documents, AI summaries/validation, approvals, achievements, analytics,
notifications, and audit trails — with natural language AI assistance.

> **Current status:** Phase 3 (Data Seeding — Development Sample Data, 3.1-D). The
> role/permission catalog, departments, positions, academic year, badge catalog, four
> development accounts, 15 employees, annual plans, targets, tasks, reports with sample
> AI reviews and approvals, performance records, monthly achievements and notifications
> are all seeded by `php artisan db:seed`. See [docs/database/seeders.md](docs/database/seeders.md).

## Purpose

Provide John Paul College leadership with a centralized, data-driven way to monitor
employee progress through **annual plans → targets → tasks → reports → validation →
achievements**, backed by NLP-based AI assistance.

## Technology Stack

| Layer      | Technology                       |
| ---------- | -------------------------------- |
| Frontend   | React.js (Vite + TypeScript)     |
| Backend    | Laravel 12 (RESTful JSON API)    |
| Database   | MySQL 8-compatible (`acadtrack_db`) |
| Local Dev  | XAMPP (Apache / PHP 8.2 / MariaDB) / Windows |

> **Note:** XAMPP ships MariaDB, which is fully MySQL-compatible and is used as the
> MySQL 8-compatible database layer on this machine.

## Architecture

```
React Frontend
      ↓  (HTTPS/HTTP + JSON)
REST API (/api/v1)
      ↓  (Laravel 12)
MySQL Database (acadtrack_db)
```

- The frontend never talks to the database directly.
- All data access flows through the Laravel REST API.
- APIs are versioned under `/api/v1`.
- Business logic lives in Laravel (Services, Controllers, Form Requests, Models).
- React uses components, hooks, and a centralized service layer.

## Repository Structure

```
jpc-portal/
├── backend/            # Laravel 12 REST API
├── frontend/           # React (Vite + TypeScript) SPA
├── docs/               # Architecture & developer documentation
├── .gitignore
└── README.md
```

## Prerequisites

- PHP >= 8.2 (XAMPP recommended)
- Composer 2.x
- Node.js >= 20 and npm
- MySQL 8 (or XAMPP MariaDB), running on `127.0.0.1:3306`

## Installation

### 1. Database

Start MySQL and create the database:

```sql
CREATE DATABASE IF NOT EXISTS acadtrack_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend (Laravel 12)

```bash
cd backend
composer install

# Create environment file and set your database credentials
copy .env.example .env

# Generate the application key
php artisan key:generate

# Run migrations (users, roles, permissions, sessions, login activities, etc.)
php artisan migrate

# Seed the roles, permission catalog, development users and the Phase 3.1-D
# sample dataset (employees, plans, tasks, reports, achievements).
php artisan db:seed
```

> `SANCTUM_STATEFUL_DOMAINS` in `.env` must list the Vite dev origin
> (`localhost:5173,127.0.0.1:5173`) so Sanctum treats the SPA requests as stateful.

### 3. Frontend (React)

```bash
cd frontend
npm install

# Create environment file (default VITE_API_BASE_URL=/api/v1 already works)
copy .env.example .env
```

Development uses the Vite proxy (`vite.config.ts`): `/api` and `/sanctum` are proxied
to `http://127.0.0.1:8000`, so the browser sees a single origin and Sanctum cookie
authentication works seamlessly.

## Running the Application

### Backend

```bash
cd backend
php artisan serve
# → http://localhost:8000
```

### Frontend

```bash
cd frontend
npm run dev
# → http://localhost:5173
```

Open **http://localhost:5173** in your browser. The dashboard shows the live backend,
API, and database connection status.

## Environment Setup

### Backend `.env` (backend/.env)

| Variable         | Description                          | Example                 |
| ---------------- | ------------------------------------ | ----------------------- |
| `DB_CONNECTION`  | Database driver                      | `mysql`                 |
| `DB_HOST`        | Database host                        | `127.0.0.1`             |
| `DB_PORT`        | Database port                        | `3306`                  |
| `DB_DATABASE`    | Database name                        | `acadtrack_db`          |
| `DB_USERNAME`    | Database user                        | `root`                  |
| `DB_PASSWORD`    | Database password                    | *(XAMPP default empty)* |
| `CORS_ALLOWED_ORIGINS` | Comma-separated allowed origins | `http://localhost:5173,http://127.0.0.1:5173` |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated SPA hosts for cookie auth | `localhost:5173,127.0.0.1:5173` |
| `APP_ENV`        | Environment (`local`/`production`) | `local`                 |
| `APP_DEBUG`      | Debug mode (always `false` in prod) | `true` (local only)     |

Secrets stay in `.env` and are **never committed**. `.env.example` holds safe placeholders only.

### Frontend `.env` (frontend/.env)

| Variable            | Description                        | Example                   |
| ------------------- | ---------------------------------- | ------------------------- |
| `VITE_API_BASE_URL` | Base URL of the Laravel API (v1)   | `/api/v1` (default; proxied in dev) |
| `VITE_APP_NAME`     | Application display name           | `JPC Portal`              |

## Authentication (Phase 2)

Sanctum SPA cookie authentication with roles and permissions:

| Role           | Email                    | Password      |
| -------------- | ------------------------ | ------------- |
| Admin          | `admin@jpc.local`        | `P@ssw0rd2026` |
| HR             | `hr@jpc.local`           | `P@ssw0rd2026` |
| Department Head| `departmenthead@jpc.local` | `P@ssw0rd2026` |
| Employee       | `employee@jpc.local`     | `P@ssw0rd2026` |

> Development-only credentials. Run `php artisan db:seed` to create them. Passwords are
> stored hashed — never in plain text. Development passwords are read from
> `SEED_ADMIN_PASSWORD`, `SEED_HR_PASSWORD`, `SEED_DEPARTMENT_HEAD_PASSWORD` and
> `SEED_EMPLOYEE_PASSWORD` (fallback `P@ssw0rd2026`). Development seeders are disabled
> on production unless `SEED_DEVELOPMENT_DATA=true` is set explicitly.

Endpoints: `POST /api/v1/auth/login` · `POST /api/v1/auth/logout` ·
`GET /api/v1/auth/me` · `GET /api/v1/admin/rbac` (admin-only demo).

See [docs/api/authentication.md](docs/api/authentication.md) for the full API
documentation.

## Health Check

The API exposes a versioned health endpoint:

```
GET /api/v1/health
```

Example response:

```json
{
  "success": true,
  "message": "JPC Portal API is running",
  "data": {
    "application": "ok",
    "database": "ok",
    "api": "ok",
    "environment": "local",
    "api_version": "v1",
    "timestamp": "2026-09-14T04:42:24+00:00"
  }
}
```

The response never exposes credentials, keys, or filesystem paths.

## Testing

### Backend

```bash
cd backend
php artisan test
```

### Frontend

```bash
cd frontend
npm test
```

### Code quality

```bash
cd backend && vendor/bin/pint          # Laravel code style
cd frontend && npm run lint            # oxlint
cd frontend && npm run build           # TypeScript + production build
```

## API Response Standard

All JPC Portal APIs return a consistent envelope:

```json
{ "success": true,  "message": "Request successful", "data": {} }
{ "success": false, "message": "Validation failed",  "errors": {} }
```

## Documentation

See [docs/architecture.md](docs/architecture.md) for detailed architecture and
development conventions.