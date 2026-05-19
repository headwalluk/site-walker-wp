# Site Walker WP

A floating front-end chat widget for WordPress, talking directly to a [Site Walker](https://site-walker.net) API instance from the visitor's browser.

**Status:** 0.3.0 — pre-alpha.

## Install

1. Drop into `wp-content/plugins/site-walker-wp/`.
2. Activate: `wp plugin activate site-walker-wp`.
3. Open **Site Walker** in the WP admin, enable the widget, set the API server URL, tune appearance.

## How it loads

The widget JS gates network calls via `localStorage`, keyed per API host:

1. **Session in progress** → render launcher; rehydrate history via `GET /messages` on open.
2. **API known reachable** (cached probe within 24h) → render launcher; mint a session on click.
3. **No recent probe** → `GET <apiUrl>/sessions/can-start`. On `{ "ok": true }`, render the launcher. On any other response, hide and don't re-probe for 1h.

## Requires

- WordPress 6.0+, PHP 8.0+
- A Site Walker API instance whose website allowlist includes the WP host's origin

## Docs

- Roadmap & TODO: [`dev-notes/00-project-tracker.md`](dev-notes/00-project-tracker.md)
- Changelog: [`CHANGELOG.md`](CHANGELOG.md)
- Coding standards: [`.github/copilot-instructions.md`](.github/copilot-instructions.md)

GPL-2.0-or-later.
