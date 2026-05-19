# Operator Controls (Planning / Brainstorm)

**Status:** Brainstorm — for review and trimming
**Last Updated:** 2026-05-19
**Author:** Paul (drafted by assistant)

A site operator's day-to-day worries are not "does the chatbot work" but "is it going to cost me money / cause embarrassment / break something." This doc enumerates the controls they might want, where each one lives, and ends with a recommended v1 cut so we don't ship a wall of toggles nobody understands.

---

## Framing: where does a control live?

Most controls can be enforced in more than one place. Each layer has trade-offs:

| Layer                | Cost     | Bypassable?            | Latency   | Good for                                       |
| -------------------- | -------- | ---------------------- | --------- | ---------------------------------------------- |
| Widget (JS)          | Free     | Yes (devtools)         | None      | UX choices — hide widget, route picker         |
| Plugin (PHP)         | Cheap    | No, server-rendered    | Tiny      | Page rules, role rules, cookie consent gate    |
| Upstream API         | Per call | No                     | Network   | Canonical limits — budget, content moderation  |

**Rule of thumb:** put enforcement at the cheapest layer where it can't be bypassed. Budget caps must live on the API (a bypassed cap costs real money). Operational hours can live in PHP (a bypassed schedule just means a determined user can poke the widget when it would otherwise hide — fine). Welcome text picker can live in JS.

For high-stakes controls, defend in depth: cheap layer for the common case, canonical layer as the actual gate.

---

## 1. Daily LLM budget caps

The single most important control. Without it, a runaway loop or a hostile visitor can cost real money overnight.

**Layers:**
- **Hard cap (API).** API tracks per-website spend. When the cap is reached, all chat completions return 402 `budget_exhausted`. Plugin's widget hides itself.
- **Soft warn (plugin).** Admin email at 80% of daily cap; admin notice in WP dashboard.

**Knobs:**
- `daily_cap_usd` — hard ceiling.
- `monthly_cap_usd` — secondary ceiling.
- `per_visitor_cap` — token or message ceiling per session.
- `behavior_on_exhaust` — `hide_widget` | `show_offline_message` | `queue_for_human`.

**Plugin UI:** new "Limits" sub-tab. Three numeric inputs + a select for behaviour. A read-only "spend today: $X.XX of $Y.YY" indicator (cached, 5-min transient, refreshed via the new admin Api_Client from `20-system-blocks-api.md`).

**Open questions:**
- Currency: API bills in one currency; do we render in the merchant's currency or the API's billing currency? Recommend the API's, with a clear label.
- Where does the visitor-cap get enforced — when the bot starts responding (cheap) or per-token (slightly fairer to long answers)? Recommend per-message; per-token is overkill.

**Complexity:** Medium. Requires API-side spend tracking (probably already exists; if not, biggest blocker).

---

## 2. Operational hours

"Evenings Mon–Fri, all day weekends" is a real schedule. Two reasons an operator wants this:

1. They don't want the bot answering off-hours when they can't intervene if it goes wrong.
2. They want the bot to be a complement to live-chat staff during business hours, taking over outside them — or vice versa.

**Layers:**
- **Widget (JS).** Hides the launcher when out of hours. Schedule shipped in the bootstrap config alongside `apiUrl`.
- **Plugin (PHP).** Same schedule check on `wp_footer` — don't even render the widget container.
- **API (defence-in-depth).** Optional: API can reject session-mint with `outside_hours` if the merchant prefers a hard gate. Probably overkill — JS+PHP is enough.

**Data model:**

```json
{
  "timezone": "Europe/London",
  "schedule": [
    { "day": "mon", "windows": [["18:00", "23:00"]] },
    { "day": "tue", "windows": [["18:00", "23:00"]] },
    { "day": "wed", "windows": [["18:00", "23:00"]] },
    { "day": "thu", "windows": [["18:00", "23:00"]] },
    { "day": "fri", "windows": [["18:00", "23:30"]] },
    { "day": "sat", "windows": [["00:00", "23:59"]] },
    { "day": "sun", "windows": [["00:00", "23:59"]] }
  ],
  "holidays": [
    { "date": "2026-12-25", "open": false },
    { "date": "2026-12-26", "open": false }
  ],
  "out_of_hours_behavior": "hide" 
}
```

`out_of_hours_behavior`: `hide` | `show_message` (renders the launcher but the panel shows "We're closed — back at 6pm" and disables input) | `take_a_message` (collects email + message for follow-up).

**Plugin UI:** "Schedule" sub-tab. Per-day rows with start/end pickers, "always open" / "always closed" shortcuts, "copy weekdays to weekend" helper. Holiday list with a date picker. Timezone defaults to the WP site timezone.

**Open questions:**
- Multiple windows per day (e.g. lunch break)? Schema supports it; UI complicates. Recommend: support in data, UI ships single-window per day with "advanced mode" for multi.
- "Take a message" mode requires a message store — either WP custom table or upstream. Defer the mode itself or build the table now? Recommend defer.
- DST handling. PHP's `DateTimeZone` does this correctly; just don't store times as fixed offsets.

