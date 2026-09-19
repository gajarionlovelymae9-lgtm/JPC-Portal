# JPC Portal — Notifications, Deadline Reminders, Review Alerts & Activity Timeline (Phase 3.3-I)

Laravel 12 backend + React frontend for the **in-app notification inbox** and the
**append-only activity timeline** built on top of the Phase 3.3-B/3.3-E/3.3-F
Planning & Task Management domain. Follows the same rule as every other screen:
the UI is a **presentation layer only** — delivery, deduplication, scoping,
priority routing and authorization are computed by the Laravel API. The frontend
never decides who gets notified or what a log shows.

```
React component → service (fetch) → Laravel /api/v1/... → Policy → Service → MySQL
```

- **Stack:** Laravel 12, MySQL, React 19, Vite 8, TypeScript, `react-router-dom` 7
- **HTTP:** native `fetch` via the shared `apiRequest` client
- **Base URL:** `VITE_API_BASE_URL` (defaults to `/api/v1`)
- **Out of scope:** email/SMS/push delivery, WebSockets live-push, AI/NLP,
  badge/achievement awarding — all preserved as future modules.

---

## 1. In-app notification system

### Persistence
Fully reuses the bespoke `notifications` table (Phase 3.3-B migration) — **no new
table required**. Notification payloads are written through Laravel's
notification system via a custom channel:

| Item | Detail |
| ---- | ------ |
| Channel | `jpc_database` (`JpcDatabaseChannel`) |
| Registration | `Notification::extend('jpc_database', ...)` in `AppServiceProvider::boot()` |
| Base class | `JpcNotification` (abstract) + trait `BuildsTaskNotification` in `app/Notifications/Tasks/` |
| Table | `notifications` (`user_id`, `type`, `task_id`, `title`, `message`, `data`, `priority`, `read_at`, `delay_until`, unique `(user_id, type, task_id, reminder_days)`) |

A notification may hold a JSON `data` payload and standard routing columns
(`route_notifications+route_mail+...` via the phase migration) so a future
mail/push channel can drop in without schema work.

### Delivery & deduplication
`NotificationService` (`app/Services/Notifications/NotificationService.php`) is
the single sender every workflow uses. It:

- deduplicates by `(user_id, type, task_id, reminder_days)` via `alreadySent()`,
- stores `priority` from the task's priority (`urgent` stays `urgent`, any other
  value normalizes to `normal`),
- maps `task_id`/`task_code` into `action_url` so the frontend can deep-link,
- exposes `sendTaskAssigned`, `sendDeadlineReminder`, `sendOverdueNotification`,
  `sendTaskSubmitted`, `sendTaskResubmitted`, `sendReviewed`, `sendTaskCancelled`.

Routes are registered with the `notifications.view` / `notifications.manage`
permissions; the default route adds `permissions` (the signed-in user) unless
the caller overrides via `->to()`.

