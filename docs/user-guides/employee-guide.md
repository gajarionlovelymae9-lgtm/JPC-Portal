# JPC Portal — Employee User Guide

How an **Employee** uses JPC Portal, from first login to final report approval.

> Development demo account (dev only — change on real installations):
> `employee@jpc.local` / `P@ssw0rd2026`.

---

## 1. Login

1. Open the portal URL (local development: `http://localhost:5173`).
2. Enter your email and password → **Sign in**.
3. Your **employee dashboard** loads: assigned tasks, your targets, recent
   reports and notifications.

If you cannot log in:

- Check the email is exactly as registered by HR.
- Use **Forgot/Reset password** if available, or ask HR/Admin to reset or
  reactivate your account.

## 2. Dashboard

Your dashboard shows:

- assigned tasks and their progress percentage;
- your targets and progress toward them;
- recent reports and their statuses;
- unread notifications.

## 3. View tasks

1. Open **My Tasks** (or the dashboard task list).
2. Each row shows the task title, priority, due date, status and progress.
3. Open a task to see:
   - references (links/supporting material);
   - comments (yours, reviewers', collaborators');
   - attachments/evidence;
   - the activity timeline and progress history.

## 4. Update progress

1. In a task, record a progress update with a percentage and a note.
2. Comment on the task to ask questions or hand off work.
3. Attachments added here are visible to authorized reviewers only.

## 5. View targets

Open **Targets** to see your assigned targets for the academic year — title,
value, unit and completion status.

## 6. Create a report

1. Open the report area → **New report**.
2. Choose the related task (if applicable), enter a title and content.
3. The report starts in `draft` — you can keep editing while it is a draft.

## 7. Upload evidence (document)

1. In the report, attach your document. Supported types: PDF, DOCX, JPG, JPEG,
   PNG (server-side MIME + size validation apply).
2. The file is stored in **private storage** — only authorized users can
   download it.
3. On upload, the OCR job extracts the document text (offline `fake` engine by
   default; Google Vision when configured server-side).

## 8. View AI processing

After upload/processing, the report detail page shows the AI-generated
**summary** and **findings** — overview, validation checks, and
reference/similarity indicators.

- These are **assistive indicators only**; they never decide your report's
  outcome.
- If you disagree with a finding, note it when you submit or discuss it with
  your reviewer.

## 9. Submit the report

1. Review your content and attachments.
2. Click **Submit**:
   - status `draft` → `submitted` (workflow-validated);
   - a submitted version is **immutable**; resubmitting creates a new version.
3. You receive a notification when the reviewer acts.

## 10. Track the review

The report detail page shows the **timeline** (submitted, processing, under
review, …) and any reviewer comments. Statuses you may see: `draft`,
`submitted`, `processing`, `under_review`, `revision_required`, `resubmitted`,
`approved`, `rejected`.

## 11. Respond to a revision

If the reviewer sets **revision required**:

1. Open the report → read the reviewer comments.
2. Edit the content / replace files (a new version is recorded).
3. **Resubmit** → status becomes `resubmitted` and the report re-enters review.

Repeat as needed; every submission creates a new immutable version with its own
timeline entry. You can compare versions from the report detail view.

## 12. View performance

Open **Performance**:

- your performance records,
- score breakdowns (task completion, report completion, on-time submission,
  report approval, target achievement),
- completion rate and progress snapshots for the period/academic year.

Scores are computed by the system; if something looks wrong, raise it with your
reviewer — do not attempt to change scores manually.

## 13. View achievements

Open **Achievements**:

- badges you have earned and progress toward badge criteria;
- monthly achievements (top performers each month);
- your rank / employee-of-the-month where applicable.

## 14. Update profile

Open your profile and correct any personal details. Some fields (e.g.
employment type, department, position) are managed by HR — request changes
through HR rather than editing them yourself if the UI does not allow it.

## 15. Change password

Use the **Change password** action in your account menu:

1. Enter your current password.
2. Enter a new password meeting the password policy (minimum length, as
   enforced by the application).
3. Confirm and save. You are signed in with the new password immediately.

## 16. Notifications

- **Notifications** lists in-app notifications: assignments, deadline
  reminders, overdue alerts, review decisions and achievements.
- Set **channel preferences** (in-app, and optionally email) so you get the
  right messages.
- A notification that could not be delivered to email is logged in
  `notification_logs` (never crashes the system).

## 17. Feedback

Use **Feedback** to report a bug, incorrect information, UI issue, or AI/OCR
problem. Admins review feedback in `/admin/feedback`. Your AI-quality ratings
are recorded for improvement without altering any AI results or scores.

---

## Tips

- Mark progress as you work — reviewers see up-to-date progress.
- Submit reports with clear content and evidence attached; AI findings and
  human reviewers both benefit from complete documents.
- Keep your password private; report suspicious logins to HR/Admin.

See also [`ADMIN_GUIDE.md`](../ADMIN_GUIDE.md), [`HR_GUIDE.md`](../HR_GUIDE.md)
and [`DEPARTMENT_HEAD_GUIDE.md`](../DEPARTMENT_HEAD_GUIDE.md) for an overview of
the other roles, and [`TROUBLESHOOTING.md`](../TROUBLESHOOTING.md) if something
does not work.