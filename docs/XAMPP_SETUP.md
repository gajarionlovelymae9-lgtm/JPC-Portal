# JPC Portal — XAMPP Setup (Local Development)

Step-by-step guide to install and run the JPC Portal locally with the **XAMPP**
stack on Windows.

## 1. Install XAMPP

Download XAMPP (with **PHP 8.2** and **MySQL/MariaDB**) from the official
apachefriends.org site and run the installer. Install to `C:\xampp`.

## 2. Start Apache and MySQL

1. Open the XAMPP Control Panel.
2. Click **Start** next to **Apache** (port 80 by default; if occupied, see
   [TROUBLESHOOTING.md](TROUBLESHOOTING.md) — convention here uses the CLI
   `php artisan serve` on port 8000 instead of Apache for the API).
3. Click **Start** next to **MySQL** (port 3306). Apache is only required if you want
   to serve the React build through it; the dev workflow below uses the Vite dev
   server + `php artisan serve` and does not need Apache.

> If MySQL reports "MySQL shutdown unexpectedly", see
> [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md).

## 3. Verify PHP

```bash
php -v
```

Expected: `PHP 8.2.x`. If `php` is not recognized, add `C:\xampp\php` to your PATH
(or call the full path `C:\xampp\php\php.exe`).

## 4. Verify Composer

```bash
composer -V
```

If missing, install Composer (Windows installer) and ensure it is on PATH.

## 5. Verify Node.js / npm

```bash
node -v    # expected >= 20
npm -v
```

## 6. Create the database

Start MySQL, then create the application database (via phpMyAdmin console or a
MySQL shell where available):

```sql
CREATE DATABASE IF NOT EXISTS acadtrack_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

> Automated tests use a separate `acadtrack_db_test` database which Laravel creates
> on demand (see `phpunit.xml`).

## 7. Run the backend (Laravel 12)

```bash
cd backend
composer install

copy .env.example .env      # create your environment file
php artisan key:generate

# edit .env: set DB_DATABASE=acadtrack_db, DB_USERNAME=root, DB_PASSWORD=
# (XAMPP default root password is empty)

php artisan migrate
php artisan db:seed          # roles, permissions, demo accounts, sample dataset

php artisan serve            # → http://localhost:8000
```

If `composer` is not global, use `C:\xampp\php\composer.phar` or the installer path.

## 8. Run the frontend (React)

```bash
cd frontend
npm install

copy .env.example .env       # VITE_API_BASE_URL=http://localhost:8000/api/v1
npm run dev                  # → http://localhost:5173 (proxies /api + /sanctum)
```

Open **http://localhost:5173** and log in with a demo account (see README).

## 9. Queues & scheduler (optional on Windows dev)

```bash
cd backend
php artisan queue:work        # local worker; keep a terminal open
php artisan schedule:work     # local scheduler; keep a terminal open
```

## 10. Verification

- `GET http://localhost:8000/api/v1/health` returns `{ success: true }`.
- Log in as employee, department head, HR, and admin and verify their dashboards.
- Submit a report with a document upload; OCR + AI summary complete with the
  offline `fake` providers.
- `php artisan test` passes (uses `acadtrack_db_test`).