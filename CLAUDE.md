# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

This repo holds **two implementations of the same "AI Pro Time-Sharing" booking system** for
วิทยาลัย RVC (a Thai college), where students/teachers reserve time slots on a shared pool of AI Pro
accounts (Claude Pro / ChatGPT Plus):

1. **The original single-file frontend prototype** (reference/design source, unchanged):
   - `AI Pro Booking System.dc.html` — the entire mock app (markup, styles, component logic) in one
     `.dc.html` document, with all data hardcoded as in-memory JS state (no persistence, no auth).
   - `support.js` — a **generated, vendored runtime**. Its header says: `GENERATED from
     dc-runtime/src/*.ts — do not edit. Rebuild with 'cd dc-runtime && bun run build'`. The `dc-runtime`
     source is *not* part of this repo, so `support.js` cannot be rebuilt here — treat it as read-only,
     third-party code.

2. **The real PHP 8 + MariaDB rebuild** (the production app — see "## The PHP application" below) — a
   server-rendered, persistent, multi-user port of the prototype. The prototype's markup/CSS/Thai copy
   were reused almost verbatim; every mock button that was a stub is now a working, DB-backed feature.
   The PHP app has since grown well past the prototype's feature set.

The prototype and the PHP app coexist at the repo root with no URL collision (different filenames). The
sections about the `.dc.html` format and `support.js` runtime describe the **prototype only** — they do
not apply to the PHP app.

## Git workflow & deployment

- Always `git pull` before starting any work in this repo.
- Always commit and push when finished making changes (remote: `origin` →
  `https://github.com/kinoppol/rvc.apts.git`).
- **Pushing to `main` deploys to production.** `.github/workflows/main.yml` SSHes into the server,
  runs `git fetch && git reset --hard origin/main` in `/var/www/rvc.apts`, then `php migrate.php`.
  There is no staging environment and no review gate — a push is a release. Any new
  `database/migrate_*.sql` file must be registered in `Migration::ORDER` (see below) or it will
  silently not run on the server.
- Because deploy is `git reset --hard`, **anything untracked on the server survives but anything
  tracked is overwritten** — never hand-edit files on the server, and never commit environment-specific
  values (that's what the git-ignored `config.local.php` is for).

## Environments

The app runs on **Linux in production** and is developed on **Windows/XAMPP** locally. These differ in
ways that bite:

| | Local dev | Production |
|---|---|---|
| Stack | XAMPP at `C:\xampp` (`C:/xampp/php/php.exe`, `C:/xampp/mysql/bin/mysql.exe`) | Linux, Apache, `/var/www/rvc.apts` |
| MariaDB port | **3306** (verified) | set via `config.local.php` on the server |
| URL | `http://localhost/rvc.apts/…` | `https://apts.rvc.ac.th/web/…` |
| `APP_BASE` | `''` — auto-detected | `/web`, via the symlink `/var/www/web` → `/var/www/rvc.apts` |

- **Filesystem case sensitivity** is the classic Windows→Linux break: a `require`/`url()`/`<img src>`
  whose case doesn't match the real filename works locally and 404s in production. Match case exactly.
- `config.php`'s committed defaults are the dev baseline (port `3307`, a leftover from an earlier WAMP
  setup); the real values on both machines come from the git-ignored `config.local.php`. If a fresh
  clone can't connect locally, that missing file is why — not `config.php`.
- The production `/web` symlink is exactly what the elaborate `APP_BASE` derivation in `bootstrap.php`
  exists to handle. Verify URL-building changes against a subdirectory base, not just `localhost/`.

## The PHP application

A framework-less PHP 8 + MariaDB rebuild lives alongside the prototype. **No Composer, no build step, no
JSON/AJAX API** — every mutation is a plain `<form method="post">` handled by the same PHP file that
renders the page, following Post/Redirect/Get with session flash messages rendered as the prototype's
toast UI.

### Layout

- `config.php` — DB constants, overridden by the git-ignored `config.local.php` (an array supplying
  `host/port/name/user/pass/app_base`). **Both environments use `config.local.php`** — the committed
  defaults in `config.php` are a stale baseline, see "## Environments" above.
