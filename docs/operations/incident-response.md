# JPC Portal — Operations: Incident Response

Incident-response runbook for JPC Portal v1.0.0 (Phase 16). Complements the
existing [`../maintenance/incident-response.md`](../maintenance/incident-response.md)
runbook (reused, not duplicated) and this repo's
[`../SECURITY.md`](../SECURITY.md) / [`../DATA_PRIVACY.md`](../DATA_PRIVACY.md).

## 1. Severity classification (§16)

| Level | Definition | Examples |
| --- | --- | --- |
| **P0** | Total outage / data loss / active breach. System unusable or production data exposed/changed. | DB down, mass login compromise, backup+restore failure with data loss, credential leak |
| **P1** | Major degradation — core feature unavailable to many users; no workaround; or high-risk security finding | AI/OCR provider down cluster-wide, report generation failing, confirmed XSS/SQLi |
| **P2** | Noticeable but partial — feature degraded for some users; workaround exists; minor security/data concern | one OCR/AI vendor failing, slow queues, intermittent notification failures, one report wrong |
| **P3** | Low impact / cosmetic / informational | one failed job, minor log noise, stale dashboard, UX papercut |

Escalate promptly: P0/P1 must be declared to all operators immediately; a P2
that is not improving becomes P1.

## 2. Incident lifecycle (§17)

The canonical flow **for every incident**, large or small:

1. **DETECT** — via monitoring (§28) or user/feedback reports.
2. **CLASSIFY** — assign P0–P3 (§16) and the incident type (§3 below).
3. **CONTAIN** — stop the blast radius *first* (disable a misbehaving cron, pause
   a failing provider, revoke a key, block an IP, take the DB offline for
   restore). Containment is allowed to be temporary and rough; correct later.
4. **INVESTIGATE** — read the relevant logs
   ([`monitoring.md`](monitoring.md) §12), query the relevant tables
   read-only, reproduce; never mutate production data while investigating
   (data-guard rule).
5. **RECOVER** — apply the smallest fix that restores service; do not rebuild or
   rewrite functioning code during an incident.
6. **VERIFY** — confirm through the existing health checks (§28) and affected
   user flows, not by assumption. Never claim `HEALTHY` unless the check
   actually ran and passed.
7. **DOCUMENT** — write a maintenance-log entry (via `system:record-maintenance`)
   and the post-incident record (§6).
8. **PREVENT RECURRENCE** — root-cause action: fix, add a monitoring/alerting
   signal, update this runbook, update tests. An incident that produced no
   prevention item is not finished.

## 3. Incident procedures by type

### 3.1 Security incident (§18)

Any suspected unauthorized access, exposure, injection, or key compromise.

1. **DETECT** — login-activity spikes (`failed` events), 403 audit bumps,
   unexpected API abuse, exposed `.env`/keys, user report.
2. **CONTAIN** — revoke the affected sanctum tokens / password /
   provider keys; disable the user; block the source IP if relevant. Do not
   delete evidence.
3. **INVESTIGATE** — read `login_activities`, `personal_access_tokens` (structure
   only), request logs, application logs; preserve the originals.
4. **RECOVER** — rotate secrets in `.env` (never commit the key), re-enable
   legitimate access with new credentials, re-issue tokens.
5. **VERIFY** — confirm the compromise path is closed and normal access works.
6. **DOCUMENT** — `system:record-maintenance "Security Incident" …`, notify the
   institution (per [`../SECURITY.md`](../SECURITY.md)).
7. **PREVENT** — tighten rate limits, review permissions, add detection.

**Never log or print secrets/keys/passwords — including in this runbook's
samples.**

### 3.2 Data incident (§19)

Any corruption, wrong data, deletion, or unauthorized modification
(covers manual edits behind the guard: the app forbids direct data mutation
without a documented reason).

1. **STOP** — stop the writer/cron that is suspected; document who/why per the
   data-guard rule. Do not keep mutating.
2. **PRESERVE** — snapshot affected tables *before* any repair
   (`mysqldump`-style export or a DB snapshot). This is evidence.
3. **BACKUP** — confirm a valid backup exists (see
   [`backup-recovery.md`](backup-recovery.md)); if not, create one now.
4. **IDENTIFY** — find exactly which rows changed and when (`processed_at`,
   `updated_at`, maintenance log, audit).
5. **COMPARE** — diff affected rows against the backup/snapshot.
6. **RESTORE / REPAIR** — restore only the affected rows/tables; never blind
   full-restore over live data unless the whole DB is corrupt.
7. **VALIDATE** — run the DB health check and affected feature flows;
   confirm foreign keys and counts.
8. **DOCUMENT** — maintenance log entry with result `rolled_back` or `completed`,
   and the root cause.

### 3.3 AI incident (§20)

Wrong/dangerous AI output, AI provider outage, leakage.

- **Contain**: flip provider to `fake`/fallback, pause the offending analysis
  feature; the rest of the system must keep working (by design).
- **Investigate** using `ai_analyses` statuses + counts only (never prompts or
  report contents in logs).
- **Recover/Verify**: re-run a sample analysis through the health AI check and
  the `ai` component of `system:check-health`.
- **Document/Prevent**: maintenance log entry; vendor escalation if provider-side.

### 3.4 OCR incident (§21)

OCR provider failure, mass low-confidence, illegible output.

- **Contain**: keep the workflow usable — OCR service failure must not block the
  rest of the platform; mark OCR indicator degraded.
- **Investigate** via `ocr_processings` statuses, low-confidence count,
  failure-rate.
- **Recover/Verify**: fix provider/creds, re-run affected docs.
- **Document/Prevent**: maintenance log + retry plan for the affected files.

## 4. During and after any incident

- Prefer read-only DB queries while investigating. Any data change needs a
  documented reason in the maintenance log (data-guard rule — never mutate
  production data without a documented reason).
- Do not "fix" an incident by rebuilding reusable, already-working components
  or re-running past phases.
- Escalation contacts and on-call order live in
  [`../maintenance/maintenance-readiness.md`](../maintenance/maintenance-readiness.md)
  and the admin handover doc.

## 5. Post-incident record (§17 step 7 — DOCUMENT)

For every incident record: id, date/time, summary, severity, type, root cause,
actions, verification result, prevention action, follow-up owner.

Record it as a maintenance-log entry:

```bash
php artisan system:record-maintenance "Incident Response" "Summary of incident" \
  --module="Incident" --result=completed --performed-by="admin@jpc.local"
```

Allowable outcomes: `completed | failed | pending | rolled_back` (invalid
values are rejected by the command).

## 6. Related

- [`monitoring.md`](monitoring.md) — how incidents get detected.
- [`backup-recovery.md`](backup-recovery.md) — restore procedures.
- [`maintenance.md`](maintenance.md) — routine maintenance (prevention).
- [`../maintenance/incident-response.md`](../maintenance/incident-response.md)
  — original runbook (severity/shutdown/rollback worked examples).
- [`../SECURITY.md`](../SECURITY.md) / [`../DATA_PRIVACY.md`](../DATA_PRIVACY.md).