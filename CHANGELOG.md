# Changelog

All notable changes to this plugin will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Pending
- Admin-side "test connection" button that pings the configured API URL from the browser.
- Conversation reset affordance (clear `localStorage`, mint fresh session).

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
