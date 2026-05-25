# Changelog

All notable changes to this plugin will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_(nothing yet)_

## [1.1.0] - 2026-05-25

Post-1.0.0 polish release focused on chat-text rendering: visitors no longer see literal `## Heading text` or `- bullet line` markdown leaking into the chat bubble, and the formatter has been consolidated into a single shared module consumed by both the widget and the Sessions admin tab.

### Changed (chat-text rendering — M11)
- Extracted the assistant-message formatter into a shared module, `assets/shared/formatter.js` (`window.SiteWalkerFormatter`), consumed by both the front-end widget (`widget.js`) and the Sessions admin tab (`admin.js`) via a new `site-walker-wp-formatter` enqueue handle. Replaces the two near-duplicate `formatAssistantMessage` / `formatMessageBody` regex blocks that had drifted apart.
- Widened the markdown subset rendered in chat bubbles. Previously **strong**, inline `code`, and bare-URL auto-linking; now adds:
  - `# `, `## `, `### `, `#### `, `##### `, `###### ` → `<h3>` (all heading levels collapse to h3 — visitor-side widget should never introduce a page-h1).
  - `- foo\n- bar\n- baz` → `<ul><li>foo</li><li>bar</li><li>baz</li></ul>`. Consecutive bullet lines collapse into one list wrapper.
  - `1. foo\n2. bar\n3. baz` → `<ol><li>foo</li><li>bar</li><li>baz</li></ol>`. Number isn't honoured (CSS controls display); just used to detect ordered intent.
  - `*em*` and `_em_` → `<em>`.
  - `[label](url)` → `<a>` if `url` matches the trusted-host allowlist; otherwise the label renders as plain text and the URL is dropped (the model deliberately hid the URL behind link syntax, so we don't override that by surfacing the bare URL).
- Closes the rendering leaks the user flagged: literal `## Heading text` and `- bullet line` no longer pass through to the visitor as plain text.
- Folded in the URL/markdown collision fix from [`dev-notes/60-formatter-url-markdown-collision.md`](dev-notes/60-formatter-url-markdown-collision.md) — the bare-URL trailing-strip pattern now recognises `*` and `_` as trailing punctuation, so `**https://example.com/path**` linkifies cleanly inside the bold span instead of capturing `**` into the href.

### Fixed
- Loose lists (model emits a blank line between items, e.g. `1. foo\n\n1. bar\n\n1. baz`) now collapse into a single `<ol>` / `<ul>` instead of producing one list per item. A blank line inside a list no longer ends the list — only a non-blank, non-list line does.

### Removed
- `assets/public/widget.js` no longer contains literal NUL bytes — the new shared module uses `~~SWWPLINK<n>~~` as the placeholder sentinel (printable ASCII, no collision with any markdown delimiter we parse). `git` now treats the file as text. The corresponding NUL-sentinel gotcha section in `CLAUDE.md` has been retired and replaced with a brief pointer to the shared module.

## [1.0.0] - 2026-05-25

First stable release. Pulls together five focused milestones (M6–M10), the release infrastructure (GitHub-Releases auto-updater + tag-driven build workflow), and a final email-capture UX rework into a coherent v1 product. Existing 0.5.0 installs receive this update through the new in-plugin updater on their next WP update cycle.

### Changed (widget email-capture UX)
- The email-capture form is no longer always-visible alongside the chat input on terminated sessions. Replaced the always-on form + always-on thanks panel with a proper state machine:
  - **Chat mode (default).** Chat input visible. A small "Request an email back" link sits below the input row as a voluntary affordance.
  - **Email entry mode.** Triggered by either the CTA link (voluntary) or a `session_terminated: true` event (involuntary). Chat input hidden; email form visible; chat history stays visible above. No "back to chat" link during entry (per spec — back is for *after* a submission attempt).
  - **Email result mode.** After Send is clicked, the response surfaces inline (success-green or error-red). Form is hidden on success; kept visible + re-enabled on error so the visitor can fix the address and retry. A "← Back to chat" link appears here, but **only** if the session is not terminated — on a hard handoff the chat is over and there's no chat to go back to.
- Persistence is unchanged: a successful submission still sets `localStorage` so a reload of a terminated session shows the thanks state instead of the entry form again.
- Removed `.swwp-email-thanks` markup + the corresponding `showEmailForm()` method, replaced by `.swwp-email-message` (used for both success and error states) and three explicit mode methods: `showChatMode()`, `showEmailEntry()`, `showEmailResult(message, kind)`. New `applyInitialUi()` helper centralises the terminated + emailSubmitted decision tree.

### Added (GitHub-Releases auto-updater + release workflow)
- New `Github_Updater` class (`includes/class-github-updater.php`) — imported from the Quick 2FA plugin and refactored for Site Walker namespacing / identifiers. Hooks into WP's `pre_set_site_transient_update_plugins` and `plugins_api` so new GitHub releases surface as standard plugin updates through the native WP UI. Polls `api.github.com/repos/<repo>/releases/latest` with a 1-hour transient cache. Operators can disable per-site via the `site_walker_updater_enabled` filter (default `true`).
- New `.github/workflows/release.yml` — on every `v*.*.*` tag push, builds `site-walker-wp-<version>.zip` (versioned) and `site-walker-wp.zip` (stable filename), uploads both as workflow artifacts, and publishes them to GitHub Releases with auto-generated body text linking back to the CHANGELOG. The updater prefers the stable filename, falling back to any matching versioned zip if the stable one isn't present.
- New constants: `UPDATER_GITHUB_REPO` (= `headwalluk/site-walker-wp`), `UPDATER_CACHE_KEY`, `UPDATER_CACHE_TTL`.
- Tagging `v1.0.0` (and onward) is now the release mechanic — push the tag, the workflow does the rest. Existing 0.5.0 installs will pick up the update via the new updater on their next WP update cycle.

### Added (Hard-handoff email capture)
- **Email capture form on `session_terminated: true`.** When the upstream M20 per-session hard cap fires (or the new M23.5 `SW_SIM_HARD_HANDOFF_AFTER_USER_TURNS` sim hook trips), the widget now swaps the locked input row for an email-capture form. Visitor types their address; widget POSTs to the upstream `POST /sessions/visitor-email`; on `204` the form is replaced with a "Thanks — we'll be in touch." confirmation. Form state persists across reloads via a per-host `localStorage` flag so already-submitted sessions don't see the form again.
- Closes the hard-handoff loop end-to-end: the upstream's `handoff_webhook_url` (if configured) now fires with the visitor's email attached as the design intended.
- Client-side validation matches the upstream's loose check (`/@/` + `.` + ≤255 chars); upstream `400 validation_failed` surfaces inline so the visitor can fix and resubmit. `401 invalid_token` drops the cached session.
- **Pre-existing bug fix in `discardToken()`:** previously didn't reset `this.disabled` / `input.disabled` / `send.disabled`, so a visitor whose terminated session got 401-recycled would land on a fresh session with the input still locked. Now the method resets every flavour of UI lock state cleanly.

### Added (Origin-scoped chatbot selection)
- **Connection tab no longer lets the operator pick any chatbot in the account.** On admin-key save, the plugin now derives this site's canonical origin (scheme + lowercase host, default port stripped) from `site_url()`, fetches each chatbot's origin allowlist via the upstream `GET /admin/chatbots/{slug}/origins`, and auto-selects the one whose allowlist contains this URL. Removes a real footgun: an admin could previously pick a chatbot bound to a different site's origin and have admin-mode sessions silently route there (admin-mode bypasses origin checks upstream by design, so the misconfiguration didn't surface as an error).
- **Friendly no-match path.** Zero-match returns a specific `no_origin_match` error naming the expected origin and the exact `sw chatbot origins add` command the operator needs to run. The available chatbot list is surfaced so the operator can see what's in the account.
- **Test connection now verifies the origin link.** Previously it just confirmed the key authenticated and listed chatbots. Now it checks the saved chatbot's allowlist still contains this site's URL — surfaces drift if the allowlist gets edited upstream after setup.
- New `get_site_origin()` helper in `functions-private.php` centralises the origin normalisation, used by both the REST layer and the localised admin JS config.
- The Connection-tab UI now shows the expected origin as informational text so the operator knows what's being matched against. The "Active chatbot" row is informational only — no picker, no "Change" button.
- Removed `POST /wp-json/site-walker/v1/admin/connection/slug` (the picker endpoint) — no longer reachable from the UI.

