# Admin-area extension (M7)

The wp-admin surface that hangs off the upstream `/admin/chatbots/*` API. Where M6 just made the chat path resilient to the new denial vocabulary, M7 gives the merchant the knobs that produce those denials — budget caps, geo policy, welcome message, persona — without making them shell into the host and run `./bin/sw`.

## Scope (medium cut)

In:
- **Connection.** Store the operator's account admin key (one wp_option, autoload=no, masked in the UI). Auto-discover the chatbot slug after key save by listing `GET /admin/chatbots` and either picking the singleton or showing a dropdown.
- **Chatbot.** Welcome message, persona, daily + per-session USD budget caps, soft-handoff threshold percent. Reads via `GET /admin/chatbots/{slug}`, writes via `PATCH`.
- **Geo.** Mode (`allowall` / `blocklist` / `allowlist`) + countries (ISO 3166-1 alpha-2). Reads + writes via `GET` / `PATCH /admin/chatbots/{slug}/geo`.
- **Usage.** Read-only token + cost totals with a `since` selector (1h / 24h / 7d / all-time). `GET /admin/chatbots/{slug}/usage?since=…`.

Out (deferred to later milestones):
- Origins management (`POST/DELETE /admin/chatbots/{slug}/origins`). Operator still uses `./bin/sw chatbot origins add`. WP merchant who needs to swap their site host wouldn't typically be doing it from wp-admin anyway.
- Provider API key setting (`PATCH /admin/chatbots/{slug}/api-key`). Bringing a metered LLM key into the WP admin UI is a credential-handling escalation that wants its own design pass; for now, operators set it via `./bin/sw chatbot set-api-key`.
- System blocks CRUD (`/admin/chatbots/{slug}/blocks`). Genuinely needs its own UX (multi-block list, edit-as-markdown, preview); see `20-system-blocks-api.md`.
- Model swap (`model_slug` in PATCH). Risky knob for a non-technical merchant — wrong model can break replies entirely. Operators use `./bin/sw chatbot set-model` for now.
- Account-level surface (`/admin/accounts/*`). That's provisioning-key territory and lives in `site-walker-for-woo`, not here.

## Architecture

Two layers between wp-admin and the upstream API:

```
wp-admin form/JS
    │  HTTP (same origin, nonce-guarded)
    ▼
WP REST routes under /wp-json/site-walker/v1/admin/*
    │  PHP (manage_options + nonce gate)
    ▼
Admin_API_Client (wp_remote_request, attaches Bearer)
    │  HTTPS
    ▼
Site Walker API /admin/chatbots/*
```

**Why server-side, not browser-to-API.** The chat widget is correctly browser-to-API because the bearer it carries is a per-visitor session token — losing it just loses that visitor's session. The account admin key is different: it grants full control over every chatbot in the account. It must never be shippable to a visitor's browser, and "only ships to admins' browsers" isn't tight enough either (browser extensions, page caches, support sessions, screenshares). Server-side keeps it in the wp_options table and the PHP process, where the same threat model that protects every other WP secret applies.

**Why a WP REST layer, not direct PHP form posts.** Two reasons. First, several flows (auto-discover, connection-test, usage refresh) want JS-driven UI without a page reload. Second, the per-tab "save" buttons are easier to wire to REST PATCHes than to per-tab admin-post.php handlers, and the error model (per-field validation, partial updates) lands more naturally as JSON than as form redirects with notice flashes.

## Storage model

Only two values live in wp_options. Everything else lives upstream and is fetched on tab load.

| Option key                          | What                                                                                | Notes                                                                                              |
|-------------------------------------|-------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------|
| `site_walker_wp_admin_key`          | Account admin key (`sw_…`).                                                          | `autoload=no`. Masked in UI to last 4 chars after save. Plain text in DB — see "Encryption" below. |
| `site_walker_wp_chatbot_slug`       | Slug of the chatbot this WP install is configured against (auto-discovered or chosen). | `autoload=no`. Cleared whenever the admin key changes.                                              |

Welcome message, persona, budget caps, geo, usage — none of these are stored locally. Treating wp_options as cache would introduce drift the moment the operator runs `./bin/sw chatbot set-budget` outside WP. Fetch-on-load + write-on-save against the API is the source-of-truth contract; the cost is one HTTP round-trip per tab open, which on a settings page is fine.

**Encryption.** Not in v1. The threat model: the wp_options table is already readable only by DB-credentialed processes; anyone who can read it can already pull every WP credential including auth keys. Encrypting the admin key with a key that has to live somewhere reachable by the same PHP process doesn't change the threat surface meaningfully. If a future audit demands at-rest encryption, the natural mechanism is `wp_options` → `wp_options_meta` shimmed with `AUTH_KEY`-derived AES-256-GCM, mirroring the upstream pattern.

