# Changelog

All notable changes to this plugin will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Pending
- Conversation reset affordance (clear `localStorage`, mint fresh session).

## [0.5.0] - unreleased

M7 — Admin-area extension (new wp-admin tabs over `/admin/chatbots/*`) and M8 — Admin mode (per-admin session minting via the upstream M21 `POST /admin/chatbots/{slug}/sessions` route). Same release because both ride on the same server-side proxy (`Admin_API_Client`) and the same account-admin key already stored on the WP host. The account admin key is never exposed to the browser in either flow.

### Added (M8 — Admin mode)
- New WP REST route `POST /wp-json/site-walker/v1/admin-session` — manage_options + nonce gated, forwards to upstream `POST /admin/chatbots/{slug}/sessions` via the existing `Admin_API_Client`. Returns the upstream `{ session_token, welcome_message, is_admin_mode: true }` envelope unchanged.
- Widget container carries `data-is-logged-in="1"` when rendered for a logged-in `manage_options` user; `window.siteWalkerWP.adminSession` (admin-session URL + nonce) is localised only in that case.
- Widget `boot()` and `ensureSession()` branch on admin mode: skip the probe (upstream skips every gate the probe tests for), mint via the WP backend on first launcher click, cache the admin token under a separate `localStorage` key (`site-walker-wp:<host>:admin-session-token`) so admin and customer sessions on the same browser don't clobber each other.
- The upstream's `**Admin mode**\n\n` welcome-message prefix renders as bold via our existing assistant-message markdown formatter — visible in-message cue, no new widget chrome.

### Added (M7 — Admin-area extension)

### Added
- `Admin_API_Client` — thin PHP wrapper over `wp_remote_request` for the upstream `/admin/chatbots/*` surface. Returns a uniform `[ok, status, data | error, detail]` envelope; carries the bearer admin key on every request; never logs it.
- `Admin_REST` — WP REST routes under `/wp-json/site-walker/v1/admin/*` for the four new tabs. All gated on `manage_options` + WP REST cookie nonce. PATCH bodies whitelist their allowed fields so an admin can't piggy-back un-exposed fields like `model_slug` through the proxy.
- **Connection tab.** API URL, account admin key (password input on first save, masked-to-last-4 thereafter with Clear / Replace buttons), auto-discovered chatbot slug (single chatbot → auto-save; multi → picker dropdown; zero → friendly message). Test-connection button.
- **Chatbot tab.** Welcome message, persona, daily / session budget caps (USD), soft-handoff threshold percent. Fetch-on-tab-open, PATCH on save. Empty-string textarea fields are translated to `null` on the way to the API so a clear-and-save actually clears the upstream field.
- **Geo tab.** Mode (`allowall` / `blocklist` / `allowlist`) as a radio group; countries as a freeform textarea (comma / whitespace / newline separated, normalised to uppercase ISO 3166-1 alpha-2). A chip-input picker is on the v2 list.
- **Usage tab.** Read-only spend display with a `since` selector (1h / 24h / 7d / 30d / all time). Renders any operator-actionable warnings (e.g. under-counting on NULL-priced models) inline.
- New design doc: `dev-notes/40-admin-area-extension.md` covers the medium-cut scope, architecture, storage model, auto-discover flow, error model, and what's intentionally deferred.

### Changed
- General tab renamed to **Widget** — the only Settings-API-driven knobs that remain there are the widget render options. The API URL field moved to the new Connection tab and is now REST-managed (not registered via the Settings API).
- Tab page restructured to host two tab families: Settings-API-driven (Widget, Appearance) wrapped in the shared `options.php` form, and REST-driven (Connection, Chatbot, Geo, Usage) each loading from its own partial under `admin-templates/tabs/`. The shared submit button now only shows when a Settings-API tab is active.
- Tab switching dispatches a `swwp:tab-activate` custom event so each REST-driven tab can refetch on display.

### Notes
- **What's deliberately out of scope** (each tracked under "Later" in the project tracker): origins management UI, BYO provider API key setting, model swap, system blocks CRUD, account-level provisioning. Each was excluded for a stated reason (credential handling, blast-radius, or "wants its own UX") rather than time pressure.

## [0.4.0] - unreleased

M6 — API v0.16 catch-up. The browser chat-path endpoints (`GET /sessions/can-start`, `POST /sessions`, `POST /chat`, `GET /messages`) are unchanged at the wire level upstream; what's new is the denial vocabulary the API introduced in M20 (daily + per-session USD caps) plus the chat-path geo-lockout. This release teaches the widget to handle them gracefully.

### Added
- Widget handles `402 budget_exhausted_daily` on probe (`GET /sessions/can-start`), mint (`POST /sessions`), and chat (`POST /chat`). New `budget-exhausted` probe state is cached with an explicit "blocked until next UTC midnight" timestamp, so the widget stays hidden for the rest of the UTC day without burning HTTP traffic, and re-probes automatically the next morning. Mid-session, the input is disabled and a polite system message replaces the launcher-click status.
- Widget handles `403 geo_blocked` on `POST /sessions`, `POST /chat`, and `GET /messages`. The cached token is dropped and the widget hides itself entirely (mint-retry would also 403, per the API contract).
- Widget handles `200 { session_terminated: true }` on `POST /chat` (per-session hard cap). The assistant's final reply still renders, then the input row locks. A per-host `terminated` flag is persisted in `localStorage` so reloads honour the terminated state too; clearing the token (e.g. via 401 recovery) resets it.
- `:disabled` styling on `.swwp-input` and `.swwp-send` so the locked state is visually obvious.

