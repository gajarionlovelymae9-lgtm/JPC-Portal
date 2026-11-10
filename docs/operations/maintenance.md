# JPC Portal — Operations: Maintenance

Routine maintenance, change control, versioning, maintenance windows, and
rollback for JPC Portal v1.0.0 (Phase 16). Complements the pre-existing
[`../maintenance/`](../maintenance/) runbooks (reused, not duplicated).

## 1. Scheduled tasks (verified — 7 tasks in `routes/console.php`)

| Schedule | Task |
| --- | --- |
| Daily 05:00 | `system:check-health` |
| Daily 05:30 | `system:verify-backups` |
| Daily 03:15 | `reports:cleanup-exports` |
| Daily 07:00 | `tasks:send-deadline-reminders` |
| Daily 07:30 | `tasks:send-overdue-notifications` |
| Weekly Mon 06:30 | `RunDataQualityScanJob('full', null, null)` |
| Monthly 1st 06:00 | `reports:generate-monthly` |

Routes are: 05:00 health scan, 05:30 backup verification, 03:15 export TTL
cleanup, 07:00/07:30 task notifications, weekly data-quality scan, monthly
report. If these stop running (cron/scheduler down), that is an incident —
address it before ad-hoc fixes.

## 2. Maintenance window (§26)

Before/after checklists for any maintenance that touches the app, DB, or
deployments. Times below assume the quiet-window recommendation (early
morning; e.g., 03:00–06:00 before the daily scans).

### Before any maintenance

1. Confirm a valid backup exists (§30) or create one (data change).
2. Pick the window outside active use; announce in advance.
3. Note the current app version (`VERSION` file / `/api/v1/health`).
4. Ensure the operator has admin access-recorded identity (`--performed-by`).
5. Have the rollback plan ready (§4).

### After maintenance

1. Run `php artisan system:check-health --source=manual` — all components ok.
2. Spot-check the affected feature flows (not just status codes).
3. Confirm scheduler ran (check the 05:00/05:30 logs) — a deploy must not
   silently kill tasks.
4. Restart `queue:work` workers if code changed (long-lived workers).
5. Record the maintenance in the maintenance log:
   ```bash
   php artisan system:record-maintenance "<Type>" "<What and why>" \
     --module="<Module>" --result=completed --performed-by="admin@jpc.local"
   ```
   Valid results: `completed|failed|pending|rolled_back`.

## 3. User feedback process (§23)

Feedback is collected via the user-feedback feature and admin lifecycle.

- **Categories (as implemented):** `bug`, `incorrect_information`,
  `ui_problem`, `ai_concern`, `ocr_concern`, `feature_request`, `usability`,
  `other`.
- **Lifecycle (as implemented):** `OPEN → UNDER_REVIEW → IN_PROGRESS →
  RESOLVED`; terminates as `REJECTED` or `CLOSED`.
- **Priorities (as implemented):** `low`, `medium`, `high`, `critical`.
- **At minimum record:** id, date, reporter, category, description, severity,
  status, assigned person, resolution.
- Users can list **only their own** feedback; admins can list/update all
  (verified by `SystemMaintenanceApiTest`).

## 4. Change & version control (§24, §25)

### Change-request flow (§24)

1. REQUEST — submit the change with motivation.
2. REVIEW — owner triages scope (this phase must not add unrelated features,
   rebuild working pieces, or alter architecture).
3. IMPACT ANALYSIS — DB? scheduler? secrets? auth? docs to update?
4. APPROVAL — explicit before implementation.
5. VERSION — bump per §4.2.
6. IMPLEMENT — smallest change possible; preserve the frozen v1.0.0 baseline.
7. TEST — run the relevant PHP feature tests + frontend build checks.
8. DOCUMENT — update the affected docs + `CHANGELOG.md`.
9. RELEASE — deploy + post-maintenance checklist (§2 after).

Full reference: [`../VERSION_MANAGEMENT.md`](../VERSION_MANAGEMENT.md).

### Versioning (§25)

Application versioning is **independent** of the Laravel framework version
(Laravel 12 is pinned).

| Version | Meaning | Bump on |
| --- | --- | --- |
| `v1.0.0` | Current frozen baseline (Phase 13–15, released). | — |
| `v1.0.x` (patch) | Bug fixes, no behavior change | fix without new behavior |
| `v1.1.x` (minor) | Backward-compatible features | new compatible feature |
| `v2.0.0` (major) | Breaking change | breaking change |

## 5. Rollback procedure (§27)

Use rollback when a deploy/change breaks current stable behavior; it is **not**
a substitute for proper incident handling.

App code:

1. Check the current version in `VERSION` (or `/api/v1/health` → `api_version`).
2. Revert to the previous tagged release (git checkout the tag; rebuild
   frontend assets; `php artisan optimize:clear`).
3. Restart workers and verify via §2 "After maintenance".

Database:

- **Do not auto-restore the DB** unless a data change is being rolled back and
  it is documented (§19 in [incident-response.md](incident-response.md)).
- If rollback requires reverting data, restore only the affected scope from a
  real backup (see [backup-recovery.md](backup-recovery.md)) — never a blind
  full restore when the whole DB is fine.
- Record `--result=rolled_back` in the maintenance log for transparency.

Scheduler/queue:

- Re-add/remove the affected scheduled task in `routes/console.php` (must stay
  exactly the 7 tasks in §1), then restart the scheduler/workers.

## 6. Routine maintenance cadence

- **Daily** — health scan & backup verify (scheduled), glance at today's log for
  `CRITICAL`/`EMERGENCY` (see [monitoring.md](monitoring.md)).
- **Weekly** — data-quality scan results (Mon 06:30), failed-job list
  (`php artisan queue:failed`).
- **Monthly** — security maintenance (rotate credentials if warranted, review
  roles — see [`../maintenance/security-maintenance.md`](../maintenance/security-maintenance.md)),
  AI/OCR provider reviews ([`../maintenance/ai-maintenance.md`](../maintenance/ai-maintenance.md),
  [`../maintenance/ocr-maintenance.md`](../maintenance/ocr-maintenance.md)),
  database maintenance ([`../maintenance/database-maintenance.md`](../maintenance/database-maintenance.md)),
  and the monthly management report (1st 06:00).
- **Quarterly/on-demand** — full backup restore-test in a staging copy (see
  [backup-recovery.md](backup-recovery.md) §5), dependency patch review,
  release-readiness review.

## 7. Related

- [`monitoring.md`](monitoring.md) — what to check and when.
- [`incident-response.md`](incident-response.md) — when maintenance goes wrong.
- [`backup-recovery.md`](backup-recovery.md) — backups and restore testing.
- [`../maintenance/`](../maintenance/) runbooks and
  [`../ADMIN_HANDOVER.md`](../ADMIN_HANDOVER.md),
  [`../DEPLOYMENT.md`](../DEPLOYMENT.md),
  [`../GO_LIVE_CHECKLIST.md`](../GO_LIVE_CHECKLIST.md).