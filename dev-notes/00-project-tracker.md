# Project Tracker

**Version:** 1.0.0
**Last Updated:** 2026-05-25
**Current Phase:** 1.0.0 shipped — next: polish + M4 integrations (below the line)
**Overall Progress:** 100% of "v1 shippable"

---

## Overview

WordPress plugin that injects a floating chat widget into the front-end and talks directly from the visitor's browser to a [Site Walker](https://site-walker.net) API instance. The widget uses `localStorage` and a cached reachability probe to keep network traffic to a minimum (avoid hammering the API and avoid round-trips on every page load).

Settings are managed via a tabbed WP admin page (Connection / Widget / Appearance / Chatbot / Geo / Usage / Sessions). All visual / positional configuration is colour-picker- and form-driven; no shortcodes or theme code required.

---

## Active TODO Items

### In progress
- _(M9 + M10 just landed on `main` — nothing in flight)_

### Required for 1.0.0
- _(empty — all line items shipped to `main`; cutting 1.0.0 is the next step)_

### Done (shipped in 1.0.0)
- [x] **M9 — Session review.** Sessions tab with paginated list + click-through detail view, hash-routed (`#sessions` + `#sessions/<id>`).
- [x] **M10 — Operational availability.** Widget handles `503 chatbot_closed`; Chatbot tab gains `timezone` / `availability` (per-day grid) / `admin_session_budget_usd`; Usage tab surfaces the `customer` / `admin` spend split.
- [x] **Origin-scoped chatbot selection.** Connection tab no longer offers a picker — it auto-selects the chatbot whose origin allowlist contains `site_url()`. Zero-match returns a specific `no_origin_match` error naming the `sw chatbot origins add` command the operator needs to run. Test-connection now verifies the saved chatbot's allowlist still contains this URL. Closes the real-world footgun where an operator could pick a chatbot bound to another site's origin and have admin-mode sessions silently route there.
- [x] **Soft / hard handoff — full loop.** Upstream landed the `SW_SIM_SOFT/HARD_HANDOFF_AFTER_USER_TURNS` sim hooks (v0.20.0 / M23.5) so soft- and hard-handoff can be forced early without burning real spend. Plugin side closed the loop: a proper three-state email-capture UI (chat mode with a small "Request an email back" CTA → entry mode → result mode with success/error + retry + optional "Back to chat" link). Handles both voluntary (CTA click) and involuntary (hard-handoff) entry. Successful submission persists across reloads. Pre-existing bug fixed: `discardToken()` now actually re-enables the input + clears all UI lock state on token recycle.
- [x] **Mobile + accessibility verification.** Manual pass on real device confirmed widget layout, tap targets, viewport behaviour are all fine. Baseline accessibility (focus trap, ESC to close, screen-reader labels) is acceptable for 1.0.0.
- [x] **GitHub-Releases auto-updater + release workflow.** Imported the `Github_Updater` class from the Quick 2FA plugin and the matching `.github/workflows/release.yml` tag-builds-and-publishes action. Refactored both for Site Walker namespacing / identifiers. Tagging `v1.0.0` (and onward) on `main` triggers the workflow → builds `site-walker-wp-<version>.zip` + a stable `site-walker-wp.zip` → publishes to GitHub Releases. The updater polls GitHub's `releases/latest` API (1h transient cache) and surfaces new versions through WP's native plugin-update UI. Disable per-site via the `site_walker_updater_enabled` filter.
- [x] **UX polish (post-M10).** Em-sized text + budget inputs (was px). Chatbot-availability vocabulary throughout (`Always online` / `(chatbot offline)` / `We're unavailable until …`) instead of the store-idiom `open` / `closed`.

