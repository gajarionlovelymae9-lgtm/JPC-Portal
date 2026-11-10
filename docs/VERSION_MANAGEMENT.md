# JPC Portal — Version Management & Change Request Process

This document defines how the approved **JPC Portal v1.0.0** release is
preserved and how future changes are requested, assessed, versioned, released,
and recorded.

Related documents: [`maintenance/release-process.md`](maintenance/release-process.md)
(the day-to-day release checklist), [`RELEASE_NOTES.md`](RELEASE_NOTES.md)
(current release notes), [`CHANGELOG.md`](../CHANGELOG.md) (running change log).

---

## 1. The current approved release

The current approved release is **JPC Portal v1.0.0** (released 2026-09-25),
pinned by:

- `VERSION` (repository root) — the canonical version marker.
- `CHANGELOG.md` (repository root) — the running change history.
- `docs/RELEASE_NOTES.md` — the v1.0.0 release notes.
- Git tag/baseline of the release (maintenance records stamp it via
  `MaintenanceLogService::currentVersion()` when not supplied).

The v1.0.0 baseline is **frozen**. It must not be silently modified to add
unapproved features.

---

## 2. Versioning scheme

| Version | Meaning | Example | When |
| --- | --- | --- | --- |
| **x.y.0** (major) | Breaking changes | `v2.0.0` | API/schema/behavior breaks; larger approved feature set |
| **x.1.y / x.y.1** (minor) | Larger approved feature changes | `v1.1.0` | New features, new modules, notable enhancements |
| **x.y.z** (patch) | Minor compatible improvements & fixes | `v1.0.1` | Bug fixes, security patches, documentation, small non-breaking changes |

Rules:

- The **current approved release is preserved** — version number increments;
  the previous release is never rewritten.
- **Do not** create a release by modifying the frozen baseline in place.
- A **patch** must not change public API shape or database behavior.
- A **minor** must remain backward-compatible (additive migrations only).
- A **major** may require the deployment/rollout process of
  [`DEPLOYMENT.md`](DEPLOYMENT.md) plus migration coordination.
- Security fixes always advance the patch version at minimum and follow the
  same change-request process.

Every release updates: `VERSION`, `CHANGELOG.md`, `docs/RELEASE_NOTES.md`, and
the `docs/` handover set.

---

## 3. Change request process

Every significant change goes through this pipeline:

```
REQUEST
   ↓
ANALYSIS
   ↓
IMPACT ASSESSMENT
   ↓
APPROVAL
   ↓
VERSION ASSIGNMENT
   ↓
IMPLEMENTATION
   ↓
TESTING
   ↓
DOCUMENTATION
   ↓
RELEASE
```

### 3.1 REQUEST

Anyone may raise a change request. Each request must state:

- the problem or desired behaviour (what, why);
- the affected module(s);
- the requesting party;
- priority (critical / high / medium / low) and any deadline.

Requests are recorded in the maintenance log (type `Release`, see
[`maintenance-log.md`](maintenance-log.md)) and/or the backlog
([`BACKLOG.md`](BACKLOG.md)).

### 3.2 ANALYSIS

The change is analysed: what code/DB/UI/API is involved, which services and
tests, and which documents must change.

### 3.3 IMPACT ASSESSMENT

- Affected modules and routes (API surface changes? new permissions?).
- Database impact (new migration? additive only; **never edit an applied
  migration**).
- Mirrors on the frontend (types, pages, services).
- Test impact; staging requirements.
- Rollback plan (backup is always a precondition).

### 3.4 APPROVAL

- Non-trivial change: approved by the project owner/assigned maintainer.
- A change that alters API contracts, authentication/RBAC, the database schema,
  or the AI/OCR pipeline requires explicit approval before implementation.
- **Any major version upgrade (e.g. Laravel 12 → 13, React 19 → 20) is a
  controlled change request.** Never upgrade major versions automatically.

### 3.5 VERSION ASSIGNMENT

Based on impact (see §2), the change is assigned `v1.0.1`, `v1.1.0`, `v2.0.0`,
etc. This is recorded at approval time.

### 3.6 IMPLEMENTATION

- Work happens on a branch/copy — **never** directly on production.
- Additive migrations only; enums/models updated in one place.
- Follow the exact-case import convention for frontend pages.

### 3.7 TESTING

- Backend: `cd backend && php artisan test` (full suite on `acadtrack_db_test`).
- Frontend: `cd frontend && npm test`, `npm run lint`, `npm run build`.
- Where relevant: re-run the demo walkthrough (93 endpoints for the four roles)
  and `php artisan system:check-health`.

### 3.8 DOCUMENTATION

Update `CHANGELOG.md`, `VERSION`, `docs/RELEASE_NOTES.md` (new release section)
and any affected docs in `docs/` (handovers, guides, DEPLOYMENT, DATABASE,
TROUBLESHOOTING). Record the change in the maintenance log.

### 3.9 RELEASE

Follow [`maintenance/release-process.md`](maintenance/release-process.md):
backup → deploy → `migrate --force` → `php artisan optimize` → restart queue
worker → verify → record the tag and maintenance-log entry.

---

## 4. Release rules (non-negotiable)

1. Back up the database and private files **before** every release.
2. Migrations in production run with `--force` and are **additive only**; never
   `migrate:fresh`.
3. Production environment sets `SEED_DEVELOPMENT_DATA=false` explicitly.
4. Frontend import paths are exact-case.
5. Every release must pass the backend and frontend suites before the
   maintenance log entry is marked `completed`.
6. The `VERSION` marker, `CHANGELOG.md` and `RELEASE_NOTES.md` are updated in
   the same release.

See [`maintenance/release-process.md`](maintenance/release-process.md) for the
step-by-step checklist and [`CHANGELOG.md`](../CHANGELOG.md) for the history.