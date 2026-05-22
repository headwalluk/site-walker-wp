# Integrations Architecture (Planning)

**Status:** Proposal — for review
**Last Updated:** 2026-05-19
**Author:** Paul (drafted by assistant)
**Depends on:** M2 (API key support) — integrations expose authenticated REST routes; we don't want to ship those without auth.

---

## Why integrations

The upstream Site Walker API doesn't need to scrape the site to know what we sell, what's popular, or what our policies are. This plugin runs inside WordPress and can hand the API curated, accurate data **directly** — better than scraping, and with structure the API can summarise into system context blocks.

An "integration" is a small bridge between this plugin and another WordPress plugin (e.g. WooCommerce, Independent Analytics). Each integration:

1. **Detects** whether the target plugin is active.
2. **Exposes** read-only REST routes for the API to pull data from.
3. **Generates** suggested system context blocks (see [`20-system-blocks-api.md`](20-system-blocks-api.md)) that the admin can enable / tweak.

---

## Directory layout

```
integrations/
  woocommerce/
    woocommerce.php          # entry point - bootstraps the integration
    class-integration.php    # main class
    class-rest-controller.php
    class-blocks.php         # block generators
  independent-analytics/
    independent-analytics.php
    class-integration.php
    ...
```

The entry-point filename matches the folder name. The plugin's main bootstrap (`site-walker-wp.php`) explicitly `require_once`s each entry point — we are NOT going to auto-scan the directory.

**Why explicit over auto-load:** auto-loading any directory under `integrations/` makes it easy for an experiment to ship to production by accident. Explicit registration is one extra line per integration and worth the trade.

---

## Integration contract

Each integration has one main class extending an abstract base:

```php
namespace Site_Walker\Integrations;

abstract class Abstract_Integration {
    abstract public function slug(): string;          // e.g. 'woocommerce'
    abstract public function is_available(): bool;    // target plugin active + compatible
    abstract public function register(): void;        // hook registration
    abstract public function generate_blocks(): array; // suggested system blocks
}
```

The integration **only** registers its hooks if `is_available()` returns `true`. If the target plugin is later deactivated, hooks remain registered (harmless), but REST routes should return a structured `503` payload:

```json
{ "error": "integration_unavailable", "integration": "woocommerce", "reason": "WooCommerce not active" }
```

---

## Registry

A simple `Site_Walker\Integrations\Registry` (singleton-ish via the main `Plugin` object) holds the active integrations so the admin UI and the system-blocks generator can iterate over them.

```php
$registry = stwlk_get_site_walker()->get_integrations();
foreach ( $registry->active() as $integration ) {
    $blocks = $integration->generate_blocks();
    // ...
}
```

---

## REST surface

All integration routes live under one namespace:

```
/wp-json/site-walker/v1/integrations/{slug}/...
```

Examples:
- `GET /wp-json/site-walker/v1/integrations/woocommerce/products`
- `GET /wp-json/site-walker/v1/integrations/independent-analytics/popular`

### Auth

Routes are authenticated via a shared API key, sent as `X-Site-Walker-Key: <key>` or `Authorization: Bearer <key>`. The key is provisioned once on the API side (per-chatbot or per-account, depending on which surface ends up driving this — likely the account admin key, since `/admin/chatbots/*` is the natural destination for any plugin → API back-channel) and entered into the plugin's General settings tab.

This pairs naturally with the **M2 — Abuse-resistance** milestone and the post-M6 admin-area extension, which both call for an API key flow. The account admin key minted via `/admin/accounts/{id}/keys` is the likely candidate.

**Open question:** one key or two? Two is safer (visitor-side leaks can't be used to drain integration data), but it doubles the admin-side setup friction. Recommend two but make the second one optional — falls back to the visitor key if unset.

### Schema discipline

Each route documents its response shape in a `@return` PHPDoc block on the controller method, and we pin it to a `site-walker-v1` schema label. Breaking changes bump to `/v2/`.

### Caching

Integration data can be expensive to compute (full product walk, analytics aggregation). Each controller:
- Uses `get_transient()` keyed by `site_walker_int_{slug}_{route}_{args_hash}`.
- TTL defaults: 1 hour for catalog data, 15 minutes for analytics. Per-integration override.
- Save/update hooks (`save_post_product`, `woocommerce_update_product`, etc.) bust the transient.

---

## Lifecycle

```
plugins_loaded  →  Plugin::run()
                     ↓
                   require_once each integrations/*/*.php
                     ↓
                   each entry-point file:
                     - constructs its Integration class
                     - calls $registry->register( $integration )
                     ↓
init  →  Registry iterates active integrations
           ↓
           Integration::register() runs (hooks + post-types if needed)
           ↓
rest_api_init  →  Integration registers its REST routes
```

We do NOT want integrations doing work in their constructor. Constructor: assignment only. Hooks: `register()`. This keeps `is_available()` cheap and avoids loading WooCommerce code paths before WooCommerce itself is ready.

---

## Per-integration enable / disable

Even if an integration's target plugin is active, the admin should be able to disable it from the plugin's settings page (privacy, debugging, A/B-ing prompt quality with vs without).

Storage: a single `wp_option`, `site_walker_wp_integrations_enabled`, holding an associative array `{ slug => bool }`. Defaults to all-enabled when the integration first registers.

UI: a third tab on the settings page — "Integrations" — listing all detected integrations with a toggle each, plus a status line ("WooCommerce 9.3 — 247 products" etc.).

---

## Failure modes we should think about

- **Target plugin deactivated.** REST returns 503; admin tab shows "unavailable, reason: …".
- **Target plugin major version bump breaks our calls.** Each integration self-reports a `compat_range` (e.g. `>=8.0 <10.0`); on mismatch we still load but log a notice and gate writes.
- **Slow plugin (e.g. analytics queries with no index).** Per-integration query budget — controller bails after N ms with a partial response + warning, so the upstream API never hangs on us.
- **Plugin uninstall.** Integration's own transients should be wiped on `uninstall.php`. Block-source tags pointing at the integration become orphaned on the API side — that needs a sync convention (see system-blocks doc).

---

## What I assumed (please correct on review)

1. The API is happy to pull integration data from this plugin on demand, rather than us pushing it. Pull is simpler — the API decides when it needs the data and we just answer. Push would force us to track API state.
2. We're OK with a single REST namespace (`site-walker/v1`) regardless of how many integrations exist. Alternative: each integration registers its own root namespace. Single namespace is friendlier for the API client.
3. We don't need to support multiple instances of the same integration (e.g. two analytics plugins). One integration = one target plugin.

---

## Out of scope for this doc

- The actual REST route shapes for each integration — see `11-*.md` and `12-*.md`.
- The system-blocks data model and sync — see `20-system-blocks-api.md`.
- Multi-language / WPML compatibility — defer.
