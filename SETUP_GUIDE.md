# JPC Portal: Quickstart & Deployment Guide
**System Setup, Environment Configuration & Antigravity AI Workflow**

> **Capstone Project**: *JPC Portal: An AI-Driven Employee Progress Monitoring Dashboard Using Natural Language Processing-Based Algorithm*  
> **Institution**: John Paul College, Roxas, Oriental Mindoro  
> **Target Audience**: Classmates, Collaborators, and Developers using Antigravity AI  

---

## 1. Overview
The **JPC Portal** is an enterprise-grade academic monitoring system featuring:
- **Backend**: Laravel 12 REST API with Sanctum SPA authentication and SQLite portability.
- **Frontend**: React 19, TypeScript, Vite, Tailwind CSS, Lucide icons.
- **AI Integration**: Google Gemini API (`gemini-3.5-flash-lite` with automatic multi-model failover) for NLP report summarization, validation, task assistance (JPC AIDE), and employee insights.
- **Design Theme**: Official John Paul College Royal Blue (`#1E3A8A` / `#1E4ED8`) and Slate Neutrals (`#F8FAFC`, `#FFFFFF`, `#E2E8F0`).

---

## 2. System Prerequisites
Before getting started, make sure your machine has:
1. **PHP 8.2 or 8.3** with the following extensions enabled in `php.ini`:
   - `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `openssl`, `fileinfo`, `zip`
2. **Composer** (v2.x) — [getcomposer.org](https://getcomposer.org)
3. **Node.js** (v18+ or v20+) and **npm** — [nodejs.org](https://nodejs.org)
4. **Git** — [git-scm.com](https://git-scm.com)
5. **Antigravity AI** — installed and connected to your workspace.

---

## 3. Step 1: Clone the Repository
Open PowerShell or Git Bash and clone the repository:
```bash
git clone https://github.com/gajarionlovelymae9-lgtm/JPC-Portal.git
cd JPC-Portal
```

---

## 4. Step 2: Configure the Backend (Laravel 12 API)

### 4.1 Install PHP Dependencies
```bash
cd backend
composer install
```

### 4.2 Configure the `.env` File
Create a new file named `.env` in the `backend/` folder and paste the exact configuration below:

```dotenv
APP_NAME="JPC Portal API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE="database/database.sqlite"

# Development sample data (Phase 3.1-D)
SEED_DEVELOPMENT_DATA=true
SEED_ACADEMIC_YEAR_START=2026-06-01
SEED_ACADEMIC_YEAR_END=2027-05-31
SEED_ADMIN_PASSWORD=P@ssw0rd2026
SEED_HR_PASSWORD=P@ssw0rd2026
SEED_DEPARTMENT_HEAD_PASSWORD=P@ssw0rd2026
SEED_EMPLOYEE_PASSWORD=P@ssw0rd2026

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173

SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

# ---------- AI Report Processing & JPC AIDE (Google Gemini) ----------
OCR_PROVIDER=fake
AI_PROVIDER=gemini
AI_MODEL=gemini-3.5-flash-lite
AI_ANALYSIS_VERSION=v1.0
AI_MAX_TOKENS=2048
AI_TEMPERATURE=0.1
AI_TIMEOUT=120
AI_ENDPOINT=https://generativelanguage.googleapis.com/v1beta/openai/chat/completions
# Use the Gemini API key provided in the JPC_Portal_Setup_Guide.docx or team chat
AI_API_KEY=your_gemini_api_key_here
AI_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai

OCR_LANGUAGE=en
OCR_MIN_CONFIDENCE=0.55
REFERENCE_ANCHOR_DEFAULT=0.35
SIMILARITY_SIMILAR_THRESHOLD=0.7
SIMILARITY_HIGH_SIMILAR_THRESHOLD=0.85
AI_REPORTS_QUEUE=default
AI_REPORTS_MAX_INPUT_CHARS=60000

REPORT_FILES_DISK=local
REPORT_FILES_DIRECTORY=reports
REPORT_MAX_FILE_SIZE_KB=51200

CACHE_STORE=database
MAIL_MAILER=log
VITE_APP_NAME="${APP_NAME}"
```

### 4.3 Initialize Application & Database
Run the following commands in the `backend/` directory:
```powershell
# 1. Generate encryption key and clear configuration cache
php artisan key:generate
php artisan config:clear

# 2. Create the SQLite database file if missing
New-Item -ItemType File -Path database/database.sqlite -Force

# 3. Run all migrations
php artisan migrate --force

# 4. Seed database with departments, roles, tasks, and users
php artisan db:seed --force
```

---

## 5. Step 3: Configure the Frontend (React 19 + TypeScript + Vite)
In a new terminal, navigate to the `frontend/` directory and install packages:
```bash
cd ../frontend
npm install
```

---

## 6. Step 4: Run the Application

Keep two terminals open (or let Antigravity run background tasks):

### Terminal 1 — Backend API:
```powershell
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```
> Running at: **`http://127.0.0.1:8000`**

### Terminal 2 — Frontend Portal:
```powershell
cd frontend
npm run dev -- --host 127.0.0.1 --port 5173
```
> Running at: **`http://127.0.0.1:5173`**

---

## 7. Demo Accounts & Credentials
All seeded development accounts share the default password: **`P@ssw0rd2026`**

| Role | Email Address | Default Password | Features to Test |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@jpc.local` | `P@ssw0rd2026` | Executive Dashboard, KPI Targets, System Health, Audit Trail, User Accounts |
| **Human Resources (HR)** | `hr@jpc.local` | `P@ssw0rd2026` | Employee Directory, Evaluations, Achievements & Badge Catalogue |
| **Department Head** | `departmenthead@jpc.local` | `P@ssw0rd2026` | Task Assignment, Departmental Progress, Report Review Workspace |
| **Employee** | `employee@jpc.local` | `P@ssw0rd2026` | Task Submissions, Report Creation, My AI Insights, JPC AIDE Chat |

---

## 8. Working with Antigravity AI
When opening this repository in Antigravity:
1. **Open Root Folder**: Open the parent `JPC-Portal` folder so Antigravity has visibility over both `backend/` and `frontend/`.
2. **Automated Server Management**: You can prompt Antigravity:
   > *"Start both frontend and backend dev servers in the background."*
3. **Automated Verification**: You can prompt Antigravity:
   > *"Run frontend tests and backend tests to make sure everything passes."*
4. **Theme Guardrails**: Antigravity knows to preserve the official **John Paul College Royal Blue** theme (`#1E3A8A` / `#1E4ED8`) and never revert to greenish tints.

---

## 9. Troubleshooting Common Issues
- **Error: *"These credentials do not match our records."***  
  Run `php artisan db:seed --force` in `backend/` to refresh user accounts with password `P@ssw0rd2026`.
- **Error: *"CSRF token mismatch" (HTTP 419)***  
  Ensure your browser opens `http://127.0.0.1:5173/` or `http://localhost:5173/` matching the stateful domains in `.env`.
- **Google Gemini Rate Limits (HTTP 429)**  
  The application is pre-configured with `gemini-3.5-flash-lite` and an automatic multi-model failover in `HttpAiProvider.php` to prevent interruptions.
