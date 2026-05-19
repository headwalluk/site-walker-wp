# Changelog

All notable changes to this plugin will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Pending
- Admin-side "test connection" button that pings the configured API URL from the browser.
- Conversation reset affordance (clear `localStorage`, mint fresh session).

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
