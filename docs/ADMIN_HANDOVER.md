# JPC Portal — Administrator Handover

Operational runbook for the person who runs the system after go-live.

## 1. Starting the system

```bash
# Backend (API) — port 8000
cd backend
php artisan serve

# Frontend (SPA) — port 5173 (dev)
cd frontend
npm run dev        # or serve the built frontend/dist in production
```

Production also needs:

```bash
cd backend
php artisan queue:work        # persistent worker (systemd/supervisor)
php artisan schedule:run      # via cron every minute
```

Verify: `GET http://localhost:8000/api/v1/health` → `{ "success": true, ... }`.

## 2. Manage users

- Create/edit/suspend/reactivate employees under **Employees**.
- Reset a password (employee self-service password change, or admin reset).
- Suspend an account immediately for offboarding (login blocked; history retained).
- Review `login_activities` for suspicious logins.

## 3. Manage departments

- Create/rename/archive departments (soft delete).
- Maintain the position catalog.
- Assign/update department heads (`department_heads`, current head flag).

## 4. Monitor reports

- **Reports** list: filter by status, employee, department, period, late flag.
- Open a report to see versions, files, references, AI summary/findings, timeline.
- Watch for stuck `processing` reports → check the queue worker (§8).

## 5. Check performance

- **Performance** module: records, score breakdowns, progress snapshots, completion
  rate, KPI.
- **Monthly performance / achievements** for rankings and employee-of-month.
- Scores are computed by the system — investigate the Performance service if a value
  looks wrong (do not hand-edit).

## 6. Review AI findings

- Open a report → AI summary + findings (possible issues, validation score,
  similarity indicators).
- **Treat findings as advisory.** Similarity is an indicator, not proof. Verify
  against the actual document before acting.
- AI never approves/rejects — you make the decision (approve / revision required /
  reject) from the report detail view.

## 7. Review data-quality issues

- **Data-quality** → run a scan, review issues (entity, type, severity, status).
- Mark resolved/dismissed; history retained in `data_quality_issue_histories`.
- Use findings to clean up employee/department data.

## 8. Generate reports / exports

- **Reporting** → choose report type (employee/department/institution/monthly,
  achievements, late, pending, targets, data-quality, management summary).
- **Report exports** → queue CSV/XLSX/PDF; download when status = `ready`.
- Exports expire (TTL) and are cleaned daily by `reports:cleanup-exports`.

## 9. Use AI Chat

- **AI Chat** → ask questions about your institution's data:
  - "Show all late reports this month."
  - "Who has the highest completion rate this month?"
  - "Which department has the highest performance?"
- Answers come from authorized AI tools scoped to your role; tool calls are logged.

## 10. Inspect audit logs

- **Audit log** — sensitive mutations (who, what, old/new values).
- **Activity log** — broad activity stream.
- Filter by user/action/date when investigating an incident.

## 11. When AI or OCR fails

**OCR stuck / failed:**

1. Confirm the worker is running: `php artisan queue:work`.
2. Check `OCR_PROVIDER` (default `fake` works offline; `google_vision` needs
   credentials in `.env`).
3. Retry: `php artisan queue:retry all`; list: `php artisan queue:failed`.
4. Logs: `backend/storage/logs/laravel-*.log`.

**AI failed:**

1. Confirm `AI_PROVIDER` (default `fake` = offline, always works).
2. For a real provider check `AI_API_KEY` / `AI_ENDPOINT` / `AI_TIMEOUT` and quota.
3. Requeue the analysis or `php artisan queue:retry all`.
4. The pipeline records the failure — the report stays reviewable; the app does not
   crash.

**Never** expose or paste API keys into the frontend, logs, or tickets.

## 12. Daily/weekly operations

See [`MAINTENANCE.md`](MAINTENANCE.md) for the daily/weekly/monthly checklist and
[`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md) for backup/restore.