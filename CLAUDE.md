# Project notes for Claude Code

WordPress plugin: a front-end chat widget that talks to a [Site Walker](https://site-walker.net) API instance. Architecture, milestones, and roadmap live in [`dev-notes/`](dev-notes/) — start with [`00-project-tracker.md`](dev-notes/00-project-tracker.md).

## Gotchas

### `assets/public/widget.js` contains literal NUL bytes — be careful when editing

`formatAssistantMessage()` (the linkifier) uses literal `\x00` bytes as the sentinel that wraps `LINK<n>` placeholders. The NULs are there because they reliably survive both `escapeHtml()` and the markdown regexes without colliding with any character the user or model could legitimately produce — a space-based sentinel would be ambiguous with real "LINK1" text in a reply.

Practical consequences:

- **`git` treats the file as binary.** `git diff` shows `Bin 17179 → 17948 bytes` instead of a real diff. Use `git diff --text` (or `git log -p --text`) to see line-level changes. The file is valid JavaScript despite this; `node --check` passes; the browser parses it fine.
- **The `Read` tool renders the NUL bytes as ordinary spaces** in its line-numbered output. If you copy a region from `Read` output into an `Edit` `old_string` and the region crosses one of the sentinel lines (around the `return \`...LINK${index}...${trailing}\`;` line and the matching `.replace(/.../g, ...)` regex), the match will silently fail because what looks like " LINK" in your `old_string` is actually `\x00LINK` in the file.
- **Workaround for edits that need to touch sentinel lines:** either use `Write` to rewrite the whole file (preserving the NULs by reading the source via `cat -A` first to confirm their positions), or use `sed -i $'s/\x00LINK/...replacement.../g' file.js` to do the surgical replacement at the byte level. For edits to *adjacent* lines, restrict your `old_string` to a region that doesn't span the sentinel lines and the normal `Edit` flow works fine.
- **If you ever rewrite `escapeHtml()`**, do not strip NUL bytes (`.replace(/\x00/g, '')` or similar) — the linkifier depends on them surviving the escape pass.

The sentinel choice is intentional and load-bearing; don't "fix" it to spaces without replacing it with another non-ambiguous character first.