**Complexity:** Low (hide/show), Medium (take-a-message).

---

## 3. Country gating

GDPR avoidance, regional support, or just "we only ship to the UK" — all valid reasons.

**Layers:**
- **Plugin (PHP, server-rendered).** Resolve country before rendering the widget container. Use Cloudflare's `CF-IPCountry` header if present (free, accurate, no library). Fall back to a MaxMind GeoIP2 lookup if installed; fall back to "show widget" if neither is available.
- **API (defence-in-depth).** Reject session-mint with `country_not_allowed` based on the visitor IP, in case the WP front-end caches across countries.

**Knobs:**
- `mode`: `allow_list` | `block_list` | `off`.
- `countries`: array of ISO-3166 two-letter codes.
- `behavior_on_block`: `hide` | `show_message` (e.g. "Chat available in EU only").
- `fallback_when_unknown`: `show` | `hide`. Recommend `show` — better UX than punishing visitors whose country we can't detect.

**Page caching interaction:** **important.** If the front page is page-cached, the widget container is also cached, so country gating server-side fails — every visitor sees the cached result for the first visitor's country. Options:

1. **Render the widget unconditionally, gate in JS** using a `/wp-json/site-walker-wp/v1/locale-gate` AJAX call that bypasses page cache (POST, or `nocache_headers()`).
2. **Use Cache-Vary on `CF-IPCountry`** — only works on supporting CDNs.
3. **Use ESI / edge-side fragments** — over-engineering.

Recommend option 1. Page caching is the dominant deployment so the server-render approach is unsafe by default.

**Open questions:**
- VPN false positives — should we accept them as the cost of the feature? Yes.
- Sub-country granularity (region / state)? US-specific use cases exist (CCPA in California). Defer to v2.

**Complexity:** Low if Cloudflare is in front. Medium with MaxMind. The page-cache interaction is the real complexity.

---

## 4. Languages

The chatbot speaks whatever the LLM can speak, so "language support" is really three separate features:

### 4a. Visitor-language UI
Welcome text, placeholder text, the "Open chat" aria-label — should match the visitor. Today these come from the plugin's General settings as single strings.

**Plan:** allow each text field to be a per-locale map.

```json
{
  "headerText": {
    "default": "Chat",
    "fr": "Discussion",
    "de": "Chat"
  }
}
```

Detection: visitor's `navigator.language` in JS, with `Accept-Language` as fallback. Falls through to `default` if no match.

### 4b. Per-language enable/disable
"This site supports English and German chats; refuse anything else." Implemented as a system block: "If the user writes in a language other than English or German, respond in English with: 'Sorry, this chat is only available in English and German.'"

→ **No new operator control needed.** This belongs in the system-blocks UI.

### 4c. WPML / Polylang integration
If the site is multilingual, the operator might want different system prompts per language (different store policies in different markets). Pairs with the integrations framework — a `WPML` or `Polylang` integration that exposes per-language system blocks.

**Defer to v2.**

**Open questions:**
- Do we ship translations for the plugin's UI strings (Open chat, Close chat, etc.) using a `.po`/`.mo` workflow? Yes — that's already wired via `load_plugin_textdomain( 'site-walker', … )`. We just need to start shipping translation files.
- RTL languages — anything to do? CSS `dir="auto"` on the message bubbles should be enough. Worth a separate verification step.