- `bootstrap.php` — every page's entry point: `date_default_timezone_set('Asia/Bangkok')`,
  `session_start()`, requires config + all `includes/*.php` domain classes, computes `APP_BASE`, and
  defines the global helpers `current_user()`, `require_login()`, `require_role()`, `flash_set()` /
  `flash_get()`, `url()`, `asset()`, `is_impersonating()`, and `e()` (htmlspecialchars).
- `index.php`, `login.php`, `register.php`, `logout.php` — auth entry / role redirect.
- `install.php` — standalone web installer (imports `schema.sql` + `seed.sql`, with a guarded
  drop-and-reinstall; can also write `config.local.php`). Deliberately does **not** use `bootstrap.php`
  (which selects the not-yet-created DB); it reads only `config.php` and runs each `.sql` file on its
  own fresh PDO connection. Meant to be deleted after setup.
- `migrate.php` — CLI-only migration runner (`php migrate.php`; hard-exits 403 under a web SAPI).
  This is what the deploy workflow calls.
- `migration.php` — standalone *web* migration runner, same standalone-of-bootstrap rationale as
  `install.php`. `admin/migrations.php` is the in-app equivalent for logged-in admins.
- `student/{dashboard,booking,my-bookings,profile}.php` — used by **both** students and teachers.
- `admin/{dashboard,members,groups,slots,ai-accounts,majors,bookings,calendar,reports,migrations,profile}.php`.
  `calendar.php` is the read-only admin twin of the student booking grid: `Booking::adminWeekGrid()`
  builds a week of all pools (not group-scoped, nothing bookable) and each cell embeds its bookings as
  JSON in `data-detail`, which `initAdminCalendar` in `assets/app.js` renders into a modal — no AJAX.
- `includes/` — domain classes (all `static`-method, PDO-backed): `Database` (PDO singleton), `Auth`,
  `Booking`, `Member`, `UserGroup`, `AiProvider`, `AiAccount`, `SlotSettings`, `Major`, `Subject`,
  `Report`, `Notification`, `Migration`, `Csrf`; plus the shared view partials `header.php` /
  `footer.php` (authenticated shell) and `guest-header.php` / `guest-footer.php` (login/register shell).
- `uploads/` — student-uploaded usage-report and issue files (image/PDF). Git-ignored except `.gitkeep`.
- `assets/app.css` (ported from the prototype's `<style>`), `assets/app.js` (sidebar collapse, theme
  toggle, booking modal population, AI-account edit modal, `generateSecurePassword`, `initUsageChart`).
- `database/schema.sql`, `database/seed.sql`, and a series of idempotent `migrate_*.sql` ALTER scripts;
  fresh installs get everything from `schema.sql`. See `database/README.md`.

### Architecture rules to respect when editing

- **Business logic lives in `includes/*.php` classes, never in the page files.** Page files only:
  guard (`require_role`), handle their own POST (call a domain method → `flash_set` → redirect), query
  what they render, and emit HTML. Add new behavior as a method on the relevant class.
- **Roles are `student` / `teacher` / `admin`.** Pages under `student/` call
  `require_role(['student', 'teacher'])` — teachers share the entire student-side UI; pages under
  `admin/` call `require_role('admin')`. This is the first statement in every page file — auth is
  enforced server-side per file, never by hiding UI. `require_role` accepts a string or an array.
- **CSRF: every POST form must include `<?= Csrf::field() ?>`** (emits a hidden field named `csrf`) and
  every POST handler must call `Csrf::check()` before mutating. Field name is `csrf`, not `csrf_token`.
- **Derive state at read time; never add cron jobs or stored status flags.** This is the single most
  important pattern in the codebase and it recurs everywhere:
  - Booking `status` only stores `'upcoming'` / `'cancelled'`. `'completed'` and `'now'` are derived by
    `Booking::displayStatus()` comparing `start/end_datetime` to `NOW()`.
  - AI-account expiry: `expires_at <= NOW()` means disabled. `AiAccount::listWithUsage()` shows a
    "ปิดใช้งาน (หมดอายุ)" badge and every availability query filters
    `(expires_at IS NULL OR expires_at > NOW())`. Days-remaining and password-reminder due dates are
    derived the same way.
  - Report-overdue suspension: `Booking::isRestricted($userId)` (any completed booking unreported past
    `Booking::REPORT_DEADLINE_DAYS` = 7) blocks new bookings — no stored "suspended" flag.
