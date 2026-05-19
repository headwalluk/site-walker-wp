# WooCommerce Integration (Planning)

**Status:** Proposal — for review
**Last Updated:** 2026-05-19
**Author:** Paul (drafted by assistant)
**Depends on:** [`10-integrations-architecture.md`](10-integrations-architecture.md)

---

## Use case

The chatbot should be able to answer questions like:

- "Do you sell X?"
- "What variants does the Y product come in?"
- "What's the price range for product line Z?"
- "What's in this bundle?"
- "What currencies do you accept?"

Today the upstream API would need to scrape the storefront to learn any of this, which is slow, fragile, and misses anything behind variation selectors. With a direct WC integration, we hand the API structured truth.

---

## HPOS compliance

Hard requirement from day one — see [`patterns/woocommerce.md`](patterns/woocommerce.md). Every order or product read goes through `wc_get_order()` / `wc_get_product()` and their CRUD getters. No `get_post_meta()` on order data. The integration entry point declares HPOS compatibility:

```php
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            STWLK_PLUGIN_FILE,  // declare for the whole plugin, not just the integration file
            true
        );
    }
} );
```

Note: we declare compatibility against the **main plugin file**, not the integration entry point. WC's compatibility check works against the registered plugin slug.

---

## What we expose

### Available now (v1 of the integration)

| Concept            | Data source                                | Notes                                                                 |
| ------------------ | ------------------------------------------ | --------------------------------------------------------------------- |
| Catalog overview   | `wc_get_products( ... )` (counts + meta)   | Total product count, by status, by category, price range.             |
| Product list       | `wc_get_products( $args )`                 | Paginated. Public fields only — no stock unless explicitly enabled.   |
| Variations         | `WC_Product_Variable::get_available_variations()` | Per-variant SKU + attributes + price.                            |
| Categories         | `get_terms( 'product_cat' )`               | Tree, including counts.                                               |
| Attributes         | `wc_get_attribute_taxonomies()`            | Used for "comes in what colours/sizes?" answers.                      |
| Shipping summary   | `WC_Shipping_Zones::get_zones()`           | Zones + methods + flat-rate costs. Not per-order calculation.         |
| Tax notes          | `wc_tax_enabled()`, `get_option( 'woocommerce_prices_include_tax' )` | "Are prices inclusive of VAT?" — yes/no flag plus rate. |

### Behind opt-in (privacy / business-sensitive)

| Concept       | Default | Reason                                                                  |
| ------------- | ------- | ----------------------------------------------------------------------- |
| Stock levels  | off     | Competitive intel; many shops hide stock.                               |
| Sales / promos | off    | Risk of revealing campaigns early via the chatbot.                      |
| Total revenue / order counts | off | The model genuinely doesn't need this.                       |

### Deliberately never exposed

- Customer data (names, emails, addresses, order history).
- Order contents.
- Cart contents.
- Coupon codes (would let the model leak working discount codes).

---

## REST routes

All under `site-walker/v1/integrations/woocommerce/`.

```
GET    /summary
GET    /products?page=1&per_page=50&since=ISO8601
GET    /products/{id}
GET    /categories
GET    /attributes
GET    /shipping
GET    /policies            # tax notes, currency, "prices inc VAT", etc.
```

All responses are JSON. All routes are read-only (GET only). All require the API key (see architecture doc).

### `GET /summary` example response

```json
{
  "store": { "currency": "GBP", "prices_include_tax": true },
  "catalog": {
    "total_products": 247,
    "by_status": { "publish": 240, "draft": 7 },
    "by_type": { "simple": 180, "variable": 65, "grouped": 2 },
    "price_range": { "min": 4.99, "max": 1299.0 },
    "categories_top": [
      { "slug": "lab-glass", "name": "Lab glass", "count": 92 },
      { "slug": "calculators", "name": "Calculators", "count": 38 }
    ]
  },
  "generated_at": "2026-05-19T08:14:00Z",
  "schema": "site-walker-v1"
}
```

### `GET /products` example response

Paginated. Default `per_page=50`, max `200`. Includes `Link: <...>; rel="next"` header for cursor-style iteration.

