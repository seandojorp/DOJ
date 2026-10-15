# Department of Justice Region 02 Employees Management System (EMS)

Secure, database-driven employee records system for the Department of Justice – National Prosecution Service.
PHP 8.2 (no framework, no Composer) · MariaDB 10.4 · Bootstrap 5 · DataTables · SweetAlert2 · Chart.js · Font Awesome.
Runs on stock XAMPP; all front-end libraries are bundled locally (works offline / on an intranet).

**Install:** see [INSTALL.md](INSTALL.md) · **Tests:** see [TESTING.md](TESTING.md)

## Schema

Built against the authoritative `DOJ_EMS_Corrected_Fresh_Install.sql` (13 tables, database `doj-employee`).
**No schema changes are required.** Legacy column spellings (`Eligibility_Tittle`, `Tittle`, `Position_Tittle`,
`Mothly_Salary`) are preserved in SQL and shown with readable labels; hyphenated tables are always back-quoted.
Features that might have needed new columns were implemented without them:

| Feature | How it works without a new column |
|---|---|
| Durable first-run marker | `SYSTEM_INITIALIZED` row in `audit_logs` + `storage/setup.lock`; setup also closes while any admin row exists |
| Concurrent first-run protection | MariaDB `GET_LOCK()` + file lock + re-check inside a transaction |
| Invalidate other sessions after password change/reset | Session stores an HMAC fingerprint of the password hash; every request re-checks it |
| Immediate effect of deactivation / role change | Account re-read from `ems_users` on every request |
| Login & registration rate limiting | Counts recent `LOGIN_FAILURE` / `REGISTER_FAILURE` rows in `audit_logs` per username and IP |

Two **optional** migrations (L&D text columns; nullable promotion dates) are in `database/migrations/` with explanations. They are not applied automatically.

## Project layout

```
doj-ems/
├── .htaccess                 routes everything to public/, blocks internal folders
├── public/                   web root: index.php (front controller) + assets/ (css, js, vendor libs)
├── app/
│   ├── bootstrap.php, helpers.php, routes.php
│   ├── Core/                 Database (PDO), Router (access guards), Session, Csrf, Auth, Audit, Validator, View…
│   ├── Services/             SetupService, AccountService, PreloadService, Modules (field definitions),
│   │                         EmployeeRecords, PersonalInfoService, PhotoService, ProfileService
│   ├── Controllers/          Setup, Auth, Admin, Preload, Account, Records, Audit, User
│   └── Views/                layouts, auth, admin, user, shared, partials, errors
├── config/config.example.php copy to config.php
├── database/migrations/      optional, reviewed migrations only
├── storage/                  logs, setup lock (must be writable)
├── tools/verify_schema.php   Phase 1 schema check (read-only)
└── tests/run_tests.php       end-to-end acceptance suite (195 checks)
```

## Roles

| | Administrator | Regular user |
|---|---|---|
| Dashboard | Statistics, charts, recent registrations & activity | Welcome, photo, DOJ ID, completion, summaries |
| Preloaded Users | Full CRUD, filter by derived registration status | — |
| Registered Accounts | Create, edit username / DOJ ID / cellphone / email / role, reset password, (de)activate, delete with safeguards | — |
| Employee information | View/insert/update/delete for every employee (per employee or across all) | View/insert/update **own** records only; cannot delete |
| Account settings | Change own password | Read-only identity; change own password only |
| Audit logs | Search/filter all events | — |

All permissions are enforced on the server (route guards + ownership checks), not just hidden in the UI.

## API endpoints (JSON, session + CSRF header `X-CSRF-Token`)

| Method & path | Access | Purpose |
|---|---|---|
| `POST /api/setup` | first run only | Create first administrator |
| `POST /api/auth/login` · `POST /api/auth/register` | public | Sign in / self-register |
| `POST /logout` · `POST /api/account/password` | signed in | Sign out / change own password |
| `GET /api/records/{section}` · `POST /api/records/{section}` · `PUT /api/records/{section}/{id}` | signed in | Own records (users); any employee via `user_id` (admins) |
| `DELETE /api/records/{section}/{id}` | admin | Delete a record |
| `GET/POST /api/personal` · `GET /photo/{userId}` | signed in | Personal information (+ photo) |
| `GET /api/profile/completion` | signed in | Completion by section |
| `GET/POST /api/admin/preload`, `GET/PUT/DELETE /api/admin/preload/{id}` | admin | Preloaded users |
| `GET/POST /api/admin/accounts`, `GET/PUT/DELETE /api/admin/accounts/{id}`, `POST …/{id}/password`, `POST …/{id}/status` | admin | Accounts |
| `GET /api/admin/stats` · `GET /api/admin/employees` · `GET /api/admin/records/{section}` · `GET /api/admin/audit-logs` | admin | Dashboard, search, cross-employee lists, audit |

Sections: `family`, `children`, `education`, `eligibility`, `work`, `voluntary`, `learning`, `employment`, `other` (and `personal` via `/api/personal`).

## Security summary

bcrypt `password_hash()` · PDO prepared statements, identifiers only from a whitelist · CSRF tokens on every state change ·
output escaping (server `e()`, client `EMS.esc`) and a strict Content-Security-Policy (no inline scripts) ·
HttpOnly/SameSite=Strict cookies, Secure over HTTPS, strict session mode, ID regeneration on login, idle + absolute timeouts ·
rate limiting · generic registration errors (no account enumeration) · photo validation by magic bytes + GD re-encode (strips metadata) ·
ownership checks that ignore any submitted `User_ID` for regular users · audit trail with no passwords, hashes or field values ·
last-active-administrator protection · ON DELETE RESTRICT honored (deactivate instead of delete).