### Changed
- `probeApi()` returns a `{ state, until? }` object instead of a boolean, so the boot path can distinguish "budget-exhausted" from generic "unavailable" without losing the explicit reset time.

### Docs
- Terminology sweep: "website(s)" → "chatbot(s)" in `README.md` and the dev-notes planning docs, matching the upstream rename in API v0.16. The `sw website …` CLI examples are now `sw chatbot …`. No plugin code referenced the old term — only the docs did.
- New M6 milestone recorded in `dev-notes/00-project-tracker.md`, with the next admin-area extension queued behind it.

## [0.3.0] - 2026-05-19

Refactor pass to align the codebase with the project's house style, plus a small UX upgrade to assistant message rendering. No DB changes; option keys unchanged.

### Changed
- Text domain renamed from `site-walker-wp` to `site-walker`. The plugin slug stays `site-walker-wp`; only the i18n domain changed.
- `@package` tag renamed from `Site_Walker_WP` to `Site_Walker` across all PHP files.
- Global-scope identifiers now use the `STWLK_` / `stwlk_` prefix (`STWLK_PLUGIN_FILE`, `STWLK_PLUGIN_DIR`, `STWLK_PLUGIN_URL`, `STWLK_PLUGIN_VERSION`, `stwlk_plugin_run()`, `stwlk_get_site_walker()`, `$stwlk_plugin`).
- Main entry file (`site-walker-wp.php`) no longer declares a namespace.

### Added
- Assistant replies render light markdown: `**bold**`, inline `` `code` ``, and auto-linking of URLs that point to the current site's host. External URLs are deliberately left as plain text — the upstream model isn't trusted to emit safe outbound links.
- CSS for `a` / `strong` / `code` inside assistant message bubbles.

### Fixed
- `admin-templates/settings-page.php` was declaring `namespace Site_Walker_WP` while referencing constants from `Site_Walker`, so `SETTINGS_GROUP` and `ADMIN_PAGE_SLUG` lookups would have errored at runtime.
- Classes under `namespace Site_Walker` were referencing bare `PLUGIN_DIR` / `PLUGIN_URL` / `PLUGIN_VERSION` constants which (post-namespace) resolved to non-existent `Site_Walker\PLUGIN_*`. Switched to explicit `\STWLK_PLUGIN_*` global references.
- Plugin bootstrap is now actually invoked — 0.2.0 shipped `stwlk_plugin_run()` defined but never called.

### Docs
- New planning docs in `dev-notes/`: integrations architecture (`10-`), WooCommerce integration (`11-`), Independent Analytics integration (`12-`), system context blocks REST API (`20-`), and operator controls brainstorm (`30-`). Project tracker updated with M4 (integrations) and M5 (operator controls) milestones.

## [0.2.0] - 2026-05-18

End-to-end browser flow verified against a live Site Walker API instance.

### Changed
- Reachability probe now calls `GET /sessions/can-start` (the API's documented pre-flight endpoint) and treats only `200 { "ok": true }` as available. Previously hit `GET /`, which the API no longer exposes as a probe.

### Fixed
- Browser end-to-end flow (probe → mint → chat) now works against the upstream API now that the proper pre-flight endpoint is in use and CORS is honoured.

## [0.1.0] - 2026-05-17

Initial scaffold. Server-side wiring complete; end-to-end browser flow blocked on upstream CORS work in the Site Walker API.

### Added
- Plugin scaffold under the `Site_Walker_WP` namespace, with `Plugin`, `Settings`, `Admin_Hooks`, and `Public_Hooks` classes.
- Constants and default values for all options (API URL, position, offsets, colours, icon, header/placeholder text, probe TTL/cooldown).
- Tabbed admin settings page (General / Appearance) using the Settings API, with sanitisers per type (URL, hex colour, enum, clamped int).
- `wp-color-picker` integration for the three colour fields.
- Floating launcher + expanded chat panel injected into `wp_footer`, gated by `is_widget_renderable()`.
- CSS-variable-driven theming so colours and offsets reach the panel without inline `<style>` blocks per element.
- Built-in SVG icons: chat / question / sparkle.
- Front-end widget JS (`assets/public/widget.js`) with the three-state load flow:
  - Existing session → rehydrate via `GET /messages`.
  - Cached probe → render launcher without a network call.
  - No probe → `GET <apiUrl>/`, cache result, decide.
- `POST /sessions` mint on first launcher click, with "Connecting…" state.
- `POST /chat` send-turn flow with handling for `invalid_token`, `context_overflow`, `model_error`, `model_not_configured`, and `origin_not_allowed`.
- Per-API-host `localStorage` keys (`site-walker-wp:<host>:session-token` etc.) so multiple widgets don't collide.

### Known limitations
- No API key authentication yet.
- No abuse / rate-limit controls yet (botnet, DDoS, token-spend protection — tracked for a later milestone).
- The Site Walker API doesn't yet ship CORS middleware, so cross-origin browser calls fail at preflight; planned upstream fix or local reverse proxy will unblock end-to-end testing.
