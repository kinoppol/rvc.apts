# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

This repo now holds **two implementations of the same "AI Pro Time-Sharing" booking system** for
วิทยาลัย RVC (a Thai college), where students reserve time slots on a shared pool of AI Pro accounts
(Claude Pro / ChatGPT Plus):

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

The prototype and the PHP app coexist at the repo root with no URL collision (different filenames). The
sections further down about the `.dc.html` format and `support.js` runtime describe the **prototype
only** — they do not apply to the PHP app.

## Git workflow

- Always `git pull` before starting any work in this repo.
- Always commit and push when finished making changes (remote: `origin` →
  `https://github.com/kinoppol/rvc.apts.git`).

## The PHP application

A framework-less PHP 8 + MariaDB rebuild lives alongside the prototype. **No Composer, no build step, no
JSON/AJAX API** — every mutation is a plain `<form method="post">` handled by the same PHP file that
renders the page, following Post/Redirect/Get with session flash messages rendered as the prototype's
toast UI.

### Layout

- `config.php` — DB constants. **Important: WAMP's MariaDB listens on port `3307`** (MySQL 9 uses 3306);
  this app targets MariaDB, so `DB_PORT='3307'`. Default creds are WAMP dev defaults (`root` / no pass).
- `bootstrap.php` — every page's entry point: `session_start()`, requires config + all `includes/*.php`,
  and defines the global helpers `current_user()`, `require_login()`, `require_role()`, `flash_set()` /
  `flash_get()`, `url()` (see APP_BASE note below), and `e()` (htmlspecialchars).
- `index.php`, `login.php`, `register.php`, `logout.php` — auth entry / role redirect.
- `student/{dashboard,booking,my-bookings,profile}.php`, `admin/{dashboard,members,slots,ai-accounts,reports}.php`.
- `includes/` — domain classes (all `static`-method, PDO-backed): `Database` (PDO singleton),
  `Auth`, `Booking`, `Member`, `AiAccount`, `SlotSettings`, `Report`, `Csrf`; plus the shared view
  partials `header.php` / `footer.php` (authenticated shell) and `guest-header.php` / `guest-footer.php`
  (login/register shell).
- `assets/app.css` (ported verbatim from the prototype's `<style>`), `assets/app.js` (sidebar collapse,
  theme toggle, booking modal population, AI-account edit modal, `initUsageChart` for Chart.js).
- `database/schema.sql`, `database/seed.sql`.

### Architecture rules to respect when editing

- **Business logic lives in `includes/*.php` classes, never in the page files.** Page files only:
  guard (`require_role`), handle their own POST (call a domain method → `flash_set` → redirect), query
  what they render, and emit HTML. Add new behavior as a method on the relevant class.
- **Every page under `student/` calls `require_role('student')`; every page under `admin/` calls
  `require_role('admin')`** as its first act — auth is enforced server-side per file, never by hiding UI.
- **CSRF: every POST form must include `<?= Csrf::field() ?>`** (emits a hidden field named `csrf`) and
  every POST handler must call `Csrf::check()` before mutating. Field name is `csrf`, not `csrf_token`.
- **Booking `status` only stores `'upcoming'` / `'cancelled'`.** `'completed'` and `'now'` (in-progress)
  are *derived at read time* by `Booking::displayStatus()` comparing `start/end_datetime` to `NOW()` — no
  cron flips statuses. Don't add a stored "completed" state.
- **Booking a slot auto-assigns an AI account** via `SELECT ... FOR UPDATE` inside a transaction in
  `Booking::create()` (picks the lowest-id `active` account not already booked for that date+slot) to
  avoid a race on the last free account. Weekly quota is enforced there too.
- **Portable URLs:** never hardcode paths. `url('student/booking.php')` prefixes the computed `APP_BASE`
  (derived in bootstrap.php from `DOCUMENT_ROOT` vs `__DIR__`), so the app works under any WAMP vhost
  subdirectory. Sibling pages still link via `url()`, not relative paths.
- Keep new user-facing strings in **Thai**, matching existing terminology (จองคิว = book, ระงับสิทธิ์ =
  suspend, อนุมัติ = approve, บำรุงรักษา = maintenance).
- Slot count/labels/times are computed in PHP from `slot_settings` (`SlotSettings::slotLabel/slotStart/
  slotEnd`) — admin-editable, not a fixed table.

### Running / setup (PHP app)

1. Start WAMP (or at least its MariaDB service). If you need MariaDB without elevation, its daemon is
   `D:/wamp64/bin/mariadb/mariadb11.5.2/bin/mariadbd.exe --defaults-file=<...>/my.ini`; the CLI client is
   `mysql.exe` in the same `bin/`, connect with `-u root -h 127.0.0.1 --port=3307`.
2. Import once: `mysql ... < database/schema.sql` then `mysql ... rvc_apts < database/seed.sql`.
3. Browse to the app under the WAMP docroot (e.g. `http://localhost/rvc.apts/login.php`).
4. **Seeded login:** every seeded account's password is `Passw0rd!`. Admin = `admin@rvc.ac.th`; primary
   demo student = `somchai@rvc.ac.th` (has the 5 seeded bookings). Seed dates are relative to import day
   via `CURDATE()` expressions, so "upcoming" stays upcoming.

PHP versions are under `D:/wamp64/bin/php/php*/php.exe` — use `php -l <file>` to lint. There is still no
automated test suite; verify by importing the seed and walking the golden paths (register → admin
approves → student books → cancels; admin edits slots, manages AI pool, exports the CSV report).

## Running / previewing the prototype

There is no dev server or CLI command for the **prototype**. To see it, open
`AI Pro Booking System.dc.html` in a browser (directly, or via any static file server / WAMP vhost
pointed at this folder). All dependencies (React, ReactDOM, Babel standalone, Bootstrap 5, Bootstrap
Icons, Chart.js) are pulled from public CDNs at runtime — an internet connection is required.

There is no build step or linter configured for either implementation.

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
   (conditional render) and `<sc-for>` (list render) instead of JSX control flow. `hint-placeholder-val`
   / `hint-placeholder-count` attributes are streaming-preview hints and can be ignored when reasoning
   about behavior.
5. Because it re-fetches and re-parses the current document (`fetch(location.href)`), the file must be
   served/opened as `.dc.html` for hot template updates to apply — editing the template and reloading is
   the whole edit loop.

## Editing the prototype itself

All actual prototype behavior — state shape, page routing, event handlers, mock data — lives in the single
`class Component extends DCLogic { ... }` block at the bottom of
`AI Pro Booking System.dc.html` (the `<script type="text/x-dc" data-dc-script>` section). Key things to
know before editing it:

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
- The app is bilingual-flavored but UI copy is Thai throughout (this is for วิทยาลัย RVC — a Thai
  college); keep new user-facing strings in Thai and consistent with existing tone/terminology (e.g.
  "จองคิว" = book a slot/queue, "ระงับสิทธิ์" = suspend, "อนุมัติ" = approve).
- Theme handling (`getBsTheme`, `setTheme`) drives Bootstrap's `data-bs-theme` attribute and also
  destroys/reinits the Chart.js instance (`maybeInitChart`), since Chart.js doesn't auto-restyle for
  dark mode.