**Complexity:** Low for 4a (per-locale text), Low for 4b (it's just a block), Medium for 4c.

---

## 5. Additional controls worth considering

### 5a. Page / context rules

"Don't show the widget on `/checkout`. Always show it on `/contact`. Use a different welcome message on product pages."

**Plan:** plugin-side rules engine, evaluated server-side at `wp_footer`:

```
WooCommerce Cart    →  hide
WooCommerce Checkout →  hide
Front page          →  show
Product pages       →  show + welcome "Have a question about this product?"
Everywhere else     →  show
```

Stored as a small ordered ruleset (first match wins). UI: a table of rules with conditions (URL pattern, post type, WC page, role) + actions (show / hide / set welcome).

**v1?** Just "hide on checkout" — single checkbox.

### 5b. Audience rules (logged-in / role-based)

- Hide widget from logged-in users with `manage_options` (admins don't need it).
- Different welcome for `customer` role.
- Refuse chats from `subscriber`-or-below if the bot is for guests only.

**v1?** Single checkbox "Hide for logged-in admins".

### 5c. Cookie consent gate

In jurisdictions where the API call counts as a third-party connection, the chatbot must not load until consent. Integrate with common consent banners (Cookie Notice, CookieYes, Complianz) via a documented JS hook:

```js
window.siteWalkerWP_consentGiven();  // call from your consent banner
```

If a consent gate is enabled and `consentGiven()` hasn't been called, the widget doesn't load.

**v1?** Yes, but the simplest possible form — a single "Require consent" checkbox and a documented JS API.

### 5d. Visitor message rate limit

Per-visitor message rate (e.g. "no more than 10 messages per 5 minutes from one IP"). Lives on API side, but plugin should surface the setting.

**v1?** Yes, via the API (pairs with M2 abuse-resistance).

### 5e. "Don't store" / ephemeral mode

For privacy-conscious operators: tell the API not to store conversation transcripts. Implies the widget rehydration flow gets less helpful (no history reload).

**v1?** Defer. Single bool on the API side once supported.

### 5f. Admin alerting

Email or webhook on:
- Daily budget threshold reached.
- API key invalid / rejected.
- Spike in error rate.
- Visitor flagged words (configurable list).

**v1?** Budget threshold only.

### 5g. Operator analytics dashboard

The flip side of the Independent Analytics integration. Operator wants to see:
- Conversations per day.
- Drop-off points.
- "Bot couldn't answer" rate.
- Token spend trend.

**v1?** A single "Activity" sub-tab with three numbers (conversations today, spend today, last error). Anything more is its own milestone.

### 5h. Lead capture / human handoff

Capture name+email at conversation start (optional); push to admin email or to a WooCommerce-customer record when the bot detects buying intent.

**v1?** Defer entirely. Big feature with its own UX surface.

### 5i. Branding / "Powered by"

- Custom assistant name + avatar.
- "Powered by Site Walker" footer (toggleable for paid tiers; mandatory on free?).

**v1?** Already partially exists via the Appearance tab. The branding toggle is a business decision, not a tech one.

---

## Where these settings live (storage)

All operator-control values are plugin options (`wp_options`), prefixed `site_walker_wp_*`. Three categories:

1. **Local-only** (plugin enforces, doesn't sync): page rules, hide-on-checkout, consent gate config, visitor-side text variants, schedule (if we enforce purely client-side).
2. **Pushed to API** (canonical on the API side, plugin is editor): budget caps, country gating (server-side mirror), per-visitor rate limit, ephemeral-mode flag.
3. **Mirrored** (we keep a copy locally for the admin UI but the API is authoritative): spend stats, recent errors.

We need a `Plugin_To_Api_Sync` job (probably extending the same admin-key `Api_Client` from [`20-system-blocks-api.md`](20-system-blocks-api.md)) that pushes category-2 settings to the API on save. Same dirty-flag-then-cron pattern as integration blocks.

---

## Recommended v1 cut

After ship of M2 (API key + abuse hardening), the first operator-control milestone should be narrow. My pick:

| Feature                      | Reasoning                                                      |
| ---------------------------- | -------------------------------------------------------------- |
| Daily budget cap (API side)  | Existential; without it the bot can ruin someone's week.       |
| Operational hours (simple)   | Most-requested, low complexity.                                |
| Country gating               | Compliance + cost control. Worth the page-cache complexity.    |
| Per-language welcome text    | Low effort, big UX win for multilingual sites.                 |
| Hide on WC checkout          | One checkbox; saves money + reduces buyer friction.            |
| Cookie-consent gate          | Compliance-critical in EU. Low surface.                        |
| Admin email at budget 80%    | Pairs with the budget cap — won't ship one without the other.  |

Defer everything else (rules engine, role rules, lead capture, analytics, ephemeral mode) to v2.

**Why this cut?** Each item above is something a merchant will plausibly turn down a paid plan over. Each deferred item is a "nice to have." Ship the must-haves, then watch which deferred items real users ask for.

---

## Open questions for review

1. **Operational hours — JS-only enforcement or also server-side?** I leaned toward both. JS-only is simpler and the bypass cost (a curious visitor pokes the widget at 3am) is low. Worth confirming you'd accept that.
2. **Country gating — Cloudflare-only or also MaxMind/IP2Location?** Cloudflare covers a huge fraction of WP sites. Adding MaxMind doubles the doc and adds a binary dependency. Recommend Cloudflare-only at first, MaxMind opt-in later.
3. **Budget caps — who sees the spend number?** Admin only, or every user with `edit_posts`? Recommend `manage_options` (matches the plugin's existing capability).
4. **Out-of-hours "take a message" mode — build now or defer?** I leaned defer. Worth your call.
5. **Should operator-controls live on the same Settings page (new sub-tabs) or get a separate top-level admin menu?** The current tab list is "General / Appearance / (Integrations) / (Context)". Adding "Limits / Schedule / Geo / Languages" makes it eight tabs. Maybe time for a sub-menu structure. Recommend: keep flat for v1, restructure when we hit ten tabs.
6. **Schedule UI — week grid vs per-day rows?** Per-day rows are simpler; week grid (drag to select) is fancier but a real engineering project. Recommend rows.
7. **Defence-in-depth for budget — should the plugin **also** track an approximate local spend so it can refuse to call the API at all when the cap is reached?** Reduces API load when over budget. Adds complexity. Recommend: not for v1; the API's 402 response is enough.

---

## What I didn't cover (deliberately)

- **Content moderation knobs** (profanity filter, topic blocklist) — these are really system-prompt content, not operator controls. Belong in [`20-system-blocks-api.md`](20-system-blocks-api.md).
- **A/B testing different prompts** — interesting but its own milestone.
- **Multi-site (network) controls** — defer until anyone asks.
- **Theme integration / appearance overrides** — already in the Appearance tab and M3 polish.
