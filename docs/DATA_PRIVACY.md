# JPC Portal — Privacy & Data Handling

How JPC Portal collects, stores, protects, retains and deletes data.

> **Principle:** collect and expose only the information the system needs. Every
> access path is scoped by role and ownership.

## 1. Employee information

**What is stored:** name, employee number, email, department, position, employment
status, profile fields, personnel history.

**Access control:**

- HR/Admin manage employee records (per permission).
- A department head sees employees in their own department.
- An employee sees **only their own** profile (self-service routes).
- Cross-user access is enforced server-side (IDOR protection).

**Retention:** kept for the employment lifecycle; soft-delete (`deleted_at`)
preserves history rather than hard-deleting, unless a deletion request requires
permanent removal (see §8).

## 2. Report information

**What is stored:** report content, title, deadline, status, versions, references,
review comments, timeline events.

**Access control:**

- The authoring employee sees their own reports.
- The assigned reviewer (department head/admin per permission) sees reports in
  their scope.
- Reporting/analytics endpoints are scoped by `ResolvesReportingScope`.
- Status transitions are validated (`ReportWorkflowService`); versions are immutable
  once submitted.

**Retention:** retained for the academic year(s) they belong to; archived reports
(`archived` status) are retained for audit/history.

## 3. Uploaded files (OCR documents, evidence)

**What is stored:** original filename, stored filename, mime type, size, checksum,
file bytes.

**Storage & protection:**

- Private disk only (`storage/app/private/reports`) — **no public URL, no public
  symlink**.
- Download requires authentication + ownership/permission check on every request.
- Filename regenerated server-side; user-supplied paths are never used.
- Size/mime limits enforced (`REPORT_MAX_FILE_SIZE_KB`).

**Retention:** follows the parent report; removed on permanent report deletion or by
a documented retention policy.

## 4. AI processing

**What is processed:** report text/content (and OCR output) is sent to the AI engine
for summary/validation/similarity/insights.

**Controls:**

- Default engine is **offline** (`AI_PROVIDER=fake`) — no external transfer.
- With a real provider, only the minimum text needed is sent (bounded by
  `AI_REPORTS_MAX_INPUT_CHARS`); **no credentials** are ever sent to the model.
- Results stored per report (`ai_analyses`, `ai_summaries`, `ai_validation_*`,
  `ai_similarity_results`, `ai_findings`).
- AI is read-only over authorized data — it cannot modify records
  (see [`AI_GOVERNANCE.md`](AI_GOVERNANCE.md)).

## 5. OCR processing

**What is processed:** document text extraction from uploaded files.

**Controls:**

- Runs **server-side only**; credentials (Google Vision) are never in the frontend
  or the repository.
- `OCR_PROVIDER=fake` default keeps processing local.
- Extracted text (`ocr_processings.raw_text`) is tied to the report file and inherits
  its access controls.

## 6. Performance data

**What is stored:** performance records, score breakdowns, progress snapshots,
badges, monthly achievements/ranks.

**Access control:** scoped by role (employee self, department head dept, admin/HR
institution). Scores are computed deterministically by the system — not editable by
users or AI.

## 7. Audit logs

**What is stored:** who changed what, when, old/new values (`audit_logs`),
activity stream (`activity_logs`), login events (`login_activities`).

**Controls:** admin/authorized roles only; logs contain **no** passwords, keys, or
raw file contents. Logs are retained for the configured period for accountability.

## 8. Retention, deletion & archival

| Data | Retention | Deletion |
| --- | --- | --- |
| Employee records | Employment lifecycle | Soft-delete (recoverable); permanent removal on validated request |
| Reports + versions | Academic year(s) / archive | Soft-delete/archive; permanent removal removes files too |
| Uploaded files | Follows report | Deleted with report or by retention job |
| Export files | TTL (`expires_at`) | Automatic via `reports:cleanup-exports` (daily 03:15) |
| Audit/activity logs | Configured period | Retained for accountability; purge by policy |
| Notifications | Until read + configured window | Prunable |
| AI results | Follows report | Follows report |

**Deletion procedure:** confirm identity & scope → soft-delete (default) → cascade
files/children → record the action in the audit log. Never delete other users' data
without authorization.

## 9. Access control summary

- Authentication required for every non-public route.
- RBAC (roles + permissions) gates route groups and policies.
- Object-level ownership checked server-side (IDOR protection).
- Private files streamed through authorized controllers only.
- See [`SECURITY.md`](SECURITY.md) for the full control set.

## 10. Data minimization

- Only fields required by the feature are stored/returned.
- API responses return the minimum needed (resources, not full table dumps).
- Health/status endpoints expose no sensitive values.
- Demo/seed data is **fictional** — never real employee personal information.