# JPC Portal — Release Notes

## v1.0.0

- **Version:** JPC Portal v1.0.0 (final release)
- **Release date:** 2026-09-25
- **Distribution:** `backend/`, `frontend/`, `docs/`, `README.md`, `CHANGELOG.md`, `VERSION`

### Major modules

- Role-based portals: **Admin**, **HR**, **Department Head**, **Employee**
- Employee & department & position management with full history
- Academic year → annual plan → target (department + employee) planning
- Task management: assignments, progress, references, comments, review, overdue
  detection
- Report workflow: draft → submitted → under review → approved / revision /
  rejected, with immutable versions, uploaded files, timeline and reviewer
  comments
- Performance records, score breakdowns, progress snapshots, KPIs
- Achievements: badges, monthly achievements, employee of the month
- Data-quality scans and issue lifecycle
- Reporting & analytics: employee / department / institution / monthly /
  achievements / late / pending / targets / data-quality / management summary
- Report exports (CSV / XLSX / PDF), queued, owner-scoped, TTL-managed
- Notifications: in-app + optional mail, preferences, delivery logs
- Full audit trail: audit logs, activity logs, login activities
- Administrative maintenance: system health, maintenance log, backup
  verification, user feedback

### AI features

- Offline deterministic AI engine by default (`AI_PROVIDER=fake`) — the
  pipeline runs with no external account
- Optional OpenAI-compatible provider (`AI_PROVIDER=openai` + `AI_ENDPOINT`)
- Report analysis: summary (short/medium/detailed), requirement validation,
  reference analysis, similarity indicators (Dice coefficient), findings
- AI Chat with authorized, role-scoped analytical tools
- AI output is **advisory only** — never approves or rejects a report

### OCR features

- Offline deterministic OCR engine by default (`OCR_PROVIDER=fake`)
- Optional Google Cloud Vision integration (REST `annotateImage` / `TEXT_DETECTION`)
  via REST key or service-account credentials
- Supported upload types: PDF, DOCX, JPG, JPEG, PNG (server-side MIME map)
- Confidence-based low-evidence flagging; OCR failure never invalidates a report

### Security

- Laravel Sanctum **SPA cookie** authentication (no bearer tokens in the
  browser); CSRF cookie handshake with stateful API middleware
- Role-based access control (4 roles) + permission catalog + policies +
  service-level scoping (RBAC matrix in `app/Enums/Permissions.php`)
- Full IDOR testing in the automated suite (cross-user / cross-department
  blocked)
- Upload validation: MIME + extension allow-list, size cap, regenerated file
  names, private storage with authorized download only
- Login rate limiting, account-status checks, login activity recording
- API global throttle (60 req/min), login limiter (5/min), export limiter
  (10/min user), reports limiter (120/min user)
- Secrets policy: `.env` git-ignored; AI/OCR keys server-side only; no
  secrets in logs (redaction in monitoring service)

### Known limitations

- Real AI/OCR depend on provider availability, network and quota; offline `fake`
  engines are the default so the pipeline works with no external accounts
- OCR accuracy depends on document quality
- Similarity detection is an **indicator**, not proof of plagiarism — requires
  human validation
- AI findings are assistive; a human makes every approval/rejection decision
- Email delivery depends on mail configuration (optional; failures log, never
  crash)
- Frontend ships as a single ~869 kB chunk (code-splitting is a documented
  future enhancement; no runtime impact)
- Scale/performance depends on infrastructure (XAMPP single-machine default)
- XAMPP `mysql.exe` client is unusable on some machines (missing
  `caching_sha2_password.dll`) — use `php artisan`/PDO for DB operations

### Deployment requirements

- PHP >= 8.2, Composer 2.x, Node.js >= 20, MySQL 8 / MariaDB >= 10.4
- Backend: `composer install` → `.env` → `php artisan key:generate` →
  `php artisan migrate` → structural `db:seed` → `php artisan optimize`
- Frontend: `npm install` → `npm run build`
- Queue worker (`queue:work`) and scheduler (`schedule:run` via cron) must be
  running for OCR/AI/exports/notifications and the 7 scheduled tasks
- **Set `SEED_DEVELOPMENT_DATA=false` on non-development environments** — the
  flag defaults to `true` and is the sole gate for demo data (see
  [`INSTALLATION.md`](INSTALLATION.md) §4.4)

### Verification status (release evidence)

| Check | Result |
| --- | --- |
| Backend test suite | 406 passed / 1880 assertions, 0 failures |
| Frontend tests | 87 passed |
| Frontend lint | oxlint 0 errors (58 pre-existing benign warnings) |
| Frontend build | PASS (`tsc -b` + Vite) |
| Live demo walkthrough | 93/93 endpoints green for all four roles |
| System health | `system:check-health` → ok |

See [`CHANGELOG.md`](../CHANGELOG.md) for the full change history and
[`VERSION_MANAGEMENT.md`](VERSION_MANAGEMENT.md) for how future changes are
versioned.