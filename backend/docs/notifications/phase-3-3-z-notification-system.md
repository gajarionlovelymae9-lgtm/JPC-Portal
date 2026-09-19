# Phase 3.3-Z — Complete Notification & Communication System

## Overview
Phase 3.3-Z establishes the enterprise-grade central notification, transactional communication, system announcements, and delivery monitoring engine of the **JPC Portal**.
It ensures no employee, Department Head, HR personnel, or Institutional Administrator misses any milestone, task assignment, approaching deadline, overdue deliverable, report review decision, revision note, AI/OCR processing state, or executive announcement.

---

## Architecture & Core Components

### 1. In-App Notification Center (`/notifications`)
- **Header Notification Bell (`NotificationBell.tsx`):** Unread count badge, real-time polling every 30 seconds, quick dropdown preview of latest 5 items with direct deep-links and "Mark all as read".
- **Notification Inbox:** Categorized filtering (`Tasks`, `Reviews`, `Deadlines`, `System`), mark as read, delete, and visual priority badges (`High`, `Critical`).
- **Deep Linking:** Automatic routing to authorized resources (e.g. `/employee/tasks/:id`, `/employee/reports/:id`).

### 2. User Notification Preferences (`/settings/notifications`)
- **Granular Multi-Channel Control:** Configure `In-App` and `Email` channels per workflow event.
- **Institutional Policy Enforcement:** Critical alerts (`TaskAssigned`, `RevisionRequired`, `TaskOverdue`, `EscalationCreated`, `EscalationResolved`, `AiProcessingFailed`, `OcrFailed`, `System`) are permanently locked ON to preserve institutional governance and compliance.

### 3. Internal Messaging & Communications (`/messages`)
- **Role-Scoped Channels:**
  - **Employee:** Communicates with own Department Head, HR, and Admin.
  - **Department Head:** Communicates with intra-department faculty, HR, and Admin.
  - **HR:** Communicates with Admin and all employees.
  - **Admin:** Institution-wide messaging capability.
- **Thread Workspace:** Split-pane interface with search, conversation listing, unread indicators, and message composer.

### 4. Institution Announcements (`/admin/announcements` & Dashboard Banner)
- **Targeted Broadcasting:** Broadcast to `All Employees`, specific `Departments`, or specific `Roles`.
- **Status Lifecycle:** `Draft` ➔ `Scheduled` ➔ `Published` ➔ `Expired`.
- **Global / Dashboard Banner (`AnnouncementBanner.tsx`):** Priority-colored banners (`Normal`, `High`, `Critical`) with localStorage dismissibility.

### 5. Delivery Telemetry & Admin Monitoring (`/admin/notifications`)
- **Telemetry Dashboard:** Live tracking of `Total Sent`, `In-App Bell`, `Email Channel`, `Failed`, `Queued`, and `Read Rate %`.
- **Transmission Logs & Failure Queue:** Searchable transmission logs with error messages and an administrative **Retry Engine** (`POST /api/v1/admin/notifications/{log}/retry`).

### 6. Automated Scheduler & Reminder Engine
- **Configurable Intervals:** 7 days before, 3 days before, 1 day before, and Due Today.
- **Idempotency & Duplicate Protection:** Senders verify existing notifications via `(user_id, type, task_id, reminder_days)` unique index.
- **Console Commands:**
  - `php artisan app:send-deadline-reminders`
  - `php artisan app:check-overdue-tasks`
  - `php artisan app:check-target-risks`

---

## API Endpoints Summary

| Method | Route | Description |
|---|---|---|
| `GET` | `/api/v1/notifications` | Paginated in-app notifications with filters |
| `GET` | `/api/v1/notifications/unread-count` | Live unread counter |
| `POST` | `/api/v1/notifications/{id}/read` | Mark single notification as read |
| `POST` | `/api/v1/notifications/read-all` | Mark all notifications as read |
| `DELETE` | `/api/v1/notifications/{id}` | Delete notification |
| `GET` | `/api/v1/notifications/preferences` | Retrieve notification preferences |
| `PUT` | `/api/v1/notifications/preferences` | Update notification preferences |
| `GET` | `/api/v1/messages/conversations` | List user conversation threads |
| `GET` | `/api/v1/messages/conversations/{id}` | Conversation detail & messages |
| `POST` | `/api/v1/messages` | Send message or start thread |
| `POST` | `/api/v1/messages/{id}/read` | Mark conversation as read |
| `GET` | `/api/v1/messages/recipients` | Authorized recipient options |
| `GET` | `/api/v1/announcements/active` | Active published announcements |
| `GET` | `/api/v1/admin/notifications/metrics` | Notification delivery telemetry |
| `GET` | `/api/v1/admin/notifications/logs` | Searchable notification logs |
| `POST` | `/api/v1/admin/notifications/{log}/retry` | Retry failed notification |
| `GET` | `/api/v1/admin/announcements` | List announcements |
| `POST` | `/api/v1/admin/announcements` | Create announcement |
| `PUT` | `/api/v1/admin/announcements/{id}` | Update announcement |
| `DELETE` | `/api/v1/admin/announcements/{id}` | Delete draft/expired announcement |
| `POST` | `/api/v1/admin/announcements/{id}/publish` | Publish announcement |

---

## Non-Authority Principle
The notification system is solely an alert mechanism and **NOT** the source of truth. Authoritative data permanently resides in the relational database tables (`tasks`, `task_assignments`, `reports`, `report_versions`, `employee_targets`, `achievements`, `users`).