### Done (shipped in 0.5.0)
- [x] **M6 — API v0.16 catch-up.** Widget handles `402 budget_exhausted_daily`, `403 geo_blocked`, and `200 { session_terminated: true }`. Terminology sweep ("website(s)" → "chatbot(s)"). Verified end-to-end on devx 2026-05-23.
- [x] **M7 — Admin-area extension.** Connection / Chatbot / Geo / Usage tabs in wp-admin. Server-side REST proxy via `Admin_API_Client`. Account admin key stored as wp_option, never exposed to the browser. Auto-discover flow for the chatbot slug. Verified end-to-end on devx 2026-05-23.
- [x] **M8 — Admin mode.** Logged-in admins get a WP-backend-proxied admin-mode session that bypasses operator-imposed gates upstream; account admin key stays server-side.
- [x] **UX polish.** Trusted external hosts allowlist on the Widget tab (URLs to operator-curated hosts get linkified in assistant replies; same-host always linkified; everything else stays plain text). Textarea grew to 2 rows. New `CLAUDE.md` capturing the widget.js NUL-sentinel gotcha so future editors don't repeat the diagnostic.

---

**═══════════════ ↑ Required for 1.0.0  |  ↓ Post-1.0.0 ═══════════════**

---

### Polish (post-1.0.0)
- [ ] **Formatter URL/markdown collision fix.** The widget's URL tokenizer greedily eats trailing `*` / `_` markdown delimiters into the URL match, breaking links like `**https://example.com/path**` (which 404 because `**` ends up in the href). Two-line trailing-strip regex fix in `widget.js` + `admin.js`. Full diagnosis + test table in [`60-formatter-url-markdown-collision.md`](60-formatter-url-markdown-collision.md). ~15 min.
- [ ] Admin-side "test connection" button on the Connection tab (the "Refresh" button does the same round-trip; a labelled "test" cycle is friendlier).
- [ ] Conversation reset affordance — clear `localStorage`, mint a fresh session. Useful for QA + for visitors who want to start over.

### Deferred admin-area features (post-1.0.0)
- [ ] **Origins management in wp-admin.** Now that the plugin auto-scopes to one chatbot, an "add/remove origins" affordance on the Connection tab is natural for multi-site setups. Lower priority since auto-scope handles the common case (one site, one origin).
- [ ] **Provider API key setting in wp-admin** — `PATCH /admin/chatbots/{slug}/api-key`. Credential-handling escalation; wants its own design pass.
- [ ] **Model swap in wp-admin** — `model_slug` PATCH. Risky knob for a non-technical merchant; wrong model breaks replies.
- [ ] **System context blocks: admin CRUD + sync** — see [`20-system-blocks-api.md`](20-system-blocks-api.md).

### Strategic future work (post-1.0.0)
- [ ] **M4 — Integrations framework + WooCommerce + Independent Analytics.** The product pivot from "generic chatbot" to "WP/Woo-aware assistant" — the v2 differentiator. See [`10-integrations-architecture.md`](10-integrations-architecture.md), [`11-integration-woocommerce.md`](11-integration-woocommerce.md), [`12-integration-independent-analytics.md`](12-integration-independent-analytics.md).
- [ ] **M2 — Abuse / rate-limit hardening.** Per-IP throttling on `POST /sessions`, simple bot detection (timing + honeypot), token-spend ceiling per visitor. Largely upstream concerns; the upstream M20 budget caps act as the effective spend ceiling today.
- [ ] **Theme override hook for `templates/`** once we have any front-end PHP templates worth exposing.

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

### M6 — API v0.16 catch-up ✅ (shipped in 0.5.0)
Resumed browser-side chat against the production-shape API. The chat-path endpoints (`GET /sessions/can-start`, `POST /sessions`, `POST /chat`, `GET /messages`) are **unchanged at the wire level**; what was new was the **denial vocabulary** introduced upstream by M20 (daily + per-session USD caps) and the chat-path geo lockout.