### Added (M10 — Operational availability + Usage split, API v0.17 M21 catch-up)
- **Widget — `503 chatbot_closed` handling.** New `PROBE_CLOSED` probe state cached with `next_open_at` (or a 1h fallback when upstream gave no hint), so the widget stays hidden between probes and re-probes when the chatbot is due to open. Launcher-click during closed hours shows a polite "We're closed until …" message; sessions already minted aren't affected (gate is mint-only per the API contract).
- **Chatbot tab — `timezone` field.** Plain text input for the IANA identifier (e.g. `Europe/London`); a "Use this site's timezone" button is offered when the WP host's `wp_timezone_string()` returns an IANA name (rather than a UTC offset).
- **Chatbot tab — `availability` per-day grid.** Seven-day editor where each day holds 0..n `HH:MM–HH:MM` windows with `+` / `×` add / remove controls. Toggle between "Always open" (sends `null`) and "Per schedule" (sends `{schedule: {mon: [...], ...}}`). Client-side validates the same rules upstream enforces (`HH:MM` format, `close > open`, `24:00` as end-of-day).
- **Chatbot tab — `admin_session_budget_usd`.** Number input mirroring `session_budget_usd`; separate per-conversation cap for admin-mode sessions.
- **Usage tab — customer / admin split.** Existing rows expanded into three columns: Combined / Customer / Admin mode. Customer column is what counts toward the daily-cap budget; admin-mode column is excluded from that aggregate (matches the upstream segregation introduced in M21).
- **`Admin_REST` PATCH whitelist** extended to allow `timezone`, `availability`, `admin_session_budget_usd` through.

