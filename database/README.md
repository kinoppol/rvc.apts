# Database setup — rvc_apts (MariaDB 10+)

The PHP app (`../login.php` etc.) needs the `rvc_apts` MariaDB database.

## Easiest: the web installer

With WAMP's MariaDB running, browse to **`../install.php`** (e.g.
`http://localhost/rvc.apts/install.php`) and click **ติดตั้งฐานข้อมูล**. It imports
`schema.sql` + `seed.sql` for you, checks readiness (PHP version, `pdo_mysql`, DB
connection), and can drop & reinstall if the database already exists. **Delete
`install.php` afterwards** — it can wipe the database.

## Upgrading an existing database

If you already imported an older schema and just need the new AI-account fields (type list,
credentials, expiry, password reminder) **without losing data**, run the migration instead of
reinstalling:

```sh
"$MYSQL" -u root -h 127.0.0.1 --port=3307 < migrate_ai_account_details.sql
"$MYSQL" -u root -h 127.0.0.1 --port=3307 < migrate_groups_and_reports.sql
```

`migrate_ai_account_details.sql` adds the `ai_providers` table and the new `ai_accounts` columns;
`migrate_groups_and_reports.sql` adds the `user_groups` table, `users.group_id`, and the booking
`purpose` / report columns. Both are idempotent (safe to re-run; the one non-idempotent step is each
foreign key — a "Duplicate key name" error on re-run is harmless).

## Or import from the CLI

Import it once:

```sh
# WAMP MariaDB CLI (note: MariaDB is on port 3307, MySQL 9 uses 3306)
MYSQL="D:/wamp64/bin/mariadb/mariadb11.5.2/bin/mysql.exe"

# 1) schema (creates the rvc_apts database + tables)
"$MYSQL" -u root -h 127.0.0.1 --port=3307 < schema.sql

# 2) demo data (1 admin, 8 students, 4 AI accounts, 5 bookings)
"$MYSQL" -u root -h 127.0.0.1 --port=3307 rvc_apts < seed.sql
```

Or paste both files into phpMyAdmin (run `schema.sql` first, then `seed.sql`).

## Seeded logins

Every seeded account uses the password **`Passw0rd!`**.

| Role    | Email               | Notes                                            |
|---------|---------------------|--------------------------------------------------|
| Admin   | `admin@rvc.ac.th`   | Full admin dashboard                             |
| Student | `somchai@rvc.ac.th` | Primary demo student — has the 5 seeded bookings |

Other seeded students (`somying`, `wichai`, `amara`, `thanapol`, `napa`, `piya`, `kanok` @rvc.ac.th)
cover the `pending` / `approved` / `suspended` states for testing the admin member-management screens.

Booking dates in `seed.sql` are written relative to the import day (via `CURDATE()` +/- intervals), so
the "upcoming" booking stays in the future no matter when you import.

Connection settings live in `../config.php` (`DB_PORT` defaults to `3307` for WAMP MariaDB).