- **Booking is per-pool, gated by group access, and capacity-limited.** A group's members may only book
  the AI accounts listed in `group_ai_accounts` (admin-managed on `admin/groups.php`); no group / no
  rows = cannot book (`Booking::allowedAccountsFor` returns `[]`). Students pick one or more **specific**
  pools per slot via checkboxes (capped client-side at the group's `max_concurrent`).
  `Booking::create($userId, $date, $slot, array $accountIds, $purpose)` validates every id is granted +
  active + non-expired, re-checks `max_concurrent` and the weekly quota server-side, and books them
  **atomically** in one `FOR UPDATE` transaction — any one pool being full rolls back the whole batch.
  No auto-assign. `Booking::getWeekGrid` returns, per slot, each pool's status
  (`available`/`busy`/`mine`/`now`/`off`); the booking page collapses this into one bordered cell per
  slot, and clicking a bookable cell opens the confirm modal where the per-pool checkboxes live.
- **Each pool has a `capacity` (default 1) — several users may share one account in a slot.** Occupancy
  is enforced in the PHP transaction (`SELECT ... occupied ... FOR UPDATE`, reject when
  `occupied >= capacity`), *not* by a plain unique key. The DB still carries a unique key
  `(ai_account_id, booking_date, slot_uniq_guard)` where `slot_uniq_guard` is a **virtual column** that
  resolves to `NULL` for cancelled or checked-out rows — MariaDB skips NULLs in unique keys, which is
  what lets a released slot be re-booked. Don't "restore" the old `uniq_account_slot` index.
- **Check-in / check-out.** `Booking::checkIn()` opens 15 minutes before the slot; `checkOut()` releases
  the pool early (sets `checked_out_at`, which excludes the row from every occupancy and
  `max_concurrent` count). `Booking::earlyAccessForUser()` surfaces a booking whose *previous* slot is
  effectively empty (previous holder never checked in, or already checked out) so the student can start
  ahead of time.
- **AI-account type is an FK to `ai_providers`** (admin-managed via the "จัดการประเภท" modal on
  `ai-accounts.php`). `ai_accounts.provider` is a denormalized copy of the type name kept in sync by
  `AiProvider::rename()`; reads prefer `COALESCE(p.name, a.provider)`. The shared login password
  (`account_password`) is stored readable on purpose (admins hand it out) — never hash it. When
  `pwdWarn` is true, `admin/ai-accounts.php` offers a modal that generates a random 12-char password
  client-side (`generateSecurePassword()` in `assets/app.js` — one guaranteed char per class, avoiding
  ambiguous glyphs like `0/O`, `1/l/I`); saving posts to `AiAccount::updatePassword()`, which resets
  `password_updated_at` (the reminder clock) without touching any other field.
- **Portable URLs:** never hardcode paths. `url('student/booking.php')` prefixes the computed
  `APP_BASE`. That computation is deliberately intricate — production serves the app through a symlink
  (`/var/www/web` → `/var/www/rvc.apts`), which `SCRIPT_FILENAME` preserves but `realpath()`/`__DIR__`
  resolve away, so bootstrap derives the URL base from the script's URL path minus its depth inside the
  project. `APP_BASE_OVERRIDE` (from `config.local.php`) short-circuits the whole thing. Read the
  comments before touching it. Use `asset('assets/app.css')` for CSS/JS — it appends `?v=filemtime` for
  cache busting.
- Keep new user-facing strings in **Thai**, matching existing terminology (จองคิว = book, ระงับสิทธิ์ =
  suspend, อนุมัติ = approve, บำรุงรักษา = maintenance). Error strings returned from domain methods are
  Thai too and are shown verbatim as toasts.
- Slot count/labels/times are computed in PHP from `slot_settings` (`SlotSettings::slotLabel/slotStart/
  slotEnd`, incl. the admin-editable `day_start_time`) — not a fixed table.
- **Slots use a 30-hour "business-day" clock.** A slot may run past midnight; `SlotSettings::slotStart/
  slotEnd` emit `24:00`–`30:00` (e.g. `25:00` = 1 AM the next calendar day) rather than wrapping, and
  `update()` caps `day_start + slot_hours×slots_per_day` at 30:00. `bookings.booking_date` stays the
  start ("business") day while `start/end_datetime` hold the true absolute timestamps (passing an
  "HH:MM" ≥ 24:00 to `DateTimeImmutable::setTime()` rolls over correctly). For display, `Booking`
  derives the date from `booking_date` and the time via `thirtyHour()` so a post-midnight slot shows on
  its start day, not a confusing next-day date.
- **Per-user booking limits go through `Booking::limitsFor($userId)`**, not `SlotSettings::get()`
  directly: it returns the global settings with the user's group (`user_groups`, admin-managed on
  `admin/groups.php`) overriding `weekly_quota` / `max_advance_days` when those group columns aren't
  NULL, plus the group's `max_concurrent` (pools bookable in one slot; default 1). Note `weekly_quota`
  counts **bookings (pool-slots), not hours** — `quotaUsed()` is a `COUNT(*)`. Any new
  quota/advance/concurrency logic must resolve through `limitsFor`, not the raw global row.
