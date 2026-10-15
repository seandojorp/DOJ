# Testing and validation

## 1. Automated end-to-end suite (195 checks)

`tests/run_tests.php` drives the running application over HTTP exactly like a browser (session cookies + CSRF tokens),
including truly simultaneous requests, and checks the database directly.

> ⚠️ Run it **only against a disposable test database** freshly imported from the fresh-install schema.
> It refuses to run if `ems_users` or `preload_users` already contain rows.

```
:: 1. Create an empty test database, e.g. doj-employee-test, and import DOJ_EMS_Corrected_Fresh_Install.sql into it
:: 2. Point a copy of the app (or config/config.php temporarily) at that database
:: 3. Run:
C:\xampp\php\php.exe tests\run_tests.php http://localhost/doj-ems
```

Exit code 0 = all passed. After the run, drop the test database and delete `storage\setup.lock`.

### What it covers

| Area | Checks |
|---|---|
| First-run setup | Redirect to setup; password policy; CSRF; **5 simultaneous submissions → exactly 1 admin**; admin has NULL `Preload_Users_ID`; durable marker; GET/POST setup blocked afterwards |
| Login & sessions | Generic failure message; SQL-injection credentials; role redirects; session ID regenerated; `Last_Login` updated; 401/redirect without session; all admin pages render |
| Preloaded Users | Create; normalization (`+63 917…` → `0917…`, lowercase email); DOJ ID / cellphone / email duplicates **each rejected independently**; per-field validation; derived registration status; edit |
| Registration | Values from different preload rows rejected; generic message (no enumeration); 6 password-policy cases; confirmation mismatch; success links to the right preload row; second registration rejected; **5 simultaneous registrations → exactly 1 account**; username uniqueness; no user with NULL preload |
| Access control | Users get 403 on every admin API/page and can't create preload users or change their role |
| Ownership | Submitted `user_id`/`User_ID` ignored; other user's record id → 404; users cannot delete; date-order, required-field and dropdown validation; single-record sections; children, eligibility (`DECIMAL(5,2)`), work (`Position_Tittle`, `Mothly_Salary`), voluntary, L&D, employment, other; table-name injection rejected |
| Personal info | Photo required on first save; registered cellphone/email cannot be overridden; one row per user; disguised non-image rejected; photo access limited to owner/admin; **stored XSS escaped**; 100 % completion |
| Admin record management | List per employee; insert for another employee; cross-employee list; delete |
| Account management | Identity edits sync to personal info and keep the preload link; collisions rejected; can't deactivate/delete/demote self; second admin; user accounts require a preload link; deactivation ends live sessions and blocks login; accounts/preload rows with linked records can't be deleted (RESTRICT honored) |
| Passwords | Wrong current / weak new rejected; change keeps current session and **signs out other sessions**; admin reset signs user out |
| Rate limiting | 6th failed login → 429, even with the correct password |
| Audit | 18 event types recorded; SQL meta-characters in search are safe; **no passwords or hashes in `audit_logs`** |
| Integrity | No cascading deletes; 13 tables; one personal row per user; unique preload links |

## 2. Schema verification

```
C:\xampp\php\php.exe tools\verify_schema.php
```

## 3. Manual browser checklist

1. First visit shows *Create Administrator Account*; after creating it, `/setup` redirects to login.
2. Password checklist ticks live; show/hide buttons work; "Passwords match" indicator updates.
3. Add a preloaded user; try the same email in different case → field error.
4. Register with that employee's details; try again → generic error.
5. As the employee: complete every section (photo upload preview, "same as residential address"), confirm the cellphone/email fields are locked.
6. Edit the URL to `/admin/dashboard` → *Access denied*. Change a record id in the browser dev tools → *Record not found*.
7. As admin: deactivate the employee while they are signed in elsewhere → their next action returns them to login.
8. Try deleting a registered preloaded user → offered *Deactivate account* instead.
9. Check the audit log shows each action with your IP.
10. Resize to phone width: sidebar becomes a slide-out menu; tables scroll horizontally.

## 4. Validation rules reference

| Field | Rule |
|---|---|
| Password | `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$`, max 72 bytes (bcrypt) — frontend and backend |
| Username | 4–50 chars, letters/digits/`.`/`_`/`-`, starts with letter or digit |
| Cellphone | PH mobile: `09XXXXXXXXX`, `9XXXXXXXXX`, `639XXXXXXXXX`, `+639XXXXXXXXX` (spaces/dashes allowed) → stored `09XXXXXXXXX` |
| Email | RFC format, lowercased; ≤ 50 chars for registration records |
| DOJ ID | trimmed, spaces removed, uppercased; letters, digits, `- / . _`; ≤ 50 |
| Employee info | Every column is NOT NULL → every field required; "N/A" accepted where marked; date ranges must be in order; ZIP 4 digits; rating 0–100 |
| Photo | JPEG/PNG/WebP by magic bytes, ≤ 5 MB upload, ≥ 50×50 px; re-encoded to JPEG ≤ 600 px and ≤ 900 KB |
