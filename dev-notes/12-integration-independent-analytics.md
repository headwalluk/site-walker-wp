# Independent Analytics Integration (Planning)

**Status:** Proposal — for review
**Last Updated:** 2026-05-19
**Author:** Paul (drafted by assistant)
**Depends on:** [`10-integrations-architecture.md`](10-integrations-architecture.md)

---

## Use case

When a visitor asks the chatbot "where can I learn more about X?" or "what's popular?" or "where do most people start?", the bot should be able to recommend pages **the visitors of this site actually engage with**, not pages the LLM guesses might exist.

Independent Analytics is a privacy-friendly, self-hosted WP analytics plugin. Because the data is local, we can read it cheaply and without sending anything off-site.

---

## Assumptions to verify before building

I'm working from general knowledge of the plugin — these need to be checked against the actually-installed version:

- **Database tables:** there's an internal table (probably under the `wp_independent_analytics_*` prefix) storing pageviews, sessions, and durations.
- **Public PHP API:** the plugin likely exposes a query class or static methods we can call rather than writing raw SQL. Prefer that over direct DB queries — if they refactor their schema we break otherwise.
- **Page-level metrics available:** views, unique visitors, average time on page, bounce rate. Need to confirm which are stored and which are computed.

**Action item for the implementation phase:** read the plugin's `class-iawp-query.php` (or equivalent) and document the actual surface in this file before writing any integration code. Linking to a hard-coded table is a maintenance trap.

---

## What we expose

| Concept              | Description                                                  |
| -------------------- | ------------------------------------------------------------ |
| Popular pages        | Top N by pageviews, period-windowed (7d / 30d / 90d).        |
| Engaging pages       | Top N by average time-on-page, with a min-view threshold.    |
| Newly-trending pages | Pages whose recent-period views are >X% above their baseline. |
| Topic clusters       | Pages grouped by category / tag, ranked by aggregate views.  |

We do **not** expose:

- Visitor identifiers, IPs, sessions, or referrers — even though they're available locally, the chatbot shouldn't see them.
- Date-of-last-visit per page if it could reveal admin activity.
- Search queries from the on-site search (separate concern).

---

## REST routes

All under `site-walker/v1/integrations/independent-analytics/`.

```
GET /summary
GET /popular?period=30d&limit=20
GET /engaging?period=30d&limit=20&min_views=50
GET /trending?period=7d&baseline=30d&limit=10
GET /by-category?period=30d
```

### `GET /popular` example response

```json
{
  "period": "30d",
  "generated_at": "2026-05-19T08:14:00Z",
  "items": [
    {
      "url": "https://devx.headwall.tech/demos/cubit-calculator/",
      "title": "Cubit Calculator",
      "views": 4823,
      "avg_time_seconds": 312
    }
  ],
  "schema": "site-walker-v1"
}
```

### `GET /summary` example response

A compact overview suited for direct use as a system-block body:

```json
{
  "period": "30d",
  "top_pages": [
    { "title": "Cubit Calculator", "url": "...", "views": 4823 },
    { "title": "Paisley's Number Sequence", "url": "...", "views": 1290 }
  ],
  "top_categories": [
    { "name": "Demos", "views": 8120 },
    { "name": "Tutorials", "views": 3402 }
  ]
}
```

---

## Suggested system blocks

1. **Most-viewed content (last 30 days)** — list of top 10 pages with titles + URLs (URL-only or url+title; admin choice).
2. **Most-engaged content (last 30 days)** — top 10 by time-on-page, gated by min 50 views.
3. **Where to start** — derived: the highest-views page in the "Getting started" or "Demos" category, if any.

All three are **periodically refreshed** — see sync section.

---

## Privacy considerations

The point of Independent Analytics is that data stays local. Forwarding aggregates to the upstream API isn't a regression — the API only learns "page X is popular here" and never sees individual visitor data. But two things to lock down:

1. **Truncate to titles + URLs and counts.** Never include any visitor-side dimension (country, device, referrer).
2. **Round counts.** Send buckets like `< 100`, `100-1k`, `1k-10k`, `> 10k` rather than exact view counts. The model doesn't need precision and exact counts can reveal site scale that the merchant might consider commercially sensitive.

**Open question:** bucket vs exact? Bucketed is safer; exact is more useful for the model's ranking quality. Recommend bucketed by default, exact behind an opt-in toggle.

---

## Cache strategy

Independent Analytics queries can be slow if the table is large and not well-indexed. Cache aggressively:

| Route       | TTL    | Bust on                                       |
| ----------- | ------ | --------------------------------------------- |
| `/summary`  | 6 hours | Manual refresh button only                   |
| `/popular`  | 6 hours | Same                                          |
| `/engaging` | 6 hours | Same                                          |
| `/trending` | 1 hour  | Same                                          |

We do NOT invalidate on every pageview — that would defeat the cache. Refresh runs on wp-cron once every six hours; admin can force-refresh.

---

## Query budget

Set a hard 500ms ceiling on aggregation queries. If we hit it:
- Return whatever rows we have so far + `truncated: true`.
- Log a warning.
- Cache the truncated result with a shorter TTL (15min) so we retry sooner.

This protects the upstream API from a slow shop hanging the chat flow.

---

## What about other analytics plugins?

The architecture is per-plugin (one integration = one target). If we later want Google Analytics, Matomo, or Burst Statistics, each gets its own integration folder. **No abstraction layer above this.** Premature; each plugin's data model is too different.

---

## Open questions for review

1. **Bucketed vs exact view counts** — see Privacy section. Recommend bucketed default.
2. **Which periods?** Above I assumed 7 / 30 / 90 days. Are any of those redundant?
3. **Should "popular" include behind-login content?** Probably exclude `private` and `password`-protected posts by default.
4. **Trending detection** — the simple "recent vs baseline" formula is naive. A page newly published has no baseline. Worth a small bit of statistical sanity (Bayesian prior, min-sample-size) before declaring something "trending".
5. **What if Independent Analytics isn't installed but Burst / GA is?** Out of scope here, but worth flagging — the bot's answers shouldn't depend on a specific plugin being chosen.