- **Every booking must carry a `purpose`**, and after a slot ends the student must file a usage report
  (free text and/or an image/PDF upload, plus optional token-usage percentages) within 7 days.
  `Member::assignGroup/resetPassword` and `Booking::waiveOverdueForUser` (admin "ปลดระงับ") are the
  admin-side escape hatches. Uploaded files are validated (extension + finfo mime, ≤5 MB) and moved
  into `uploads/` by `Booking::submitReport()` / `Booking::reportIssue()` (issues allow multiple files).
- **Registration collects a major (students) or subjects (teachers)** via `Major` / `Subject`, managed
  on `admin/majors.php`. Teachers can add a new subject inline from the registration autocomplete
  (`Subject::addAndGetId`).
- Admins can **impersonate** a member from `admin/members.php` (`$_SESSION['impersonating']` +
  `admin_id`; `is_impersonating()` drives the banner in `header.php`, and `logout.php` restores the
  admin session rather than logging out).

### Migrations

`includes/Migration.php` is a tiny hand-rolled migration runner tracking applied files in a
`_migrations` table.

- **Run order is an explicit `Migration::ORDER` array, not filename sort.** Adding a new
  `database/migrate_*.sql` file means appending its name to that list — a file absent from `ORDER` is
  treated as a manual/emergency script and is never auto-applied (this is intentional for
  `migrate_production_catchup.sql`).
- Migrations must be **idempotent** (`ADD COLUMN IF NOT EXISTS`, `DROP INDEX IF EXISTS`, etc.) — they
  may be re-run, and `markApplied()` lets an admin tick one off without executing it when the schema
  already matches.
- `parseStatements()` splits on `;` and strips `--` comments and `USE <db>;` lines (the DB name differs
  across environments), so avoid semicolons inside string literals in migration SQL.
- Three entry points, same engine: `php migrate.php` (CLI, used by deploy), `migration.php` (standalone
  web), `admin/migrations.php` (in-app, shows status + run-one / run-all / mark-applied).
- `schema.sql` must stay the union of everything, so a fresh install needs no migrations.

### Running / setup (PHP app)

1. Start XAMPP's Apache + MySQL (MariaDB) services. CLI client:
   `C:/xampp/mysql/bin/mysql.exe -u root -h 127.0.0.1 --port=3306`.
2. Ensure `config.local.php` exists (git-ignored; `install.php` can write it).
3. Import once — either browse to `install.php` and click ติดตั้ง, or from the CLI:
   `mysql ... < database/schema.sql` then `mysql ... rvc_apts < database/seed.sql`.
