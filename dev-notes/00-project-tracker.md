# Project Tracker

**Version:** 0.3.0
**Last Updated:** 2026-05-23
**Current Phase:** M7 — Admin-area extension (in progress)
**Overall Progress:** ~45% of "v1 shippable"

---

## Overview

WordPress plugin that injects a floating chat widget into the front-end and talks directly from the visitor's browser to a [Site Walker](https://site-walker.net) API instance. The widget uses `localStorage` and a cached reachability probe to keep network traffic to a minimum (avoid hammering the API and avoid round-trips on every page load).

Settings are managed via a tabbed WP admin page (General / Appearance). All visual / positional configuration is colour-picker- and form-driven; no shortcodes or theme code required.

---

## Active TODO Items

### In progress
- [ ] **M7 — Admin-area extension.** The wp-admin surface that hangs off `/admin/chatbots/*`: Connection tab (admin key + auto-discovered chatbot slug), Chatbot tab (welcome / persona / budgets), Geo tab (mode + countries), Usage tab (read-only spend). Server-side proxy via WP REST. See M7 below and the design doc at [`40-admin-area-extension.md`](40-admin-area-extension.md).

### Next
- [ ] Add an admin-side "test connection" button that pings the configured API URL from the browser (not server-side — same origin model as the widget). _(May land naturally as part of M7's Connection tab.)_
- [ ] Conversation reset affordance (clear `localStorage`, mint fresh session). _(Orthogonal to M7.)_

### Done (pending 0.4.0 cut)
- [x] **M6 — API v0.16 catch-up.** Widget handles `402 budget_exhausted_daily`, `403 geo_blocked`, and `200 { session_terminated: true }`. Terminology sweep. Verified end-to-end on devx 2026-05-23. Awaiting 0.4.0 version bump + tag.

### Later (deferred to dedicated milestones)
- [ ] **Soft-handoff email capture** — on `session_terminated: true`, swap the input row for a one-field form that POSTs `/sessions/visitor-email`. Deferred from M6 to keep the catch-up tight; revisit alongside the operator-controls work in M5.
- [ ] **Origins management in wp-admin** — `POST/DELETE /admin/chatbots/{slug}/origins` from the Connection tab. Deferred from M7 (operators use `./bin/sw chatbot origins add` today).
- [ ] **Provider API key setting in wp-admin** — `PATCH /admin/chatbots/{slug}/api-key`. Deferred from M7 (credential-handling escalation, wants its own design pass).
- [ ] **Model swap in wp-admin** — `model_slug` PATCH. Deferred from M7 (wrong model breaks replies — risky knob for a non-technical merchant).
- [ ] Abuse / rate-limit hardening: per-IP throttling on `POST /sessions`, simple bot detection (timing + honeypot), token-spend ceiling per visitor.
- [ ] Theme override hook for `templates/` once we have any front-end PHP templates worth exposing.
- [ ] **Integrations framework** — see [`10-integrations-architecture.md`](10-integrations-architecture.md).
- [ ] **WooCommerce integration** — see [`11-integration-woocommerce.md`](11-integration-woocommerce.md).
- [ ] **Independent Analytics integration** — see [`12-integration-independent-analytics.md`](12-integration-independent-analytics.md).
- [ ] **System context blocks: admin CRUD + sync** — see [`20-system-blocks-api.md`](20-system-blocks-api.md). Upstream API routes need to be designed and built first.

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

### M4 — Integrations & curated context (planning only)
The pivot from "generic chat widget" to "WordPress/WooCommerce-aware assistant". Planning docs landed 2026-05-19; nothing built yet.
- Integrations framework (per-plugin bridges; see [`10-integrations-architecture.md`](10-integrations-architecture.md)).
- WooCommerce integration ([`11-integration-woocommerce.md`](11-integration-woocommerce.md)).
- Independent Analytics integration ([`12-integration-independent-analytics.md`](12-integration-independent-analytics.md)).
- Admin CRUD for system context blocks + sync to upstream API ([`20-system-blocks-api.md`](20-system-blocks-api.md)). New upstream routes required.

### M5 — Operator controls (planning only)
The knobs a merchant actually needs before they'll trust this in production. Planning landed 2026-05-19; nothing built yet. See [`30-operator-controls.md`](30-operator-controls.md) for the full brainstorm and the recommended v1 cut (budget caps, schedule, country gating, per-language welcome text, hide-on-checkout, consent gate, budget-threshold email).

### M6 — API v0.16 catch-up ✅ (pending 0.4.0 cut)
Resumed browser-side chat against the production-shape API. The chat-path endpoints (`GET /sessions/can-start`, `POST /sessions`, `POST /chat`, `GET /messages`) are **unchanged at the wire level**; what was new was the **denial vocabulary** introduced upstream by M20 (daily + per-session USD caps) and the chat-path geo lockout.

Done:
- Widget handles `402 budget_exhausted_daily` on `GET /sessions/can-start`, `POST /sessions`, and `POST /chat`. Probe path caches a `budget-exhausted` state with an explicit "next UTC midnight" expiry. Mid-session, the input is disabled with a polite system message.
- Widget handles `403 geo_blocked` on chat-path routes — drops the cached token and hides the widget for this visitor.
- Widget handles `200 { session_terminated: true }` on `POST /chat` — renders the final reply, persists a per-host `terminated` flag in `localStorage`, disables the input.
- `:disabled` CSS so the lock state is visually obvious.
- Terminology sweep: "website(s)" → "chatbot(s)" across `README.md` and the dev-notes planning docs.
- End-to-end verification on devx 2026-05-23.

Still pending: 0.4.0 version bump in `site-walker-wp.php` (header + `STWLK_PLUGIN_VERSION`), tracker version line, CHANGELOG date; tag + push.

### M7 — Admin-area extension (in progress)
The wp-admin surface that hangs off the upstream `/admin/chatbots/*` API. M6 made the chat path resilient to the new denial vocabulary; M7 gives the merchant the knobs that produce those denials — budget caps, geo policy, welcome message, persona — without making them shell into the host and run `./bin/sw`. Full design in [`40-admin-area-extension.md`](40-admin-area-extension.md).

**Architecture (one paragraph):** wp-admin JS / forms → WP REST endpoints under `/wp-json/site-walker/v1/admin/*` (manage_options + nonce gate) → `Admin_API_Client` PHP wrapper (`wp_remote_request`, attaches the account admin bearer) → upstream `/admin/chatbots/*`. Server-side because the admin key grants full account control and must never ship to a visitor's (or even an admin's) browser.

