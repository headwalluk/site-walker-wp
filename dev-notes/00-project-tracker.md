# Project Tracker

**Version:** 0.2.0
**Last Updated:** 2026-05-18
**Current Phase:** M2 — Abuse-resistance pass (next)
**Overall Progress:** ~40% of "v1 shippable"

---

## Overview

WordPress plugin that injects a floating chat widget into the front-end and talks directly from the visitor's browser to a [Site Walker](https://site-walker.net) API instance. The widget uses `localStorage` and a cached reachability probe to keep network traffic to a minimum (avoid hammering the API and avoid round-trips on every page load).

Settings are managed via a tabbed WP admin page (General / Appearance). All visual / positional configuration is colour-picker- and form-driven; no shortcodes or theme code required.

---

## Active TODO Items

### In progress
- _(nothing in flight — 0.2.0 just shipped)_

### Next
- [ ] Add an admin-side "test connection" button that pings the configured API URL from the browser (not server-side — same origin model as the widget).
- [ ] Conversation reset affordance (clear `localStorage`, mint fresh session).

### Done (this release)
- [x] **Unblock browser end-to-end test.** Resolved upstream: API ships CORS middleware and a proper pre-flight endpoint (`GET /sessions/can-start`). Reverse proxy not needed.
- [x] Verify the three-state load flow end-to-end in a real browser: probe-needed → cached-available → session-in-progress. Confirmed against the live API on 2026-05-18.
- [x] Switch probe from `GET /` to `GET /sessions/can-start`; treat `200 { "ok": true }` as the only success signal.

### Later (deferred to dedicated milestones)
- [ ] API key support (header injection on every request; admin field; key revocation flow).
- [ ] Abuse / rate-limit hardening: per-IP throttling on `POST /sessions`, simple bot detection (timing + honeypot), token-spend ceiling per visitor.
- [ ] Conversation reset affordance (clear `localStorage`, mint fresh session).
- [ ] Theme override hook for `templates/` once we have any front-end PHP templates worth exposing.

---

## Milestones

### M0 — Scaffold & happy-path widget ✅
- Plugin loads without errors, options register with defaults.
- Settings page renders both tabs, saves, sanitises.
- Front-end container, JS, and CSS enqueue and render correctly.
- API config is shipped to JS as typed JSON (not stringified via `wp_localize_script`).

### M1 — Verified browser flow ✅ (0.2.0)
- Upstream API now ships CORS middleware and the `GET /sessions/can-start` pre-flight endpoint.
- Manual test pass against the live API: probe success path confirmed (`{"state":"available"}` cached to `localStorage`); session mint, send-turn and rehydrate paths exercised in the browser.

### M2 — Abuse-resistance pass
- Rate limiting (client-side cool-downs + server-side allowance).
- API key.
- Token-spend ceiling.

### M3 — Polish
- Mobile layout review.
- A11y review (focus trap when panel open, ESC to close, screen reader checks).
- Theme override docs.

---

## Technical Debt

- **No `phpcs.xml`** in the plugin root yet. Workflow docs reference it but it hasn't been added; phpcs is installed globally on the host and ready to use once the config lands.
- **No tests.** Manual verification only so far. Worth at least smoke tests around `Settings::sanitize_*` once the surface stabilises.
- **`functions-private.php`** is a flat function bag; if it grows beyond a handful of helpers, promote to a class.

---

## Notes for Development

- **Working directory ownership:** plugin dir is `www-devx:www-data`. Git needs the path on the user's `safe.directory` allowlist before any git operation will work.
- **Page cache on `devx.headwall.tech`:** front-end is page-cached by default; the user toggles cache off when iterating.
- **WP-CLI:** runs globally from any directory — no `cd` to the WordPress root required.
- **API origin allowlist:** the Site Walker website must have `https://devx.headwall.tech` added via `sw website origins add` before any session-mint will succeed.
- **Local API base URL:** `http://localhost:47830` (dev), `https://api.site-walker.net` (prod default).