### Added (M9 — Session review)
- New **Sessions** wp-admin tab surfacing the upstream M22 review routes (`GET /admin/chatbots/{slug}/sessions[?page]`, `/sessions/{id}`, `/sessions/{id}/messages`). Paginated list of recent sessions with per-row aggregates (message count, tokens, cost estimate); `Admin mode` and `Terminated` pill badges where applicable; visitor email rendered as a `mailto:` link when the visitor volunteered one.
- Click-through detail view shows the full ordered message history of one session as alternating user / assistant bubbles. Messages from the assistant render through the same markdown formatter the front-end widget uses (bold, inline code, same-host + trusted-host link auto-linking), so the admin sees what the visitor saw.
- Hash-routed list ↔ detail: `#sessions` for the list, `#sessions/<id>` for one session. Browser back/forward work without a page reload. The tab nav extends to parse a sub-route from the hash and dispatch it to the active panel via `event.detail.sub` on `swwp:tab-activate`.
- Three new REST proxy routes in `Admin_REST` (`GET /chatbot/sessions`, `/sessions/{id}`, `/sessions/{id}/messages`), gated on `manage_options` + WP REST nonce like the rest. The visitor's session bearer token is intentionally absent from upstream responses, so there's no hijack risk in surfacing this through the admin proxy.

### Pending
- Conversation reset affordance (clear `localStorage`, mint fresh session).

## [0.5.0] - 2026-05-23

Catches the plugin up to two upstream API releases (v0.16 M20 budget caps + handoff, v0.17 M21 operational availability + admin-mode sessions) and ships the wp-admin surface operators have been waiting for. Three milestones rolled into one release because they share the same server-side proxy (`Admin_API_Client`), the same account admin key on the WP host, and the same end-to-end verification pass on devx 2026-05-23.

### Added — chat path resilience (M6, API v0.16 catch-up)
- Widget handles `402 budget_exhausted_daily` on probe (`GET /sessions/can-start`), mint (`POST /sessions`), and chat (`POST /chat`). A new `budget-exhausted` probe state is cached with an explicit "blocked until next UTC midnight" timestamp, so the widget stays hidden for the rest of the UTC day without burning HTTP traffic, and re-probes automatically the next morning. Mid-session, the input is disabled and a polite system message replaces the launcher-click status.
- Widget handles `403 geo_blocked` on `POST /sessions`, `POST /chat`, and `GET /messages`. The cached token is dropped and the widget hides itself entirely (mint-retry would also 403, per the API contract).
- Widget handles `200 { session_terminated: true }` on `POST /chat` (per-session hard cap). The assistant's final reply still renders, then the input row locks. A per-host `terminated` flag is persisted in `localStorage` so reloads honour the terminated state too; clearing the token (e.g. via 401 recovery) resets it.
- `:disabled` styling on `.swwp-input` and `.swwp-send` so the locked state is visually obvious.

