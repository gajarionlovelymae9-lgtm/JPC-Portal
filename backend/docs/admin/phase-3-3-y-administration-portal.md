# Phase 3.3-Y — Administration Portal & Executive Dashboard

## Overview
Phase 3.3-Y provides an institution-wide management and executive governance layer for the JPC Portal.
It aggregates data across Human Resources, Academic Departments, Faculty Members, Task Deliverables, Report Submissions, OCR Pipeline, and AI-assisted Validations to give top-level institutional leaders complete visibility: *"Makikita na natin kung gaano na kalayo ang buong institution."*

---

## Key Capabilities

### 1. Executive Dashboard (`/admin/dashboard` & `/admin/executive-report`)
- **Institution-Wide KPIs:** Active Employees, Departments, Total Tasks, Reports Submitted, Completion Rate, On-Time Rate, Approval Rate, and Target Progress %.
- **Institution-Wide Annual Target Progress Bar:** Track whole-school progress toward annual operational milestones.
- **Status Distributions:** Tasks breakdown (Pending, In Progress, Submitted, Completed, Overdue, Cancelled) and Reports breakdown (Draft, Submitted, Reviewing, Approved, Revision Required, Rejected).
- **Cross-Department Comparison:** Head-to-head performance matrix of all academic departments (completion rates, approval rates, total staff, and on-time submissions).
- **Monthly Achievers:** Live recognition of top-performing faculty members with high completion metrics.
- **Critical Institutional Alerts:** Live detection of overdue deliverables, pending report review backlogs, and unresolved department escalations.
- **AI-Assisted Executive Brief:** Automated institution status summary with required advisory disclaimers.

### 2. Department Management & Comparison (`/admin/departments`, `/admin/departments/:id`)
- Cross-department list with performance metrics and department head mapping.
- Department deep-dive showing faculty rosters, tasks, targets, and departmental report backlogs.

### 3. Institutional Report Quality & Review Center (`/admin/reports-center`, `/admin/reports/:id`)
- Cross-institutional report search and multi-criteria filtering (department, status, submission period).
- In-depth inspection of submitted reports, attachments, OCR confidence & text extracts, AI summaries, and automated validation flags.
- **Administrative Override Action (`POST /api/v1/admin/reports/{report}/override`):**
  - Allows top management to approve, reject, or request revisions.
  - Requires mandatory explanation/justification.
  - Fully logged in audit trail (`activity_logs`).

### 4. AI & OCR Pipeline Monitoring (`/admin/ai`, `/admin/ai/ocr`)
- **Pipeline Health Dashboard:** Tracks total processed documents, average confidence rating, validation pass rate, failed jobs, and OCR queue latency.
- **OCR Document Monitor:** Tabular feed of scanned documents with confidence indicators, document types, and OCR processing states.
- **Admin Retry Action (`POST /api/v1/admin/ai/retry/{job}`):** Allows administrators to re-dispatch failed OCR/AI analysis jobs.

### 5. Escalation Workspace (`/admin/escalations`)
- Triage interface for department-level issues escalated by Department Heads to executive leadership.
- Fast resolution action (`POST /api/v1/admin/escalations/{escalation}/resolve`) with resolution notes and status tracking.

### 6. Institutional Export Center (`/admin/exports`)
- One-click institution-wide progress exports, department comparison sheets, and accreditation-ready summary packages.

---

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/admin/executive-dashboard` | Full institutional KPI aggregation, department rankings, alerts, and AI summary |
| `GET` | `/api/v1/admin/departments` | Cross-department performance list |
| `GET` | `/api/v1/admin/departments/{id}` | Single department drill-down |
| `GET` | `/api/v1/admin/reports` | Institution-wide report review queue with search & filters |
| `GET` | `/api/v1/admin/reports/{id}` | Detailed report review with OCR & AI validation data |
| `POST` | `/api/v1/admin/reports/{id}/override` | Admin decision override with mandatory audit note |
| `GET` | `/api/v1/admin/ai/status` | AI/OCR processing center health statistics |
| `GET` | `/api/v1/admin/ai/ocr` | Scanned document OCR monitoring feed |
| `POST` | `/api/v1/admin/ai/retry/{job}` | Retry failed OCR or AI job |
| `GET` | `/api/v1/admin/escalations` | List escalated issues from Department Heads |
| `POST` | `/api/v1/admin/escalations/{id}/resolve` | Resolve an escalated issue |

---

## Security & Architectural Guarantees
1. **RBAC Protection:** All endpoints strictly guarded by `auth:sanctum` and role check for `admin`.
2. **Audit Logging:** Every administrative override and escalation resolution creates an immutable entry in the `activity_logs` table.
3. **Advisory AI Rule:** Every AI-generated institutional summary or report analysis contains clear neutral phrasing and mandatory human oversight disclaimers.
4. **Performance Optimized:** Database queries utilize eager loading and aggregations without N+1 queries.