**Plugin work:**
- [ ] **`Admin_API_Client`** — PHP wrapper around `wp_remote_request` to the upstream admin surface. Returns a uniform `[ok, data | status, error, detail]` envelope. Handles bearer-required, bearer-invalid, network errors.
- [ ] **WP REST routes** — `/wp-json/site-walker/v1/admin/{connection, chatbot, chatbot/geo, chatbot/usage}`. Manage_options + nonce gated.
- [ ] **Connection tab** — admin key field (masked-after-save), API URL (moved here from General), auto-discover chatbot slug after key save, test-connection button.
- [ ] **Chatbot tab** — welcome message, persona, daily/session budget caps, soft-handoff threshold percent. Fetch-on-load, PATCH-on-save.
- [ ] **Geo tab** — mode (`allowall` / `blocklist` / `allowlist`) + countries (ISO 3166-1 alpha-2).
- [ ] **Usage tab** — read-only spend display with `since` selector (1h / 24h / 7d / all-time).
- [ ] **Tab gating** — API-backed tabs (Chatbot / Geo / Usage) render a stub pointing to Connection when no valid admin key is configured.
- [ ] **CHANGELOG + release** — entry under [Unreleased] / pending 0.5.0. Version bump and tag deferred until end-to-end verification.

**Explicitly out of scope** (each tracked under "Later"):
- Origins management UI — operators use `./bin/sw chatbot origins add` today.
- Provider (BYO) LLM API key setting — credential handling wants its own design pass.
- Model swap — risky knob for non-technical merchants.
- System blocks CRUD — wants its own UX (see [`20-system-blocks-api.md`](20-system-blocks-api.md)).
- Account-level provisioning (`/admin/accounts/*`) — that's `site-walker-for-woo` territory.

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
- **API origin allowlist:** the Site Walker chatbot must have `https://devx.headwall.tech` added via `sw chatbot origins add <slug> https://devx.headwall.tech` before any session-mint will succeed. _(Was `sw website origins add` pre-v0.16.)_
- **Local API base URL:** `http://127.0.0.1:47830` (dev — `localhost` also works), `https://api.site-walker.net` (prod default).
