# Project Tracker

**Version:** 0.3.0
**Last Updated:** 2026-05-22
**Current Phase:** M6 — API v0.16 catch-up (next)
**Overall Progress:** ~40% of "v1 shippable"

---

## Overview

WordPress plugin that injects a floating chat widget into the front-end and talks directly from the visitor's browser to a [Site Walker](https://site-walker.net) API instance. The widget uses `localStorage` and a cached reachability probe to keep network traffic to a minimum (avoid hammering the API and avoid round-trips on every page load).

Settings are managed via a tabbed WP admin page (General / Appearance). All visual / positional configuration is colour-picker- and form-driven; no shortcodes or theme code required.

---

## Active TODO Items

### In progress
- [ ] **M6 — API v0.16 catch-up.** Wire the widget up to the new denial vocabulary so the chat path can be re-enabled on devx. See the M6 milestone below for the full breakdown.

### Next
- [ ] Add an admin-side "test connection" button that pings the configured API URL from the browser (not server-side — same origin model as the widget). _(Orthogonal to M6 — schedule into a polish pass.)_
- [ ] Conversation reset affordance (clear `localStorage`, mint fresh session). _(Orthogonal to M6.)_
- [ ] **Admin-area extension** — once chat is back up, plan the wp-admin work that hangs off the new `/admin/chatbots/*` surface (admin-key field, daily/session budget caps, geo policy, welcome message). Separate milestone, planning starts after M6 verification.

### Done (this release)
- [x] **Unblock browser end-to-end test.** Resolved upstream: API ships CORS middleware and a proper pre-flight endpoint (`GET /sessions/can-start`). Reverse proxy not needed.
- [x] Verify the three-state load flow end-to-end in a real browser: probe-needed → cached-available → session-in-progress. Confirmed against the live API on 2026-05-18.
- [x] Switch probe from `GET /` to `GET /sessions/can-start`; treat `200 { "ok": true }` as the only success signal.

### Later (deferred to dedicated milestones)
- [ ] **Admin API key (account-scoped)** — store the operator's account admin key as a WP option, send it on any `/admin/chatbots/*` request the plugin makes. Replaces the older "per-website API key" thinking; the chat path itself stays bearer-session-token-authenticated and never carries this key. Lands as the first step of the admin-area extension once M6 ships.
- [ ] **Soft-handoff email capture** — on `session_terminated: true`, swap the input row for a one-field form that POSTs `/sessions/visitor-email`. Deferred from M6 to keep the catch-up tight; revisit alongside the operator-controls work in M5.
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

### M6 — API v0.16 catch-up (next)
Resume browser-side chat against the production-shape API (v0.16). The chat-path endpoints (`GET /sessions/can-start`, `POST /sessions`, `POST /chat`, `GET /messages`) are **unchanged at the wire level**; what's new is the **denial vocabulary** introduced upstream by M20 (daily + per-session USD caps) and the chat-path geo lockout. The "websites → chatbots" rename is conceptual / CLI-side only — no plugin code references the old term, only the docs do.

Scope is deliberately chat-only. Admin API key plumbing and any new wp-admin settings ride in a separate later milestone once chat is back on its feet — see "Admin-area extension" under Next and "Admin API key" under Later.

**Operator prereq (already done on devx 2026-05-22):** new-API account + chatbot + origin allowlist re-provisioned via `sw account create … && sw chatbot create … --account … && sw chatbot origins add … && sw chatbot set-model …`. The widget itself doesn't care which side of the rename it's talking to.

**Plugin work:**
- [ ] **Widget — handle `402 budget_exhausted_daily`** on `GET /sessions/can-start`, `POST /sessions`, and `POST /chat`. On probe / mint paths: treat as "unavailable for the rest of the UTC day" — store as a probe-state variant that survives the normal `probeCooldownMs` so we don't hammer mint. On mid-session `/chat`: render a polite system message, disable the input.
- [ ] **Widget — handle `403 geo_blocked`** on chat-path routes (`POST /sessions`, `GET /sessions/can-start`, `POST /chat`, `GET /messages`). Drop any cached token and hide the widget for this visitor; don't retry mint — it will also 403.
- [ ] **Widget — handle `200 { session_terminated: true }` on `POST /chat`**. Render the assistant's final reply, then disable the input. Subsequent `/chat` returns the canned `HANDOFF_HARD.md` content with `message_id: 0` — same `session_terminated` handling, no special-casing needed.
- [ ] **Docs — terminology sweep.** Rename "website(s)" → "chatbot(s)" in `README.md`, `dev-notes/10-integrations-architecture.md`, `dev-notes/20-system-blocks-api.md`, `dev-notes/30-operator-controls.md`, and the "Notes for Development" footer of this tracker. Update any `sw website …` CLI references to `sw chatbot …`.
- [ ] **End-to-end verification on devx.** Toggle the plugin on, exercise the three load states (probe-needed → cached-available → session-in-progress), the send-turn path, and the rehydrate-on-reload path. Smoke-test one of the new denial paths — easiest is setting `daily_budget_usd` low via the admin HTTP API or `sw chatbot` CLI, tripping it, then raising it again.
- [ ] **Release 0.4.0** with these changes once verification passes; add a CHANGELOG entry naming the new denial codes the widget now handles.

**Explicitly out of scope (lands in later milestones):**
- Account admin key field + any `/admin/chatbots/*` integration → "Admin-area extension" (next planning pass after M6).
- `POST /sessions/visitor-email` email-capture form on the soft-handoff path → revisit alongside M5 operator controls.
- Token-spend ceiling, per-IP throttling, bot detection → M2 abuse-resistance.

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
