# JPC Portal — HR Guide

How the **HR** role manages employee data and oversees reporting/quality.

> **Consolidated guide:** the management workflows for Admin, HR and Department
> Heads are now covered together in
> [`user-guides/management-guide.md`](user-guides/management-guide.md). This
> page keeps the HR-specific quick reference below and is maintained alongside
> it.

> Demo account: `hr@jpc.local` / `P@ssw0rd2026` (dev only).

## 1. Employee management

- **Create / edit / suspend / reactivate** employees, employee numbers, profile
  fields, employment status.
- Manage **department** and **position** assignment for each employee.
- View **employee history** (personnel change trail).

## 2. Employee data validation

- Review records for completeness and consistency (missing fields, duplicates,
  invalid department/position links).
- These checks surface as **data-quality issues** in the data-quality module.

## 3. Department information

- View and maintain **departments** (name, code, status, head assignment) and the
  **position** catalog.
- Manage department heads (current head, start/end dates).

## 4. Performance visibility (per your permissions)

- View **performance** records and scores where permitted (role-scoped HR routes).
- Employee-level sensitive detail is limited by RBAC; HR does not automatically see
  other departments' private report content unless the permission grants it.
- Performance data is read-only for HR — scores are computed by the system.

## 5. Data quality

- **Data-quality scans** — trigger scans to find missing/inconsistent data.
- **Issues** — review each issue (entity, type, severity), assign/act, mark
  resolved or dismissed; full history retained.
- Use issues to drive employee-data cleanup.

## 6. Reports

- **Reports** — monitor submitted reports across the institution (per your
  permissions): statuses, late reports, review outcomes.
- HR can review/approve where the permission allows; the same human-review principle
  applies (AI assists, humans decide).

## 7. Analytics

- **Analytics hub** — institution-wide trends, comparisons, rankings, distribution.
- **Report exports** — queue CSV/XLSX/PDF exports and download when ready.

## 8. Audit (where authorized)

- **Audit log** / **activity log** — review who changed employee data and when,
  where your role has audit permissions.

## 9. Notifications & announcements

- Send **announcements** to employees/departments.
- Manage notification preferences relevant to HR processes.