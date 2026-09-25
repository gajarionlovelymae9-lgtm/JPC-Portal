# JPC Portal — Backup & Restore

This document describes how to back up `acadtrack_db` and how to safely restore it.

> **IMPORTANT**
> - A backup is NOT valid simply because the command completed. You must verify it
>   by restoring it into a separate recovery database and comparing record counts.
> - NEVER test restore procedures against the real production database.
> - On this developer machine the XAMPP `mysql.exe` client is unusable (missing
>   `caching_sha2_password.dll`). Use the PHP/PDO-based backup scripts below, or the
>   XAMPP MySQL tooling (`mysqldump.exe`) directly from a shell where it works.

## 1. What to back up

| Item | Location / command |
| --- | --- |
| Database `acadtrack_db` | MySQL — see below |
| Uploaded report files (private storage) | `backend/storage/app/private/reports` |
| Export files (if any) | `backend/storage/app/private/report_exports` (TTL-managed; re-generable) |
| Application key | `APP_KEY` from `backend/.env` (must be recovered together with the DB) |
| `.env` configuration | `backend/.env`, `frontend/.env` (contains no secrets on frontend) |

The database is the source of truth. Uploaded files and the app key are required for
a complete restore.

## 2. Backup strategy

### Frequency

| Backup | Frequency | Retention |
| --- | --- | --- |
| Full logical dump (`acadtrack_db`) | **Daily** | 14 daily copies |
| Weekly snapshot | **Weekly** (e.g. Sunday) | 8 weekly copies |
| Monthly snapshot | **Monthly** (e.g. 1st) | 12 monthly copies |
| Private files folder | **Daily** alongside the dump | same retention |

Store backups **off-machine** (external drive, network share, or object storage) and
keep at least the latest daily + latest monthly off-machine.

### Recommended schedule

```
Daily   00:30  full dump + files → backups/daily/YYYY-MM-DD/
Weekly  Sun    promote to backups/weekly/YYYY-Www/
Monthly 1st    promote to backups/monthly/YYYY-MM/
```

## 3. Creating a backup

### Option A — `mysqldump` (if the client works on the target machine)

```bash
cd backend
mysqldump --single-transaction --routines --triggers \
  -h 127.0.0.1 -u root -p acadtrack_db > backup.sql
```

On XAMPP the binary is at `C:\xampp\mysql\bin\mysqldump.exe`.

### Option B — PHP/PDO logical dump (works on this machine)

A small table-by-table PDO dump is reliable on this stack. Use `php artisan tinker`
or a one-off script with `DB_*` values from `backend/.env`:

```php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=acadtrack_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$out = "-- acadtrack_db backup ".date('c')."\n";
foreach ($tables as $t) {
    $out .= "DROP TABLE IF EXISTS `$t`;\n";
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
    $out .= $create[1].";\n\n";
    foreach ($pdo->query("SELECT * FROM `$t`") as $row) {
        $cols = implode(',', array_map(fn($c)=>'`'.$c.'`', array_keys($row)));
        $vals = implode(',', array_map(fn($v)=>$pdo->quote((string)$v), array_values($row)));
        $out .= "INSERT INTO `$t` ($cols) VALUES ($vals);\n";
    }
    $out .= "\n";
}
file_put_contents('backup.sql', $out);
```

Also back up the private files folder:

```bash
Copy-Item -Recurse backend/storage/app/private backups/daily/YYYY-MM-DD/private
```

## 4. Backup verification

1. Record the count of rows for a few key tables at backup time.
2. Restore the fresh dump into a throwaway `acadtrack_db_verify` database
   (see §5) — **never** into `acadtrack_db`.
3. Compare table counts (`SHOW TABLES` list and per-table `COUNT(*)`).
4. Confirm migrations table matches:
   ```sql
   SELECT COUNT(*) FROM acadtrack_db_verify.migrations;
   ```
5. Confirm the app boots against the verify DB (health endpoint).

A backup is only considered good after a successful **restore test** that shows
matching counts.

## 5. Restore procedure (recovery database first)

> NEVER restore over the running production database without a full backup first.
> First practice into a recovery database named `acadtrack_db_verify`.

1. **Stop application writes** (or at minimum suspend the app / put Laravel in
   maintenance mode):
   ```bash
   cd backend && php artisan down
   ```
2. **Create a fresh backup** of the current `acadtrack_db` before touching anything
   (see §3), so you can roll back.
3. **Create the recovery database**:
   ```sql
   CREATE DATABASE IF NOT EXISTS acadtrack_db_verify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. **Restore** the dump into the recovery database (via `mysql` client if available,
   or execute the SQL through PDO for MySQL in a script). Example with client:
   ```bash
   mysql -h 127.0.0.1 -u root -p acadtrack_db_verify < backup.sql
   ```
5. **Verify tables** — compare `SHOW TABLES` between `acadtrack_db` and
   `acadtrack_db_verify`.
6. **Verify relationships** — spot-check FK integrity:
   ```sql
   SELECT COUNT(*) FROM report_reviews r
     LEFT JOIN reports p ON p.id = r.report_id WHERE p.id IS NULL;  -- expect 0
   ```
7. **Verify record counts** for every table:
   ```sql
   SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
     WHERE TABLE_SCHEMA='acadtrack_db_verify' ORDER BY TABLE_NAME;
   ```
   Compare against the pre-restore count snapshot.
8. **Run application checks**:
   ```bash
   cd backend && APP_URL=http://localhost:8000 php artisan route:list --except-vendor | Out-Null
   php -r "require 'vendor/autoload.php'; (new Illuminate\Foundation\Application(__DIR__))->make('config'); echo 'ok';"
   ```
   Log in with the seeded demo/admin accounts and open key screens.
9. **Return service to normal** — only when the recovery DB passes all checks:
   ```bash
   cd backend && php artisan up
   ```
   If the recovery database is confirmed correct and you must use it as the live
   database, point `DB_DATABASE=acadtrack_db_verify` in `.env` (or rename it) and
   restart.

## 6. Restoring the app key and files

- The app key must match at restore time (session cookies and encrypted data).
  Keep `APP_KEY` from the backed-up `.env`.
- Copy the backed-up `storage/app/private/reports` back to the same location.

## 7. Automation notes

- Windows Task Scheduler can run the daily dump; a cron/systemd timer is used on
  Linux servers.
- Compress dumps (`gzip`/`tar.gz`) to reduce storage.
- Test the full restore **monthly** (see [`MAINTENANCE.md`](MAINTENANCE.md)).

## 8. Never do

- `php artisan migrate:fresh` in production (destroys data).
- Restoring into the live DB without a fresh backup.
- Storing dumps with passwords in plain text next to the dump.
- Keeping only one backup copy (a single corrupt file = data loss).