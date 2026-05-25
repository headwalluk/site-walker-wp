# Project notes for Claude Code

WordPress plugin: a front-end chat widget that talks to a [Site Walker](https://site-walker.net) API instance. Architecture, milestones, and roadmap live in [`dev-notes/`](dev-notes/) — start with [`00-project-tracker.md`](dev-notes/00-project-tracker.md).

## Gotchas

### Assistant-message rendering lives in a shared module

The chat-text formatter (escape, linkify with trusted-host allowlist, headings, lists, emphasis, inline code) is in [`assets/shared/formatter.js`](assets/shared/formatter.js), exposed on `window.SiteWalkerFormatter`. Both `assets/public/widget.js` (front-end chat bubbles) and `assets/admin/admin.js` (Sessions tab) consume it via the same handle (`site-walker-wp-formatter`), enqueued from `includes/class-public-hooks.php` and `includes/class-admin-hooks.php` respectively.

If you need to change how a markdown construct renders, edit the shared module — both call sites pick it up automatically.

The module uses `~~SWWPLINK<n>~~` as the internal placeholder sentinel that holds tokenised URLs through the HTML-escape + markdown passes. `~` is the only printable ASCII char that doesn't collide with any of the markdown delimiters we parse (`*`, `_`, `` ` ``, `#`, `-`, digit-dot, `[`/`]`/`(`/`)`) and doesn't get touched by HTML escaping. If you add new markdown features that introduce `~` as a delimiter (strikethrough, for example), pick a different sentinel char first — the placeholder restore pass at the end of the pipeline assumes the sentinel survives every intermediate regex unchanged.