## Auto-discover flow

When the admin saves a new admin key:

1. POST to our REST endpoint `POST /wp-json/site-walker/v1/admin/connection`.
2. Server validates the key shape (`sw_…`, ~46 chars).
3. Server calls `GET /admin/chatbots` upstream with the new key.
4. Response handled:
   - `0 chatbots` → return `{ error: 'no_chatbots' }`. UI surfaces "Key works but no chatbots found — create one via `./bin/sw chatbot create`."
   - `1 chatbot` → save the slug; return `{ slug, name }`. UI shows "Connected to *chatbot-name*."
   - `>1 chatbots` → return `{ chatbots: [{ slug, name }, …] }`. UI shows a dropdown; second POST `POST /wp-json/site-walker/v1/admin/connection/slug` saves the chosen slug.
5. On `401 bearer_invalid` upstream → return `{ error: 'bearer_invalid' }`. UI surfaces "Admin key not recognised."

A "Test connection" button on the Connection tab repeats step 3 against the stored key without changing anything — useful for diagnosing "settings are saved but the API has rotated my key under me."

## Tab structure

The existing settings page has two tabs (General, Appearance). M7 adds four more, and folds the existing API URL field into a renamed first tab.

| Tab          | Source of truth | What lives there                                                                          |
|--------------|----------------|--------------------------------------------------------------------------------------------|
| Connection   | wp_options      | API server URL (was in General), admin key, chatbot slug, test-connection button.          |
| Appearance   | wp_options      | (Unchanged.)                                                                                |
| Widget       | wp_options      | Renamed from "General" minus the API URL. The widget-only knobs: enabled flag, header text, placeholder. |
| Chatbot      | API             | Welcome message, persona, daily / session budget caps, handoff threshold percent.          |
| Geo          | API             | Mode (allowall / blocklist / allowlist) + countries multi-select.                          |
| Usage        | API (read-only) | Message count, tokens, cost; `since` selector.                                              |

Tabs are gated: if the admin key isn't saved + valid, the API-backed tabs (Chatbot / Geo / Usage) render a stub pointing to Connection.

## Error model

Upstream API uses `{ error: '<code>', detail?: { ... } }`. The PHP client returns either `[ 'ok' => true, 'data' => <decoded> ]` or `[ 'ok' => false, 'status' => <int>, 'error' => '<code>', 'detail' => <array|null> ]`. The REST layer passes the same envelope through to the browser. The admin JS maps the codes to friendly copy:

| Upstream code          | UI message                                                                       |
|------------------------|-----------------------------------------------------------------------------------|
| `bearer_required`      | "No admin key configured."                                                        |
| `bearer_invalid`       | "Admin key not recognised. Check that it's the right key and hasn't been revoked." |
| `wrong_scope`          | "This is a provisioning key, not an account admin key. Mint one with `./bin/sw account add-admin-key`." |
| `not_found`            | "No chatbot found with this slug. Pick a different one from Connection."         |
| `validation_failed`    | Fall through to `detail.message` from the API; otherwise generic "Invalid value." |
| `conflict`             | Generic "Conflict — that value collides with an existing record."                |
| (HTTP transport error) | "Couldn't reach the API server. Check the URL on Connection."                    |

## What this is not

- **Not provisioning.** Creating accounts and minting admin keys stays in `./bin/sw` (or eventually `site-walker-for-woo`).
- **Not a billing surface.** Usage tab is read-only diagnostics; billing happens in WooCommerce at `site-walker.net`.
- **Not a per-visitor analytics view.** Sessions / messages browsing is a `./bin/sw sessions` thing today. If a real customer asks for in-WP visibility, that's a separate milestone with its own privacy story.

## Open questions for review

1. **Should the admin key field accept a paste-and-reveal flow?** Once stored we mask it; on first paste, do we show it briefly so the operator can verify? Mirror the upstream behaviour of "raw key shown exactly once" — minting on the API side does this, but our store-and-reuse case is different.
2. **Single Save vs per-tab Save?** Current draft: per-tab Save (clearer error scope; matches the API's per-route PATCH). Risk: an operator changes welcome on one tab, switches to Geo without saving, loses the welcome edit. Mitigation: dirty-form warning on tab switch.
3. **Usage `since` defaults.** Default to 24h? Sticky last-choice in wp_user_meta? Probably 24h-fixed for v1; sticky is a polish item.
4. **What happens if the API URL is changed after a key is saved?** The key is account-scoped to a *specific* API instance. Changing the URL should probably clear both the key and the slug — flag this in the Connection tab's API URL `description`, and wipe on change.
5. **Country picker UX.** A 250-row multi-select is awful. Use a tag-input with autocomplete? Top-10 most-common + "more…" expander? v1 can ship a textarea of comma-separated codes; v2 picks something nicer.
