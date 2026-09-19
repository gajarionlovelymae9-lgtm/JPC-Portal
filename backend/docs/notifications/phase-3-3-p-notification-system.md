# JPC EPMS — Notification System (Phase 3.3-P)

## Purpose
Central, policy-safe notification surface for the JPC EPMS AI-driven progress
dashboard: in-app (bell/inbox) notifications, email routing, task deadline /
overdue reminders, review SLA alerts and per-event preference routing.

## Notification types & events
Event vocabulary lives in `app/Enums/NotificationEvent.php` (string-backed enum
`App\Enums\NotificationEvent`). Each event is either:

- **Optional** — user-controllable per channel via preferences.
- **Critical** (`isCritical()`) — institutional policy (task assigned, revision
  required, review SLA, security/system, overdue). May **never** be disabled.

All in-app rows are written through the custom `jpc_database` channel
(`app/Notifications/Channels/JpcDatabaseChannel.php`) into the bespoke
`notifications` inbox (user_id, type, title, message, data, priority, task_id,
reminder_days, read_at). The unique index on
`(user_id, type, task_id, reminder_days)` makes deadline/overdue senders
idempotent, so scheduler re-runs never duplicate rows.

Report workflow notifications (`app/Notifications/Reports/*`, 10 classes)
derive from the abstract `BuildsReportNotification` → `JpcNotification` base
and carry routing metadata + priority.

## Preferences (§37–§41)
- Table `notification_preferences`: one row per `(user_id, event)`.
- `App\Services\Notifications\NotificationPreferenceService` translates a user
  payload into enforced channel routing; critical events are forced on.
- API:
  - `GET  /api/v1/notifications/preferences`
  - `PUT  /api/v1/notifications/preferences`
  - Both under `permission:notifications.view` /
    `permission:notifications.manage`.
- Controller: `Api\V1\Notifications\NotificationPreferenceController`,
  service: `Services\Notifications\NotificationPreferenceService`.

## Event → listener wiring
Your event classes under `app/Events/*` pair with
`app/Listeners/*`; listeners dispatch through Laravel Notifications (queued via
`ShouldQueue` where appropriate). Event names follow the `NotificationEvent`
enum values so preferences, listeners and audit rows use one vocabulary.

## Emails
`app/Mail/*` templates share the branded JPC EPMS layout (title, message,
action button, footer). Delivery respects `email_enabled` preferences unless
the event is critical.

## Security
- Every endpoint authorizes via `NotificationPolicy` — recipients only ever see
  their own rows; `action_id` is never trusted from the client for navigation.
- Critical events bypass user preferences (institutional policy override).
- Failed jobs land in `failed_jobs` (Laravel default) for admin inspection,
  retried with the default backoff; no silent loss.

## Real-time & fallback
If `broadcasting` is configured, unread changes can be pushed over the user's
private channel. The DB channel always records first, so the bell poll fallback
(`/unread`, `/unread-count`, `/read-all`) remains the source of truth.

## Deduplication
Deadline/overdue reminders dedupe via the `(user_id, type, task_id,
reminder_days)` unique index + `firstOrCreate`. Preferences upsert
(idempotent). Achievement/badge/review senders guard against duplicate rows
with the same unique key.

## Test coverage
- `tests/Feature/Api/V1/Notifications/*` — API contract happy path, unread
  count, read-all, RBAC denial for other users' rows.
- `tests/Feature/Notifications/*` — workflow + scheduler command dedup tests.

## Priority
In-app rows carry `priority` (normal/high/critical). Notification UI renders
priority via label + icon, never color only (§80).
