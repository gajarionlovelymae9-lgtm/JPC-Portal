# JPC Portal â€” Capstone System Overview

Academic summary of the completed system.

## System title

**JPC Portal â€” AI-Driven Employee Progress Monitoring & Management System**

## Problem addressed

John Paul College leadership lacked a centralized, data-driven way to monitor
employee progress through annual plans, targets, tasks, reports, validation, and
achievements. Paper/document-based reporting was hard to track, late submissions
were difficult to surface, reviews were time-consuming, and evidence/AI analysis
was not available in one place.

## Objectives

1. Centralize **annual plan â†’ target â†’ task â†’ report â†’ review â†’ achievement** workflows.
2. Automate **OCR + AI analysis** of submitted reports (summary, validation, reference
   and similarity indicators).
3. Provide **role-based dashboards** for Admin, HR, Department Head and Employee.
4. Compute **performance/KPI** data deterministically and surface **achievements**.
5. Add **data-quality scans**, **reporting & analytics**, **AI Chat**, and an
   **audit trail** â€” with human review always in control.

## Users & roles

| Role | Purpose |
| --- | --- |
| Admin | Institution dashboard, employee/dept/task/target management, approvals, audit, exports |
| HR | Employee records, data validation, quality issues, reporting |
| Department Head | Department tasks/targets/reports, manual review, revision, approval, analytics |
| Employee | Self-service: tasks, targets, report creation, upload, AI review, resubmit, performance |

## Major features

- Employee/department/position management with history
- Academic year â†’ annual plan â†’ targets (department + employee)
- Task management (assignments, progress, references, comments, attachments, review)
- Report workflow (draft â†’ submitted â†’ review â†’ approved/revision/rejected) with
  immutable versions, files, timeline
- OCR document processing (offline fake engine + optional Google Vision)
- AI analysis pipeline (summary, validation, references, similarity indicators),
  AI findings + insights
- Performance records, score breakdowns, progress snapshots, KPI
- Achievements: badges, monthly achievements, employee of the month
- Data-quality scans and issue lifecycle
- Reporting: employee/department/institution/monthly/achievements/late/pending/
  targets/data-quality/management summary
- Analytics: trends, comparisons, rankings, distribution
- Report exports (CSV/XLSX/PDF, queued, owner-scoped, TTL)
- AI Chat with authorized tool registry
- Notifications (in-app/mail), preferences, delivery logs
- Audit + activity logs

## Architecture

React (Vite + TS) SPA â†’ Laravel 12 REST API `/api/v1` (Sanctum cookie auth, RBAC,
Services, Resources) â†’ MySQL 8 (`acadtrack_db`), with database queues, scheduler,
OCR/AI (server-side), notifications, reporting/analytics, and audit logging.
See [`FINAL_ARCHITECTURE.md`](FINAL_ARCHITECTURE.md).

## AI functionality

- Deterministic offline engine by default (`AI_PROVIDER=fake`) â€” works fully offline.
- Optional OpenAI-compatible provider (`AI_PROVIDER=openai`, `AI_ENDPOINT`).
- Summarizes, validates against configured requirements, compares against
  references, detects similarity indicators, finds data inconsistencies, generates
  insights, and answers authorized questions via AI Chat tools.
- **AI never makes approval/HR decisions** â€” see [`AI_GOVERNANCE.md`](AI_GOVERNANCE.md).

## OCR functionality

- Uploaded report documents are OCR-extracted server-side.
- `OCR_PROVIDER=google_vision` uses the Google Vision REST API;
  `OCR_PROVIDER=fake` is the offline default. Credentials stay server-side.

## Workflow

```
Task â†’ Report(draft) â†’ upload/OCR â†’ AI summary+validation â†’ submit
â†’ under_review â†’ [revision_required â†’ resubmit] â†’ approved/rejected
â†’ performance records â†’ achievements â†’ analytics/reporting/exports
```

## Database overview

MySQL `acadtrack_db`, utf8mb4, ~90 migrations covering identity/RBAC, planning,
tasks, reports, OCR/AI, chat, performance, achievements, data quality, reporting
exports, notifications, messages, audit. See [`DATABASE.md`](DATABASE.md) and
[`docs/database/erd.md`](database/erd.md).

## Security

Sanctum SPA cookie auth; role/permission RBAC + policies + service scoping (IDOR
protection); private file storage + authorized downloads; parameterized SQL;
React XSS defaults; CSRF via Sanctum; server-side AI/OCR secrets; audit logging.
See [`SECURITY.md`](SECURITY.md).

## Testing

- Backend: **406 tests passed, 1880 assertions** (`php artisan test`; isolated
  `acadtrack_db_test`).
- Frontend: **87 tests passed** (Vitest), oxlint clean of errors, TypeScript +
  production build pass.

## Limitations

- Real AI/OCR depend on provider availability, network and quota; offline fake
  engines are the default to avoid the dependency.
- OCR accuracy depends on document quality.
- Similarity detection is an **indicator**, not proof of plagiarism.
- AI findings require human validation.
- Email delivery depends on mail configuration (mail is optional; failures are
  logged, never crash).
- Performance scales with infrastructure; the frontend bundle is a single ~846 kB
  chunk (code-split target for the future).

## Future enhancements

See [`FUTURE_ENHANCEMENTS.md`](FUTURE_ENHANCEMENTS.md): mobile app, predictive
analytics, anomaly detection, institution benchmarking, more AI providers, offline
support, advanced OCR/document classification, recommendation engine, time/
attendance integration (if approved), enterprise/cloud deployment.