### Workflow events
Workflow code (services under `app/Services/TaskManagement/`) dispatches
domain events (`App\Events\TaskManagement\`, `ShouldDispatchAfterCommit`) with
dedicated listeners in `app/Listeners/Notifications/` that call
`NotificationService`. This keeps the service layer free of notification
coupling while guaranteeing emails/reminders follow the same rules.

---

## 2. Activity timeline (append-only audit log)

`ActivityLogService` (`app/Services/ActivityLogs/ActivityLogService.php`) writes
rows into the `activity_logs` table (Phase 3.3-B) with:

- `action` dotted keys (`auth.login`, `task.created`, `task.assigned`,
  `task.submitted`, `task.reviewed`, `progress.updated`, …),
- `subject_type` + `subject_id` for polymorphic context (Task, Employee, …),
- JSON `metadata` (task code, before/after status, reviewer, …),
- `user_id`, `ip_address`, `user_agent` captured from the request when present.

**Append-only:** the service uses `forceFill` + guarded columns so clients can
never overwrite audit fields; the API rejects any write route to activity logs.

Audit points are recorded by the same service layer that enforces the workflow,
so every state transition shows up without the frontend having to report it.

---

## 3. Scheduled reminders

`config/task.php` defines the reminder policy:

- `reminders`: days-before-deadline alerts (e.g. `[7, 3, 1, 0]`) — sent to the
  assignee as each threshold passes, deduplicated via `reminder_days`.
- `overdue_notification_roles`: management roles notified when a task becomes
  overdue.

Commands (registered in `routes/console.php`):

| Command | Schedule | Purpose |
| ------- | -------- | ------- |
| `tasks:send-deadline-reminders` | daily 07:00 | Deadline reminders for assignable assignments |
| `tasks:send-overdue-notifications` | daily 07:30 | Overdue notices to assignee + management |

Both are idempotent (dedupe key prevents repeat sends) and safe on empty data.

---

## 4. API endpoints

All under `auth:sanctum` in `routes/api.php`; every method authorizes via
`NotificationPolicy` / scoping in `ActivityLogController`.

| Endpoint | Purpose |
| -------- | ------- |
| `GET  /api/v1/notifications` | Paginated inbox (`filter=unread\|read`, `type`, `category`, `page`, `per_page`) |
| `GET  /api/v1/notifications/unread` | Unread-only list |
| `GET  /api/v1/notifications/unread-count` | Fast badge counter |
| `POST /api/v1/notifications/read-all` | Marks every unread notification read |
| `GET  /api/v1/notifications/{notification}` | Single notification |
| `POST /api/v1/notifications/{notification}/read` | Mark one as read |
| `DELETE /api/v1/notifications/{notification}` | Remove one |
| `GET  /api/v1/activity-logs` | Paginated, filterable timeline (see below) |
| `GET  /api/v1/activity-logs/{activityLog}` | Single log |
| **`PUT/POST/DELETE /api/v1/activity-logs/{activityLog}`** | **403 immutable** |
| `GET  /api/v1/me/tasks/upcoming` | Employee's next actionable deadlines |

**Activity log filters:** `user_id`, `action`, `subject` (Task/Employee/…),
`subject_id`, `search`, `from`, `to`, `page`, `per_page`.

**Access control**
- Notifications: owner-only (policy + scoping), `notifications.view`.
- Activity logs: scoped by role — admin/HR see everything; department head sees
  logs where the subject `Task` belongs to their department **or** the actor is
  an employee of their department; employees see only their own (via the
  `activity_logs.view` permission enforced on both route and UI).
- Notifications write/read/deletion rights use `notifications.manage` where
  applicable.

---

## 5. Frontend surfaces

| Surface | Route | Gate |
| ------- | ----- | ---- |
| Header bell (badge + dropdown + mark-all-read) | all pages | authenticated |
| Notification inbox | `/notifications` | `notifications.view` |
| Activity Logs page | `/activity-logs` | `activity_logs.view` |
| Task-detail “System activity” timeline | `/tasks/:taskId` | `activity_logs.view` |
| Employee dashboard “Upcoming deadlines” | `/employee/dashboard` | `tasks.view` (own data only) |

Implementation highlights:

- `services/notificationService.ts` (list/unread/count/read-all/read/delete,
  activity-log list/get)
- `services/taskService.ts → getMyUpcomingTasks()`
- `hooks/useNotifications` (list + 60s unread-count polling), `useActivityLogs`,
  `useMyUpcomingTasks`
- `components/NotificationBell`, `components/ActivityTimeline`
- `pages/NotificationsPage`, `pages/ActivityLogsPage`

The bell and inbox always render real API values; the frontend never fabricates
unread counts or filters categories.

---

## 6. Verification

Backend (`backend/`):

```
php artisan test          # 254 tests / 1216 assertions green
```

Frontend (`frontend/`):

```
npm run lint              # 0 errors (2 fast-refresh warnings only)
npm run build             # tsc -b && vite build succeeds
npm run test -- --watch=false   # 16 files / 79 tests green
```

Key test suites: `tests/Feature/Api/Notifications/*`, `tests/Feature/Api/ActivityLogs/*`,
`tests/Feature/Notifications/*` (workflow + scheduled commands), plus
`MyUpcomingTasksTest` and the frontend `App.test.tsx` route coverage.