4. Browse to the app under the XAMPP docroot (e.g. `http://localhost/rvc.apts/login.php`).
5. **Seeded login:** every seeded account's password is `Passw0rd!`. Admin = `admin@rvc.ac.th`; primary
   demo student = `somchai@rvc.ac.th` (has the 5 seeded bookings). Seed dates are relative to import day
   via `CURDATE()` expressions, so "upcoming" stays upcoming.

Lint with `C:/xampp/php/php.exe -l <file>`. **There is no
automated test suite and no linter configured for either implementation.** Verify by importing the seed
and walking the golden paths: register → admin approves → assigns group → student books → checks in →
checks out → files report → cancels; admin edits slots, manages the AI pool, runs migrations, exports
the CSV report.

## Running / previewing the prototype

There is no dev server or CLI command for the **prototype**. To see it, open
`AI Pro Booking System.dc.html` in a browser (directly, or via any static file server / vhost
pointed at this folder — e.g. XAMPP's `htdocs`). All dependencies (React, ReactDOM, Babel standalone, Bootstrap 5, Bootstrap
Icons, Chart.js) are pulled from public CDNs at runtime — an internet connection is required.

## How the prototype boots (support.js)

*(Prototype only — the PHP app does not use any of this.)* `support.js` is a small runtime for the
`.dc.html` ("declarative component") document format:

1. On load it parses the `<x-dc>` element's inner HTML as the component template and reads the sibling
   `<script type="text/x-dc" data-dc-script>` block as the component's JS logic.
2. It dynamically injects `react@18`, `react-dom@18`, and `@babel/standalone` from `unpkg.com` (see
   `REACT_URL`, `REACT_DOM_URL`, `BABEL_URL` in `support.js`), then Babel-transpiles the inline script.
3. The `<x-dc>` element is replaced with a `#dc-root` div and the parsed template is rendered into it via
   React, driven by the `Component` class defined in the inline script (a small class-based state
   container — `this.state`, `this.setState()`, lifecycle methods like `componentDidMount` /
   `componentDidUpdate`).
4. Template expressions use `{{ expr }}` interpolation and custom elements `<sc-if value="{{ cond }}">`
   (conditional render) and `<sc-for>` (list render) instead of JSX control flow.
   `hint-placeholder-val` / `hint-placeholder-count` attributes are streaming-preview hints and can be
   ignored when reasoning about behavior.
5. Because it re-fetches and re-parses the current document (`fetch(location.href)`), the file must be
   served/opened as `.dc.html` for hot template updates to apply — editing the template and reloading is
   the whole edit loop.

## Editing the prototype itself

All actual prototype behavior — state shape, page routing, event handlers, mock data — lives in the single
`class Component extends DCLogic { ... }` block at the bottom of
`AI Pro Booking System.dc.html` (the `<script type="text/x-dc" data-dc-script>` section):

- **No backend, no persistence.** All data (`membersData`, `myBookings`, `aiAccounts`, etc.) is hardcoded
  mock data in `state`; actions like approve/reject/suspend/cancel just mutate in-memory state via
  `setState` and show a toast — nothing survives a reload.
- **Routing is a state field**, not URL-based: `state.page` (e.g. `'login'`, `'student-dashboard'`,
  `'booking'`, `'admin-dashboard'`, `'member-management'`) selects which section renders via `sc-if`
  blocks in the template. `state.role` (`'student'` | `'admin'` | `null`) gates auth vs. app and which
  sidebar links show.
- **`renderVals()`** is the single method that computes and returns every value the template can
  reference via `{{ }}` — derived data, CSS class strings, and event handler closures are all built here
  each render. When adding a new template binding, add it to the object returned by `renderVals()`.
- Dates are hardcoded relative to a fixed "today" (`new Date(2026,6,3)`, i.e. 3 Jul 2026) in
  `generateWeekSlots()` / `getWeekLabel()`, not the real current date — this is intentional for a stable
  demo, not a bug.
- UI copy is Thai throughout; keep new user-facing strings in Thai and consistent with existing tone.
- Theme handling (`getBsTheme`, `setTheme`) drives Bootstrap's `data-bs-theme` attribute and also
  destroys/reinits the Chart.js instance (`maybeInitChart`), since Chart.js doesn't auto-restyle for
  dark mode.