Done:
- Widget handles `402 budget_exhausted_daily` on `GET /sessions/can-start`, `POST /sessions`, and `POST /chat`. Probe path caches a `budget-exhausted` state with an explicit "next UTC midnight" expiry. Mid-session, the input is disabled with a polite system message.
- Widget handles `403 geo_blocked` on chat-path routes — drops the cached token and hides the widget for this visitor.
- Widget handles `200 { session_terminated: true }` on `POST /chat` — renders the final reply, persists a per-host `terminated` flag in `localStorage`, disables the input.
- `:disabled` CSS so the lock state is visually obvious.
- Terminology sweep: "website(s)" → "chatbot(s)" across `README.md` and the dev-notes planning docs.
- End-to-end verification on devx 2026-05-23.

_(Folded into the 0.5.0 cut alongside M7 + M8.)_

### M7 — Admin-area extension ✅ (shipped in 0.5.0)
The wp-admin surface that hangs off the upstream `/admin/chatbots/*` API. M6 made the chat path resilient to the new denial vocabulary; M7 gives the merchant the knobs that produce those denials — budget caps, geo policy, welcome message, persona — without making them shell into the host and run `./bin/sw`. Full design in [`40-admin-area-extension.md`](40-admin-area-extension.md).

Architecture: wp-admin JS / forms → WP REST endpoints under `/wp-json/site-walker/v1/admin/*` (manage_options + nonce gate) → `Admin_API_Client` PHP wrapper (`wp_remote_request`, attaches the account admin bearer) → upstream `/admin/chatbots/*`. Server-side because the admin key grants full account control and must never ship to a visitor's (or even an admin's) browser.

Done:
- `Admin_API_Client` + `Admin_REST` (REST routes under `/wp-json/site-walker/v1/admin/*`, manage_options + nonce gated).
- Four tabs in wp-admin: Connection, Chatbot, Geo, Usage. Tab gating on the API-backed three when the admin key isn't configured yet.
- Bug-fix: unwrap upstream `{chatbots: [...]}` envelope (had assumed bare array; corrupted picker), with a `toArray()` defensive guard on the JS side.

Shipped in the 0.5.0 cut on 2026-05-23 alongside M6 + M8.

### M8 — Admin mode ✅ (shipped in 0.5.0)
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
- [x] **Server-side mint route + signal** — `POST /wp-json/site-walker/v1/admin-session` (manage_options + nonce gated), reads `OPT_CHATBOT_SLUG`, calls upstream via existing `Admin_API_Client`. Public_Hooks adds `data-is-logged-in="1"` to the widget container when the rendered page is for an admin user; localises the admin-session URL + nonce into `window.siteWalkerWP.adminSession` only in that case.
- [x] **Widget admin-mode branch** — detects the attribute; skips the probe (upstream skips all gates anyway); mints via the WP-backend endpoint instead of `POST /sessions`; caches the admin token under a separate `localStorage` key so it doesn't clobber a logged-out customer chat on the same browser. Welcome message renders normally — the upstream `**Admin mode**\n\n` prefix becomes the in-message visual cue via our existing markdown formatter.
- [x] **CHANGELOG entry** — folded into the 0.5.0 entry alongside M6 + M7.

**Explicitly out of scope** (tracked under "Later" as "Operational availability — widget + settings"):
- Widget handling for the new `503 chatbot_closed` denial.
- Chatbot tab fields for `timezone`, `availability`, `admin_session_budget_usd`.
- Usage tab surfacing the new `customer` / `admin` spend split.
- `PATCH /admin/chatbots/{slug}` whitelist expansion to allow the three new fields through. (Leave the whitelist tight until the UI exposes them.)

### M9 — Session review ✅ (pending next release cut)
Surface the upstream M22 routes (`GET /admin/chatbots/{slug}/sessions[?page]`, `GET /admin/chatbots/{slug}/sessions/{id}`, `GET /admin/chatbots/{slug}/sessions/{id}/messages`) in a new **Sessions** wp-admin tab so operators can browse recent conversations and click through to read the full message history. The upstream surface is intentionally read-only and pagination-only for v1; filters (admin/customer segment, date range, has_email, terminated) are post-v1.0 upstream and inherit that deferral here.