### Added — Admin-area extension (M7)
- `Admin_API_Client` — thin PHP wrapper over `wp_remote_request` for the upstream `/admin/chatbots/*` surface. Returns a uniform `[ok, status, data | error, detail]` envelope; carries the bearer admin key on every request; never logs it.
- `Admin_REST` — WP REST routes under `/wp-json/site-walker/v1/admin/*` for the four new tabs. All gated on `manage_options` + WP REST cookie nonce. PATCH bodies whitelist their allowed fields so an admin can't piggy-back un-exposed fields like `model_slug` through the proxy.
- **Connection tab.** API URL, account admin key (password input on first save, masked-to-last-4 thereafter with Clear / Replace buttons), auto-discovered chatbot slug (single chatbot → auto-save; multi → picker dropdown; zero → friendly message). Test-connection button.
- **Chatbot tab.** Welcome message, persona, daily / session budget caps (USD), soft-handoff threshold percent. Fetch-on-tab-open, PATCH on save. Empty-string textarea fields are translated to `null` on the way to the API so a clear-and-save actually clears the upstream field.
- **Geo tab.** Mode (`allowall` / `blocklist` / `allowlist`) as a radio group; countries as a freeform textarea (comma / whitespace / newline separated, normalised to uppercase ISO 3166-1 alpha-2). A chip-input picker is on the v2 list.
- **Usage tab.** Read-only spend display with a `since` selector (1h / 24h / 7d / 30d / all time). Renders any operator-actionable warnings (e.g. under-counting on NULL-priced models) inline.

### Added — Admin mode (M8, API v0.17 M21)
- New WP REST route `POST /wp-json/site-walker/v1/admin-session` — manage_options + nonce gated, forwards to upstream `POST /admin/chatbots/{slug}/sessions` via `Admin_API_Client`. Returns the `{ session_token, welcome_message, is_admin_mode: true }` envelope unchanged.
- Widget container carries `data-is-logged-in="1"` when rendered for a logged-in `manage_options` user; `window.siteWalkerWP.adminSession` (admin-session URL + nonce) is localised only in that case.
- Widget `boot()` and `ensureSession()` branch on admin mode: skip the probe (upstream skips every gate the probe tests for), mint via the WP backend on first launcher click, cache the admin token under a separate `localStorage` key (`site-walker-wp:<host>:admin-session-token`) so admin and customer sessions on the same browser don't clobber each other.
- The upstream's `**Admin mode**\n\n` welcome-message prefix renders as bold via our existing assistant-message markdown formatter — visible in-message cue, no new widget chrome.

### Added — UX polish
- **Trusted external hosts allowlist** on the Widget tab. URLs in assistant replies pointing to operator-curated hosts become clickable; same-site URLs are always clickable; everything else stays as plain text. External links get `target="_blank" rel="noopener noreferrer nofollow"`. Stored locally as a wp_option; exact host match only.
- Input textarea grows from one row to two so longer messages feel less cramped.

### Changed
- General tab renamed to **Widget** — the Settings-API-driven knobs that remain there are pure widget render options. The API URL field moved to the new Connection tab and is now REST-managed (not registered via the Settings API).
- Tab page restructured to host two tab families: Settings-API-driven (Widget, Appearance) wrapped in the shared `options.php` form, and REST-driven (Connection, Chatbot, Geo, Usage) each loading from its own partial under `admin-templates/tabs/`. The shared submit button only shows when a Settings-API tab is active.
- Tab switching dispatches a `swwp:tab-activate` custom event so each REST-driven tab can refetch on display.
- `probeApi()` returns a `{ state, until? }` object instead of a boolean, so the boot path can distinguish "budget-exhausted" from generic "unavailable" without losing the explicit reset time.

### Fixed
- Upstream `GET /admin/chatbots` returns `{chatbots: [...]}`, not a bare array — the Connection tab's chatbot picker now unwraps the envelope correctly. Added a `toArray()` defensive guard on the JS side so a malformed payload degrades to "0 chatbots visible" rather than a `TypeError`.

### Docs
- Terminology sweep: "website(s)" → "chatbot(s)" in `README.md` and the dev-notes planning docs, matching the upstream rename in API v0.16.
- New design doc: `dev-notes/40-admin-area-extension.md` covers the M7 medium-cut scope, architecture, storage model, auto-discover flow, error model, and what's intentionally deferred.
- New `CLAUDE.md` at the plugin root capturing the `widget.js` NUL-byte sentinel gotcha (and why git treats the file as binary) so future editors don't repeat the diagnostic.
- M6 / M7 / M8 milestones recorded in `dev-notes/00-project-tracker.md`.

### Notes
- **What's deliberately out of scope** (each tracked under "Later" in the project tracker): operational availability handling in the widget (`503 chatbot_closed`) + the corresponding Chatbot / Usage tab fields, origins management UI, BYO provider API key setting, model swap, system blocks CRUD, soft-handoff email capture form. Each was excluded for a stated reason rather than time pressure.
- **Existing settings are preserved.** General → Widget is a UI-label rename only; option keys are unchanged, so existing installs keep their tuned values across the upgrade.

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
