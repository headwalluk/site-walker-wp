# Project Tracker

**Version:** 1.2.0
**Last Updated:** 2026-06-09
**Current Phase:** 1.2.0 — M12 (Context blocks editor) built, pending in-browser verification; then remaining polish queue + M4 integrations
**Overall Progress:** 100% of "v1 shippable"

---

## Overview

WordPress plugin that injects a floating chat widget into the front-end and talks directly from the visitor's browser to a [Site Walker](https://site-walker.net) API instance. The widget uses `localStorage` and a cached reachability probe to keep network traffic to a minimum (avoid hammering the API and avoid round-trips on every page load).

Settings are managed via a tabbed WP admin page (Connection / Widget / Appearance / Chatbot / Geo / Usage / Sessions). All visual / positional configuration is colour-picker- and form-driven; no shortcodes or theme code required.

---

## Active TODO Items

### In progress
- _(M11 just shipped in 1.1.0 — nothing in flight)_

### Next session (queued)
- [ ] **Admin-area tidy.** Multiple "Save settings" buttons across the tabs are disorientating — only one Save per page would be cleaner. User wants to review the current behaviour in operation for a day or so before deciding what to change, so this is queued (not done now).
- [x] **Plan a chatbot-content editor admin tab.** Planned as **[M12 — Context blocks editor](#m12--context-blocks-editor-admin-tab--planned--build-as-a-sprint)** below. UI label settled as "Context" (no "system blocks" terminology surfaced); builds to the shipped flat disk-block API rather than the richer unbuilt catalogue in [`20-system-blocks-api.md`](20-system-blocks-api.md). Ready to build as a sprint.

### Done (shipped in 1.1.0)
- [x] **M11 — Formatter extension + consolidation.** Chat-text rendering tidy-up: headings, bullet lists, ordered lists, italic, and markdown-syntax `[label](url)` links now render as HTML in the chat bubble instead of leaking through as plain text. Assistant-message formatter consolidated into a single shared module (`assets/shared/formatter.js`, exposed as `window.SiteWalkerFormatter`) consumed by both `widget.js` and `admin.js`. Folded in the 60-doc URL/markdown collision fix (trailing `*` / `_` no longer captured into the href). Loose-list grouping fix landed in the same release (blank line between items doesn't end the list any more). Retired the widget.js NUL-byte sentinel gotcha — file is plain ASCII now (`~~SWWPLINK<n>~~` placeholder scheme).

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

**═══════════════ ↑ Required for 1.1.0  |  ↓ Post-1.1.0 ═══════════════**

---

### Polish (post-1.1.0)
- [ ] Admin-side "test connection" button on the Connection tab (the "Refresh" button does the same round-trip; a labelled "test" cycle is friendlier).
- [ ] Conversation reset affordance — clear `localStorage`, mint a fresh session. Useful for QA + for visitors who want to start over.

### Deferred admin-area features (post-1.0.0)
- [ ] **Origins management in wp-admin.** Now that the plugin auto-scopes to one chatbot, an "add/remove origins" affordance on the Connection tab is natural for multi-site setups. Lower priority since auto-scope handles the common case (one site, one origin).
- [ ] **Provider API key setting in wp-admin** — `PATCH /admin/chatbots/{slug}/api-key`. Credential-handling escalation; wants its own design pass.
- [ ] **Model swap in wp-admin** — `model_slug` PATCH. Risky knob for a non-technical merchant; wrong model breaks replies.

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

### M11 — Formatter extension + consolidation ✅ (shipped in 1.1.0)
Tidy up chat-text rendering so it handles the markdown the model actually emits (the widget is currently leaking literal `## heading` and `- bullet` lines through to visitors), and consolidate the duplicated formatter across `widget.js` and `admin.js` into a shared module. Rolls in the URL/markdown collision fix already diagnosed in [`60-formatter-url-markdown-collision.md`](60-formatter-url-markdown-collision.md) — its anticipated shared-module refactor is the natural home for this work.

In-house renderer rather than an off-the-shelf library: the trusted-host allowlist (only same-origin + operator-curated external hosts get `<a>` tags; everything else stays plain text) doesn't have a clean extension point in any of the small markdown libs (snarkdown, drawdown, marked) — they all either linkify every URL or none, which would force us to keep our own linkifier running alongside the lib. Cheaper to add the missing features directly than to wire up a third actor in the sentinel dance.

**Scope (inline):**
- [x] `*em*` / `_em_` → `<em>` (single-line, no nesting beyond existing `**strong**`).
- [x] `[text](url)` → `<a>` honouring the existing `trustedHosts` allowlist. Non-allowlisted hosts: render `text` as plain text and drop the link silently (the model asserted the URL belongs there; we don't want to surface a hallucinated phishing route, but we also don't want to surface the raw URL when the model deliberately hid it behind link syntax).
- [x] Existing `**strong**` and inline backtick `code` preserved unchanged.

**Scope (block):**
- [x] `# `, `## `, `### ` → `<h3>` (collapse all heading levels — visitor-side widget shouldn't introduce a page-h1, and shifting by 2 lets `####` produce `<h6>` which is silly; collapse-all keeps typography predictable).
- [x] Consecutive `- ` lines → `<ul><li>…</li></ul>` (group adjacent bullet lines into one list).
- [x] Consecutive `1. ` / `2. ` lines → `<ol><li>…</li></ol>` (semantic ordered list; CSS controls numbering display).
- [x] Block elements stay line-oriented — no need to handle nested lists or block elements inside other blocks.

**Refactor:**
- [x] Factor the formatter into a shared JS module enqueued by both `widget.js` and `admin.js` (60-doc flags this as the right next move once the formatter sees another change — this is that change). Lives at `assets/shared/formatter.js`, exposed as `window.SiteWalkerFormatter` (`.formatAssistant(raw, opts)`, `.format(raw, role, opts)`, `.escape(s)`).
- [x] Convert sentinels to an ASCII scheme so `widget.js` stops being a binary as far as git is concerned. Used `~~SWWPLINK<n>~~` rather than `admin.js`'s previous `__SWWPLINK<n>__` because the new `_em_` italic regex would otherwise eat the sentinel's own underscores (`_SWWPLINK0_` is a valid italic match inside `__SWWPLINK0__`); `~` is the only printable ASCII char that doesn't collide with any of our markdown delimiters. NUL-byte gotcha gone.
- [x] Land the URL/markdown collision fix from 60-doc in the new shared module (the two-line trailing-strip extension).
- [x] Strip the NUL-sentinel gotcha section from `CLAUDE.md` once the shared module ships; replace with a brief note about the new module's location + sentinel scheme.

**Verification:**
- [x] Walk through each row of 60-doc's test table against the refactored formatter (regression check on the bug-fix portion). Node-driven, see notes below.
- [ ] Eyeball-test fresh markdown inputs: a `##` heading mid-reply, a 3-item `- ` bullet list, a `1.`/`2.`/`3.` ordered list, an `*italic phrase*`, `[click here](https://example.com)` with both an allowlisted and non-allowlisted host. _(needs in-browser pass — user)_
- [ ] Confirm Sessions-tab message rendering matches widget rendering (both load the same shared module). _(needs in-browser pass — user)_
- [x] CHANGELOG entry under `[Unreleased]`.

Also added minimal chat-bubble CSS for the new block elements (`h3`, `ul`, `ol`, `li`, `em`) in both `assets/public/widget.css` and `assets/admin/admin.css` so browser-default margins/padding don't blow out the bubble width.

Pre-implementation regex testing was done with a node harness (`/tmp/sw-formatter-test.js`) that stubs `window` and runs `formatAssistant()` over the 60-doc test table + the new markdown scope. All 60-doc rows produce the expected anchors; new rows produce the expected `<h3>` / `<ul>` / `<ol>` / `<em>` shapes; the two user-reported leak cases (`## Some text`, `- some text`) now wrap correctly.

**Out of scope (won't do):**
- Nested lists, blockquotes, tables, images, horizontal rules, fenced code blocks, raw HTML passthrough.
- Multi-line code blocks (inline backticks are already covered; fenced blocks aren't a chat-reply shape).
- Configurable heading-level mapping (collapse-to-h3 is fine as a baked-in choice).

Estimated effort: 2-3 hours — mostly the refactor + the verification matrix; the regex additions themselves are small.

### M12 — Context blocks editor (admin tab) ✅ (built — pending in-browser verification)
A new **Context** tab in wp-admin that lets the site owner add / edit / delete the chatbot's system context blocks — the markdown files the upstream API stitches into the LLM's system prompt — without shelling into the host. Surfaces the upstream filesystem-backed block API (`GET/PUT/DELETE /admin/chatbots/{slug}/blocks[/{name}]`, documented in the API project's `docs/api-admin.md`).

**Reality check on the design notes.** The earlier proposal in [`20-system-blocks-api.md`](20-system-blocks-api.md) describes a much richer DB-backed catalogue (`priority`, `enabled` toggles, `tags`, `source` badges, a `/preview` endpoint, integration sync) that **was never built upstream**. What actually shipped is a flat, filesystem-backed surface: each block is a named markdown file with content and a byte size — nothing else. This milestone builds to what exists; `20-system-blocks-api.md` is superseded and should be marked as such (the richer catalogue, if ever wanted, is a separate upstream project).

**The flat API surface:**
- `GET /admin/chatbots/{slug}/blocks` → `{blocks:[{name,size}]}`.
- `GET /admin/chatbots/{slug}/blocks/{name}` → raw `text/markdown` body (not JSON).
- `PUT /admin/chatbots/{slug}/blocks/{name}` → write/overwrite; body content-type must be `text/markdown` / `text/plain`; 64 KB cap (413 over).
- `DELETE /admin/chatbots/{slug}/blocks/{name}` → 204.
- Name pattern `^[A-Za-z0-9_-]+$`. Reserved (PUT 400s): `PERSONA` (lives on the Chatbot tab) and `HANDOFF_FINAL`. `HANDOFF_SOFT` / `HANDOFF_HARD` **are** writable — the operator-customisable handoff messages.

**UX (settled with the user):** label the tab **Context** (don't surface "system blocks" terminology in the UI). Flat list with hints — all blocks in one master-detail list; the "new block" name field carries a hint naming the special/reserved names rather than a separate dedicated handoff section.

**Architecture:** mirrors the other REST-driven tabs. New proxy routes in `Admin_REST` under `/wp-json/site-walker/v1/admin/chatbot/blocks[/{name}]`, manage_options + nonce gated, via the existing `Admin_API_Client` and stored slug. The one genuine wrinkle: blocks are `text/markdown`, not JSON, in both directions — the existing client always JSON-encodes the body, sends `Accept: application/json`, and JSON-decodes the response, so the single-block get/put needs a raw-body / raw-response path. List + delete stay on the existing JSON helpers. No transient caching — fetch-on-activate, consistent with Chatbot / Geo / Usage / Sessions.

**Plugin work:**
- [x] **`Admin_API_Client` raw path** — `get_raw()` / `put_raw()` (+ private `request_raw()`): send a string body with a caller-supplied `Content-Type`, return the response body unparsed on success; the failure path still decodes the JSON `{error,detail}` envelope.
- [x] **REST routes** — four proxies in `Admin_REST`: list (`GET …/blocks`, JSON passthrough), get-one (`GET …/blocks/{name}`, wraps raw markdown into `{name,content}`), put (`PUT …/blocks/{name}`, takes `{content}` JSON → `text/markdown` body, enforces the 64 KB cap), delete. Name pattern + reserved-name rejection shared via `validate_block_name()`; client/slug guard extracted to `resolve_client_and_slug()` (now also used by `proxy_to_chatbot()`). Name regex + `RESERVED_BLOCK_NAMES` + `BLOCK_MAX_BYTES` live in `constants.php`.
- [x] **Context tab partial** — `admin-templates/tabs/blocks.php`. Not-configured / loading scaffold; master-detail list (name + KB size, clickable) → editor with name + markdown textarea, Save / Delete, live byte counter, and the special-names hint under the name field.
- [x] **`initBlocksTab()` in `admin.js`** — fetch list on `swwp:tab-activate`; in-place master-detail (not hash-routed, to avoid a `#blocks/new` collision) load / save / delete; UTF-8 byte counter; client-side mirror of name + size validation. Wired into the `$(function(){…})` init block.
- [x] **Nav + registration** — `'blocks' => __('Context', …)` in `$tabs` and `'blocks' => 'blocks.php'` in `$rest_tabs` (`settings-page.php`), placed right after Chatbot.
- [x] **CSS** — list / editor layout + byte-counter over-limit state in `admin.css`, matching the file's existing style.
- [x] **Docs** — `20-system-blocks-api.md` marked superseded; CHANGELOG entry under `[Unreleased]`.

**Verification:**
- [ ] Create, edit, and delete a free-form block; confirm the change is live on the next chat turn (upstream re-reads per request — no restart).
- [ ] Edit a `HANDOFF_SOFT` block and confirm the customised handoff copy appears in a forced soft-handoff (`SW_SIM_SOFT_HANDOFF_AFTER_USER_TURNS`).
- [ ] Confirm reserved names (`PERSONA`, `HANDOFF_FINAL`) and over-64 KB / bad-name inputs surface friendly errors rather than raw codes. _(in-browser pass)_

**Out of scope (won't do here):** priority / ordering, enable-disable toggles, tags, assembled-prompt preview, integration-generated blocks + sync — all part of the unbuilt `20-system-blocks-api.md` catalogue, not the shipped flat API.

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
