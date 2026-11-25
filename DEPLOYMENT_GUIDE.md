# JPC Portal: Cloud Deployment Guide (Render & Vercel)
**Capstone Project**: *JPC Portal: An AI-Driven Employee Progress Monitoring Dashboard Using Natural Language Processing-Based Algorithm*  
**Institution**: John Paul College, Roxas, Oriental Mindoro  

This guide walks through deploying the JPC Portal using:
* **Render**: Laravel 12 Backend API (Docker Web Service with SQLite, Nginx, and PHP 8.3)
* **Vercel**: React 19 + Vite Frontend SPA

---

## Part 1: Deploy Backend to Render (5 Minutes)

1. Open **[dashboard.render.com](https://dashboard.render.com)** and log in with GitHub.
2. Click **New +** (top right) and select **Web Service**.
3. Choose **Build and deploy from a Git repository** and connect `JPC-Portal`.
4. Configure the Web Service settings:
   * **Name**: `jpc-portal-api` (or any custom name)
   * **Region**: `Singapore` (closest to Philippines)
   * **Branch**: `master`
   * **Root Directory**: `backend`
   * **Runtime**: **`Docker`**
   * **Instance Type**: **Free**
5. Scroll down to **Environment Variables** and click **Add Environment Variable** for each:

| Key | Value | Notes |
| :--- | :--- | :--- |
| `APP_NAME` | `JPC Portal API` | |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_KEY` | *(Click "Generate" or leave empty)* | Render will auto-generate or run `key:generate` |
| `APP_URL` | `https://jpc-portal-api.onrender.com` | Replace with your actual Render URL |
| `DB_CONNECTION` | `sqlite` | |
| `DB_DATABASE` | `/var/www/html/database/database.sqlite` | Auto-created & seeded on first boot |
| `SEED_DEVELOPMENT_DATA` | `true` | Pre-seeds test faculty accounts |
| `SEED_ADMIN_PASSWORD` | `P@ssw0rd2026` | Unified password for all roles |
| `SEED_HR_PASSWORD` | `P@ssw0rd2026` | |
| `SEED_DEPARTMENT_HEAD_PASSWORD` | `P@ssw0rd2026` | |
| `SEED_EMPLOYEE_PASSWORD` | `P@ssw0rd2026` | |
| `AI_PROVIDER` | `gemini` | |
| `AI_MODEL` | `gemini-3.5-flash-lite` | Multi-model failover active |
| `AI_ENDPOINT` | `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions` | |
| `AI_BASE_URL` | `https://generativelanguage.googleapis.com/v1beta/openai` | |
| `AI_API_KEY` | `your_gemini_api_key_here` | Use the pre-configured key from JPC_Portal_Setup_Guide.docx |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173` | Temporary until Vercel URL is created |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173` | Temporary until Vercel URL is created |
| `SESSION_DRIVER` | `database` | |
| `SESSION_LIFETIME` | `120` | |
| `SESSION_SAME_SITE` | `none` | Allows cross-origin cookies between Vercel & Render |
| `SESSION_SECURE_COOKIE` | `true` | Required for SameSite=None |
| `SESSION_PARTITIONED_COOKIE` | `true` | Chrome/Safari third-party cookie compatibility |

6. Click **Create Web Service**.
7. Render will build the Docker container, run database migrations, seed the accounts, and start Nginx + PHP-FPM.
8. Once the build finishes, copy your Render URL (e.g. `https://jpc-portal-api.onrender.com`).
   * Test it by visiting: `https://jpc-portal-api.onrender.com/api/v1/health` (should return `{"success": true, ...}`).

---

## Part 2: Deploy Frontend to Vercel (3 Minutes)

1. Open **[vercel.com](https://vercel.com)** and log in with GitHub.
2. Click **Add New...** -> **Project**.
3. Locate `JPC-Portal` under your repositories and click **Import**.
4. Configure Project Settings:
   * **Framework Preset**: `Vite`
   * **Root Directory**: Click **Edit** and select **`frontend`** ⚠️ *(Crucial! Do not leave as root)*.
   * **Build Command**: `npm run build`
   * **Output Directory**: `dist`
5. Expand **Environment Variables** and add:
   * **Name**: `VITE_API_BASE_URL`
   * **Value**: `https://jpc-portal-api.onrender.com/api/v1` *(Use your actual Render URL from Part 1)*
6. Click **Deploy**.
7. In about 45 seconds, Vercel will complete the build and display your live production link (e.g. `https://jpc-portal.vercel.app`).

---

## Part 3: Final 30-Second Connection

Now that you have your live Vercel URL (e.g. `https://jpc-portal.vercel.app`):

1. Go back to your **Render Dashboard** -> click your `jpc-portal-api` service -> go to **Environment**.
2. Update the two frontend domain variables:
   * `CORS_ALLOWED_ORIGINS`: `https://jpc-portal.vercel.app` *(include https://)*
   * `SANCTUM_STATEFUL_DOMAINS`: `jpc-portal.vercel.app` *(domain only, NO https://)*
3. Click **Save Changes**. Render will automatically redeploy in 30 seconds.

---

## Part 4: Testing Your Live Deployment

Open your live Vercel URL in your browser and test each role:

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@jpc.local` | `P@ssw0rd2026` |
| **Human Resources (HR)** | `hr@jpc.local` | `P@ssw0rd2026` |
| **Department Head** | `departmenthead@jpc.local` | `P@ssw0rd2026` |
| **Employee** | `employee@jpc.local` | `P@ssw0rd2026` |

### Key Features to Verify Live
1. **Executive Dashboard**: Log in as `admin@jpc.local` -> verify KPI cards, Department Progress, and Password Change Requests tab.
2. **Employee Security**: Log in as `employee@jpc.local` -> go to Security -> test requesting a password change.
3. **JPC AIDE (Gemini AI)**: Ask the AI assistant to summarize employee progress or help compose an NLP report.
