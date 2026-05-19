=== Site Walker ===
Contributors: headwalluk
Tags: chat, chatbot, ai, llm, widget
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Floating front-end chat widget for WordPress, talking directly to a Site Walker API instance from the visitor's browser.

== Description ==

Site Walker adds a floating chat launcher to the front-end of your site. The widget runs entirely in the visitor's browser and talks directly to a [Site Walker](https://site-walker.net) API instance — there is no PHP middle layer for chat traffic, and no third-party JavaScript bundle.

Features:

* Tabbed admin settings page (General / Appearance) using the WordPress Settings API.
* Colour pickers, position offsets, header text and placeholder, all configurable without code.
* CSS-variable-driven theming — colours and offsets reach the panel without inline `<style>` blocks per element.
* `localStorage`-backed session persistence so conversations survive page reloads, keyed per API host to avoid collisions.
* Cached reachability probe (`GET /sessions/can-start`) so unreachable APIs don't hammer the network on every page view.

Site Walker is for operators who already run a Site Walker API instance. The plugin itself does not include a language model or chat backend.

== Installation ==

1. Upload the plugin directory to `wp-content/plugins/site-walker-wp/`, or install via the WordPress plugin admin.
2. Activate the plugin.
3. Open **Site Walker** in the WP admin, enable the widget, set the API server URL, and tune appearance.
4. On the Site Walker API side, make sure your website's origin allowlist includes the host the widget loads from.

== Frequently Asked Questions ==

= Does this work without a Site Walker API instance? =

No. The plugin is a front-end widget; the actual chat is served by a Site Walker API instance you (or your provider) operate.

= Does the widget proxy requests through WordPress? =

No. The widget calls the configured API URL directly from the visitor's browser. WordPress only serves the widget assets and admin UI.

= Where is the session token stored? =

In the visitor's `localStorage`, keyed per API host so multiple widgets on the same browser don't collide. The token is opaque and tied to the website's origin allowlist on the API side.

== Changelog ==

= 0.3.0 =
* Changed: text domain renamed from `site-walker-wp` to `site-walker`; `@package` renamed to `Site_Walker`.
* Changed: global-scope identifiers now use the `STWLK_` / `stwlk_` prefix; main entry file no longer declares a namespace.
* Added: light markdown formatting in assistant replies — bold (`**…**`), inline code (`` `…` ``), and same-origin URL auto-linking. External URLs are deliberately not linkified.
* Fixed: settings page template was in a non-existent `Site_Walker_WP` namespace, breaking constant lookups for `SETTINGS_GROUP` / `ADMIN_PAGE_SLUG`.
* Fixed: classes under the `Site_Walker` namespace referenced bare `PLUGIN_DIR`/`PLUGIN_URL`/`PLUGIN_VERSION` constants that no longer existed; switched to global `\STWLK_PLUGIN_*` references.
* Fixed: plugin bootstrap (`stwlk_plugin_run()`) is now invoked.

= 0.2.0 =
* Changed: reachability probe now calls `GET /sessions/can-start` and treats `200 { "ok": true }` as available, matching the API's documented pre-flight endpoint.
* Fixed: end-to-end browser flow (probe → mint → chat) works against the upstream API.

= 0.1.0 =
* Initial scaffold: settings page, front-end widget, three-state load flow (cached session → cached probe → fresh probe), session mint and chat turn handling.

== Upgrade Notice ==

= 0.3.0 =
Text domain renamed to `site-walker` and global-scope prefix standardised on `STWLK_` / `stwlk_`. Bootstrap is now actually invoked (the 0.2.0 release shipped a no-op entry point). No DB changes; option keys unchanged.

= 0.2.0 =
Probe endpoint changed to `/sessions/can-start`. Requires a Site Walker API instance that exposes the new endpoint.
