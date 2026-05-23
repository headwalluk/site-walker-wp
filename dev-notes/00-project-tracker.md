# Project Tracker

**Version:** 0.3.0
**Last Updated:** 2026-05-23
**Current Phase:** M8 — Admin mode (in progress)
**Overall Progress:** ~50% of "v1 shippable"

---

## Overview

WordPress plugin that injects a floating chat widget into the front-end and talks directly from the visitor's browser to a [Site Walker](https://site-walker.net) API instance. The widget uses `localStorage` and a cached reachability probe to keep network traffic to a minimum (avoid hammering the API and avoid round-trips on every page load).

Settings are managed via a tabbed WP admin page (General / Appearance). All visual / positional configuration is colour-picker- and form-driven; no shortcodes or theme code required.

---

## Active TODO Items

### In progress
- [ ] **M8 — Admin mode.** Surface upstream M21's admin-mode session minting: when a logged-in WP admin loads a page, the widget calls back to a server-side WP REST route which mints an admin-mode session via `POST /admin/chatbots/{slug}/sessions` and relays the token. The account admin key never reaches the browser. See M8 below.

### Next
- [ ] Add an admin-side "test connection" button that pings the configured API URL from the browser (not server-side — same origin model as the widget). _(May land naturally alongside M7 polish.)_
- [ ] Conversation reset affordance (clear `localStorage`, mint fresh session). _(Orthogonal.)_

### Done (pending 0.5.0 cut)
- [x] **M6 — API v0.16 catch-up.** Widget handles `402 budget_exhausted_daily`, `403 geo_blocked`, and `200 { session_terminated: true }`. Terminology sweep. Verified end-to-end on devx 2026-05-23.
- [x] **M7 — Admin-area extension.** Connection / Chatbot / Geo / Usage tabs in wp-admin. Server-side REST proxy via `Admin_API_Client`. Account admin key stored as wp_option, never exposed to the browser. Auto-discover flow for the chatbot slug. Verified end-to-end on devx 2026-05-23.

### Later (deferred to dedicated milestones)
- [ ] **Operational availability — widget + settings (M21 catch-up follow-up).** Widget handling for `503 chatbot_closed` (hide widget + "opens at X" message, honour `Retry-After`); Chatbot tab gains `timezone` + `availability` fields; Usage tab surfaces the new `customer` / `admin` split. Deferred from M8 to keep the slim-cut admin-mode milestone tight.
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

### M7 — Admin-area extension ✅ (pending 0.5.0 cut)
The wp-admin surface that hangs off the upstream `/admin/chatbots/*` API. M6 made the chat path resilient to the new denial vocabulary; M7 gives the merchant the knobs that produce those denials — budget caps, geo policy, welcome message, persona — without making them shell into the host and run `./bin/sw`. Full design in [`40-admin-area-extension.md`](40-admin-area-extension.md).

Architecture: wp-admin JS / forms → WP REST endpoints under `/wp-json/site-walker/v1/admin/*` (manage_options + nonce gate) → `Admin_API_Client` PHP wrapper (`wp_remote_request`, attaches the account admin bearer) → upstream `/admin/chatbots/*`. Server-side because the admin key grants full account control and must never ship to a visitor's (or even an admin's) browser.

Done:
- `Admin_API_Client` + `Admin_REST` (REST routes under `/wp-json/site-walker/v1/admin/*`, manage_options + nonce gated).
- Four tabs in wp-admin: Connection, Chatbot, Geo, Usage. Tab gating on the API-backed three when the admin key isn't configured yet.
- Bug-fix: unwrap upstream `{chatbots: [...]}` envelope (had assumed bare array; corrupted picker), with a `toArray()` defensive guard on the JS side.

Still pending: 0.5.0 cut — `Version:` header + `STWLK_PLUGIN_VERSION` in `site-walker-wp.php`, tracker version line, CHANGELOG date. Wraps M6 + M7 + M8 together.

### M8 — Admin mode (in progress)
Surface upstream M21's admin-mode session minting in the front-end widget. When a logged-in WP admin loads a page, the widget mints a session via a new server-side WP REST route (instead of the standard `POST /sessions`), which calls upstream `POST /admin/chatbots/{slug}/sessions` using the account admin key the WP host already holds. The admin-mode session bypasses operational hours, geo, origin allowlist, daily-cap, soft-handoff, and the handoff webhook on the upstream side; the WP plugin doesn't need to special-case any of that — it just needs to know it's an admin session so it can render the welcome correctly and not pollute the customer's localStorage.

**Architecture:**
```
Browser (admin user)             WP backend (PHP)                                site-walker API
─────────────────────            ────────────────                                ───────────────
data-is-logged-in="1"
on .site-walker-wp div
                       fetch    /wp-json/site-walker/v1/admin-session   ─►  current_user_can('manage_options') ✓
                                                                            POST /admin/chatbots/{slug}/sessions
                                                                            (Authorization: Bearer sw_<admin>)
                                                                            ◄── 201 { session_token, welcome_message, is_admin_mode: true }
                       ◄─ relay back to browser
widget treats token
identically to any
session token; chats
via /chat as usual
```

**Plugin work:**
- [ ] **Server-side mint route + signal** — `POST /wp-json/site-walker/v1/admin-session` (manage_options + nonce gated), reads `OPT_CHATBOT_SLUG`, calls upstream via existing `Admin_API_Client`. Public_Hooks adds `data-is-logged-in="1"` to the widget container when the rendered page is for an admin user; localises the admin-session URL + nonce into `window.siteWalkerWP.adminSession` only in that case.
- [ ] **Widget admin-mode branch** — detect the attribute; skip the probe (upstream skips all gates anyway); mint via the WP-backend endpoint instead of `POST /sessions`; cache the admin token under a separate `localStorage` key so it doesn't clobber a logged-out customer chat on the same browser. Welcome message renders normally — the upstream `**Admin mode**\n\n` prefix becomes the in-message visual cue via our existing markdown formatter.
- [ ] **CHANGELOG entry** under the same pending 0.5.0 section as M6 + M7, or split into 0.6.0 — decide at release-cut time.

**Explicitly out of scope** (tracked under "Later" as "Operational availability — widget + settings"):
- Widget handling for the new `503 chatbot_closed` denial.
- Chatbot tab fields for `timezone`, `availability`, `admin_session_budget_usd`.
- Usage tab surfacing the new `customer` / `admin` spend split.
- `PATCH /admin/chatbots/{slug}` whitelist expansion to allow the three new fields through. (Leave the whitelist tight until the UI exposes them.)

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
