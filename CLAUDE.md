# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

This is a single-file frontend prototype, not a buildable application. There is no `package.json`,
no build/lint/test tooling, and no backend — everything lives in two files:

- `AI Pro Booking System.dc.html` — the entire app (markup, styles, and component logic) in one
  `.dc.html` document.
- `support.js` — a **generated, vendored runtime**. Its header says: `GENERATED from dc-runtime/src/*.ts —
  do not edit. Rebuild with 'cd dc-runtime && bun run build'`. The `dc-runtime` source is *not* part of
  this repo, so `support.js` cannot actually be rebuilt here — treat it as read-only, third-party code.

There is no server-side code. This directory just happens to sit under a WAMP `www` root; the app is
opened directly (double-click / `file://`, or serve statically and browse to
`AI Pro Booking System.dc.html`). There is no `.htaccess` or index/router — the filename (including the
literal space) is the URL path.

## Git workflow

- Always `git pull` before starting any work in this repo.
- Always commit and push when finished making changes (remote: `origin` →
  `https://github.com/kinoppol/rvc.apts.git`).

## Running / previewing

There is no dev server or CLI command. To see the app, open
`AI Pro Booking System.dc.html` in a browser (directly, or via any static file server / WAMP vhost
pointed at this folder). All dependencies (React, ReactDOM, Babel standalone, Bootstrap 5, Bootstrap
Icons, Chart.js) are pulled from public CDNs at runtime — an internet connection is required.

There are no automated tests, build step, or linter configured for this repo.

## How the app boots (support.js)

`support.js` is a small runtime for the `.dc.html` ("declarative component") document format:

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

## Editing the app itself

All actual app behavior — state shape, page routing, event handlers, mock data — lives in the single
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