```json
{
  "products": [
    {
      "id": 1234,
      "type": "variable",
      "name": "Cubit Calculator Pro",
      "slug": "cubit-calculator-pro",
      "permalink": "https://devx.headwall.tech/product/cubit-calculator-pro/",
      "short_description": "Interactive grid calculator …",
      "categories": ["calculators", "interactive-tools"],
      "price_range": { "min": 19.99, "max": 49.99, "currency": "GBP" },
      "variations": [
        { "id": 1235, "attrs": { "edition": "standard" }, "price": 19.99, "sku": "CCP-STD" },
        { "id": 1236, "attrs": { "edition": "pro" },      "price": 49.99, "sku": "CCP-PRO" }
      ]
    }
  ],
  "page": 1,
  "per_page": 50,
  "total": 247
}
```

The model doesn't need raw HTML descriptions, so we strip tags and collapse whitespace on `short_description`.

---

## Suggested system blocks

The integration auto-generates these and offers them to the admin (admin can disable / re-prioritise but not edit the body — those are kept fresh by the integration).

1. **Catalog overview** — one paragraph: "We sell ~247 products across X categories. Prices range from £4.99 to £1,299. Top categories: …"
2. **Currency & tax** — one line: "Prices are shown in GBP, inclusive of 20% VAT."
3. **Shipping** — bullet list: "UK: free over £50, else £4.95. EU: from £9.95. Rest of world: from £14.95."
4. **Returns** — pulled from the WC Returns / Refunds page if one is set; otherwise a "no policy set" warning shown to the admin.

These are **suggestions**, not commands. The admin can disable any of them. They live alongside hand-written blocks (see [`20-system-blocks-api.md`](20-system-blocks-api.md)).

---

## Cache strategy

| Route            | TTL    | Bust on                                                     |
| ---------------- | ------ | ----------------------------------------------------------- |
| `/summary`       | 1 hour | `save_post_product`, `woocommerce_update_product`, category term changes |
| `/products` list | 1 hour | Same as above                                               |
| `/products/{id}` | 1 hour | Same; also `delete_post`                                    |
| `/categories`    | 6 hours | `edited_product_cat`, `created_product_cat`                |
| `/shipping`      | 6 hours | Zone / method updates                                       |
| `/policies`      | 6 hours | Settings page save                                          |

Generated blocks are also transient-cached (24h) and refreshed by wp-cron once a day, OR on demand via an admin "Refresh now" button.

---

## Variation explosion concern

A store with 100 variable products averaging 10 variations each = 1000 variations to serialise. The `/products` list response excludes variation detail by default; clients add `?include=variations` if they need it. `/products/{id}` always includes variations.

Hard cap: 50 variations per product in the response (with a `truncated: true` flag). If a product has 200 variations, the bot doesn't need every one — it needs to know the attribute axes and the price range.

---

## Bundles, subscriptions, etc.

WooCommerce ecosystem plugins each add their own product type. Approach: detect known sub-types in a small adapter layer.

- **WooCommerce Product Bundles:** if `class_exists( 'WC_Product_Bundle' )`, products of type `bundle` get an extra `bundled_items` field in their response.
- **Subscriptions, deposits, composites:** same pattern, each in its own adapter file. None are v1.

Each adapter is opt-in via the Integrations tab — keep the default response shape small.

---

## Open questions for review

1. **Stock levels — opt-in or off entirely?** Recommend opt-in. Many merchants are fine sharing stock; some are emphatically not.
2. **Should `/products` include images?** Image URL only (cheap), or a small list of variants (heavier)? Recommend single primary image URL.
3. **Cross-sells / upsells?** WC has `crosssell_ids` and `upsell_ids`. Likely valuable to the model. Default on.
4. **Multi-currency.** WPML, CurrencySwitcher, etc. each store currency differently. Defer to v2 — for v1, expose only the store's base currency.
5. **Should the integration push catalog deltas via webhook instead of pull?** Cheaper for big catalogs. But adds bidirectional state. Recommend pull-only for v1.