**Architecture (one paragraph):** new REST proxy routes in `Admin_REST` under `/wp-json/site-walker/v1/admin/chatbot/sessions[...]`, manage_options + nonce gated as elsewhere, calling upstream via the existing `Admin_API_Client`. New `admin-templates/tabs/sessions.php` with two containers (list + detail) shown alternately based on the URL hash; the existing tab nav extends to handle `#sessions/<id>` (tab name + sub-route).

**Plugin work:**
- [x] **REST routes** — three GETs in `Admin_REST` proxying the M22 surface. Pagination + 100-page-size cap inherited from upstream.
- [x] **Sessions tab partial** — list and detail in a single panel, toggled by JS based on the hash sub-route. Stub when not configured, matching the other API-backed tabs.
- [x] **List view** — paginated table of session rows. Per-row: id (clickable), last-active timestamp (absolute, locale-formatted), message count, tokens in+out, cost estimate, badges for admin-mode and terminated sessions, `mailto:` link on captured visitor emails. Prev/next + "Page X of Y (N total)" at the bottom.
- [x] **Detail view** — header summary (timestamps, totals, badges, visitor email) plus alternating user/assistant message bubbles. Reuses the widget's assistant-message formatting logic — duplicated into `admin.js` with an ASCII sentinel (`__SWWPLINK<n>__`) rather than the widget's NUL bytes so admin.js stays a normal text file (see [CLAUDE.md](../CLAUDE.md) for the NUL gotcha).
- [x] **Hash routing extension** — tab activate parses `#sessions/<id>` to surface the sub-route to the Sessions panel via `event.detail.sub`; clicking a list row updates the hash; browser back/forward Just Work.

### M10 — Operational availability ✅ (pending next release tag)
Caught the plugin up to upstream M21's operational-hours surface. Two distinct halves: the **widget** handles the new `503 chatbot_closed` denial that fires when a visitor lands outside the chatbot's open hours, and the **wp-admin** got the configuration knobs (`timezone`, `availability`, `admin_session_budget_usd`) so operators can set those hours without dropping into `./bin/sw`. Folded in the Usage tab's `customer` / `admin` spend split that also landed in M21.

**Plugin work:**
- [x] **`Admin_REST` whitelist expansion** — added `timezone`, `availability`, `admin_session_budget_usd` to `chatbot_patch`'s allowed-fields list.
- [x] **Localise WP site timezone** — `wp_timezone_string()` injected into the admin localised config (only when it's an IANA name, not a UTC offset).
- [x] **Chatbot tab — `timezone` field** — text input with a "Use this site's timezone" button.
- [x] **Chatbot tab — `availability` per-day grid** — seven rows (Mon-Sun), each with 0..n `HH:MM-HH:MM` windows + add/remove buttons. JS serialises to `{schedule: {mon: ["09:00-17:00"], ...}}` on save; empty → `null` (always online).
- [x] **Chatbot tab — `admin_session_budget_usd`** — number input.
- [x] **Usage tab — customer / admin split** — three-column table layout (Combined / Customer / Admin mode).
- [x] **Widget — `503 chatbot_closed` handling** — new `PROBE_CLOSED` probe state cached with `next_open_at`. Launcher-click during closed hours surfaces "We're unavailable until \<time\>".
- [x] **UX post-pass** — em-sized HH:MM + USD budget inputs; chatbot-availability vocabulary (online/offline rather than open/closed).

**Explicitly out of scope** (deferred — none of these are 1.0.0 blockers):
- Per-day overrides (public holidays, one-off closures). Upstream doesn't support them either.
- Maintenance-mode kill switch (`chatbots.is_paused`). Separate upstream feature, not built yet.
- Auto-detect timezone mismatch warnings ("your WP site's tz is X but the chatbot's is Y").

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
