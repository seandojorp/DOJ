# DOJ Region 02 EMS — Installation on XAMPP (Windows)

Target: XAMPP with **PHP 8.2+** and **MariaDB 10.4+**, Apache with `mod_rewrite` (enabled by default in XAMPP).
No Composer, Node or internet connection is needed — all front-end libraries are bundled in `public/assets/vendor`.

---

## 1. Copy the application

1. Extract the archive so the folder is `C:\xampp\htdocs\doj-ems\` (it must contain `app`, `public`, `config`, …).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.

The app will be available at **http://localhost/doj-ems/**. (Another folder name works too; the base URL is detected automatically.)

## 2. Create the database (fresh install only)

> ⚠️ `DOJ_EMS_Corrected_Fresh_Install.sql` is a **fresh-install** schema. Import it only into an **empty** database.
> Never import it over a database that already has data. For an existing database, see §8.

1. Open **http://localhost/phpmyadmin** → **New** → database name `doj-employee`, collation `utf8mb4_unicode_ci` → **Create**.
2. Select `doj-employee` → **Import** → choose `DOJ_EMS_Corrected_Fresh_Install.sql` → **Import**.
3. You should see 13 tables: `audit_logs`, `children-information`, `civil_service_eligibility`, `educational-background`,
   `employment-information`, `ems_users`, `family_background`, `learning_development`, `other_information`,
   `personal-information`, `preload_users`, `voluntary_work`, `work_experience`.

No administrator or sample data is seeded — the first administrator is created in the browser (§6).

## 3. Create a least-privilege database account (recommended)

In phpMyAdmin → **SQL** tab, run (choose your own strong password):

```sql
CREATE USER 'doj_ems_app'@'localhost' IDENTIFIED BY 'Choose-A-Strong-Password-Here';
GRANT SELECT, INSERT, UPDATE, DELETE ON `doj-employee`.* TO 'doj_ems_app'@'localhost';
FLUSH PRIVILEGES;
```

The application never needs CREATE/ALTER/DROP. (On a private development PC you *may* use `root` with an empty password, but don't do this on a shared or production server.)

## 4. Configure

1. Copy `config\config.example.php` to `config\config.php`.
2. Edit `config\config.php`:
   - `db.user` / `db.pass` — the account from §3.
   - `app.key` — a random secret. Generate one in a terminal:
     `C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32));"`
   - Leave `app.env` = `production` (use `development` only on a private machine to see error details).
   - `lookups` — labels for the numeric *Type of L&D* and *Conducted/Sponsored* codes (edit to your standard).
3. Make sure `storage\` is writable by Apache (it is by default on Windows XAMPP).

## 5. Verify the schema (Phase 1)

Open a terminal in `C:\xampp\htdocs\doj-ems` and run:

```
C:\xampp\php\php.exe tools\verify_schema.php
```

It runs `SHOW TABLES`, `DESCRIBE` and `SHOW CREATE TABLE` checks (13 tables, unique keys, `DECIMAL(5,2)` rating,
RESTRICT foreign keys) and changes nothing. All checks should report `[OK]`.

## 6. Initialize the first administrator

1. Browse to **http://localhost/doj-ems/** — you are redirected to **Create Administrator Account**.
2. Enter a username and a password (≥10 characters with upper, lower, number and special character).
3. You are sent to the login page. The setup page is now permanently closed: it stays closed even if
   administrators are later deactivated (a durable marker is kept in `audit_logs` and `storage/setup.lock`).

## 7. First steps after login

1. **Preloaded Users** → add each employee's DOJ ID, cellphone and email (from HR).
2. Employees register at **http://localhost/doj-ems/register** using those exact three values.
3. Administrators can also create accounts directly under **Registered Accounts → Create Account**.

## 8. Existing databases and migrations

- **Never** re-import the fresh-install SQL over a populated database.
- Back up first: phpMyAdmin → Export, or `C:\xampp\mysql\bin\mysqldump.exe -u root -p doj-employee > backup.sql`.
- `database/migrations/` contains two **optional** migrations. The application does **not** need them and never runs them:
  - `001_OPTIONAL_learning_development_text_columns.sql` — store L&D type/sponsor as text instead of codes.
  - `002_OPTIONAL_employment_promotion_dates_nullable.sql` — allow empty promotion dates.
  Each file explains the matching code change required. Review and test on a copy before applying.

## 9. Production hardening checklist

- Serve over **HTTPS** and set `session.secure` to `true` in `config.php`.
- Point Apache's DocumentRoot at `doj-ems/public` if you can (the root `.htaccess` already blocks `app/`, `config/`, `storage/`, `database/`, `tests/`, `tools/`).
- Keep `app.env` = `production`; review `storage/logs/php-error.log` periodically.
- Use the least-privilege DB account (§3); keep regular database backups.
- XAMPP's default `max_allowed_packet` is 1 MB; photos are re-encoded and kept under 900 KB to fit.
  If you raise `upload.photo_max_store_bytes`, raise `max_allowed_packet` in `C:\xampp\mysql\bin\my.ini` too.

## 10. Troubleshooting

| Symptom | Fix |
|---|---|
| "Configuration missing" | Copy `config/config.example.php` to `config/config.php`. |
| "The database is currently unavailable" | MySQL not running, or wrong `db.*` settings. Set `app.env` to `development` temporarily to see the error. |
| 404 on every page except the home page | `mod_rewrite` disabled or `AllowOverride None`. In `httpd.conf`, set `AllowOverride All` for `htdocs`. |
| Photo upload fails | Check `upload_max_filesize`/`post_max_size` in `C:\xampp\php\php.ini` (≥ 6M) and that the `gd` extension is enabled. |
| Locked out after failed logins | Wait 15 minutes (configurable in `security.*`), or an administrator resets the password. |
