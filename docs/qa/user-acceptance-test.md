# JPC Portal — User Acceptance Test (UAT)

Phase 17 final acceptance validation · Date: 2026-09-26
Product: JPC EPMS AI-Driven System v1.0.0 (JPC Portal)

## How to read this sheet
| Status | Meaning |
|---|---|
| **PASS** | Executed successfully over real HTTP (browser-equivalent SPA flow) or proven by automated suite + live endpoint. |
| **PARTIAL** | Function verified at service/API layer; end-to-end UX not fully exercised. |
| **BLOCKED** | Cannot be executed in this environment (external provider/config requirement missing). |
| **NOT TESTED** | No automation exists and not performed manually (documented honestly). |

Demo accounts: `admin@jpc.local`, `employee@jpc.local`, `departmenthead@jpc.local`, `hr@jpc.local` — default dev password `P@ssw0rd2026` (restored at close of QA).

## UAT scenarios

### A. Authentication & session
| # | Scenario | Steps | Expected | Result |
|---|---|---|---|---|
| 1 | Login success | POST `/api/v1/auth/login` with valid creds, Origin+CSRF | 200 `Login successful`, permissions present | **PASS** |
| 2 | Session lifecycle | `/sanctum/csrf-cookie` → login → `/auth/me` → logout → `/auth/me` | me 200 before logout; logout 200; me 401 after | **PASS** |
| 3 | Invalid credentials | Wrong password | 401 generic message (no user enumeration) | **PASS** |
| 4 | CSRF flow | SPA-style token exchange | Works (post-login session regeneration requires fresh token — verified) | **PASS** |
| 5 | Rate limiting | >5 rapid attempts | 429 `Too Many Requests` (automated suite) | **PASS** |

### B. RBAC & role isolation
| # | Scenario | Result |
|---|---|---|
| 6 | Admin can access admin dashboard, RBAC catalog, system health | **PASS** |
| 7 | Employee login returns employee-only permission set | **PASS** |
| 8 | Employee is denied admin RBAC (403) | **PASS** |
| 9 | Employee is denied admin dashboard summary (403) | **PASS** |
| 10 | Department head scope locked to own department (automated) | **PASS** |
| 11 | Unauthenticated access to protected endpoints (401) | **PASS** |

### C. Employee workflows
| # | Scenario | Result |
|---|---|---|
| 12 | Employee views own tasks / upcoming tasks / progress | **PASS** (automated) |
| 13 | Employee creates and updates draft report | **PASS** (automated `ReportWorkflowTest`) |
| 14 | Employee submits report → state transition | **PASS** |
| 15 | Reviewer adds comment and requests revision | **PASS** |
| 16 | Reviewer approval updates downstream state | **PASS** |
| 17 | Late/overdue tasks reported in management dashboard | **PASS** (automated) |
| 18 | Report upload accepts all supported formats; OCR record completes per format | **PASS** (automated) |

### D. AI / OCR
| # | Scenario | Result |
|---|---|---|
| 19 | AI processing dashboard accessible to authorized user | **PASS** |
| 20 | Process queues report; idempotent (no duplicate analysis) | **PASS** |
| 21 | AI summary/validation auth (cross-report access blocked) | **PASS** |
| 22 | AI system health (13/13 requests ok) | **PASS** (provider `fake`) |
| 23 | Real AI provider inference / real OCR engine | **BLOCKED** (no external provider configured; app uses `fake` provider by design) |
| 24 | AI chat & tool execution | **NOT TESTED** (no automation; live chat not exercised) |
| 25 | AI authorization feature tests | **NOT TESTED** (tests are empty stubs) |

### E. Data, export, audit
| # | Scenario | Result |
|---|---|---|
| 26 | CSV/XLSX/PDF export streams valid and scoped | **PASS** (automated, incl. audited export) |
| 27 | Activity/audit logs: admin list; dept head scoped; employee denied | **PASS** |
| 28 | Data-quality scan + dashboard health score | **PASS** |
| 29 | Dev dataset integrity (FK/history/seed idempotency) | **PASS** |
| 30 | Demo data present (15 users, 15 employees, 24 tasks, admin exists) | **PASS** (restored) |

### F. Frontend
| # | Scenario | Result |
|---|---|---|
| 31 | TypeScript build (tsc -b) + Vite production build | **PASS** |
| 32 | Lint (oxlint) — 0 errors, 0 new warnings | **PASS** |
| 33 | Browser run in dev (vite dev server 127.0.0.1:5173, API proxy) | **PASS** at HTTP layer via CORS/Origin tests; visual click-through **NOT TESTED** (no UI automation configured) |

## Acceptance decision
- **Blocking issues:** none.
- **Gaps to close before claiming full UAT closure:** D-24/25 (AI chat + tool exec tests and live chat acceptance). D-23 is BLOCKED by external provider, must stay documented.
- **Overall UAT:** **PARTIAL** — all executable scenarios pass; browser UI automation and real-AI scenarios are explicitly BLOCKED/NOT TESTED rather than assumed.

Sign-off: backend functional acceptance PASS; real-AI acceptance pending provider enablement.