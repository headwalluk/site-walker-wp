# Formatter bug: URL tokenizer collides with markdown emphasis

**Status:** Planned. Post-1.0.0 polish.
**Files:** `assets/public/widget.js` (lines ~146-181), `assets/admin/admin.js` (the duplicated `formatMessageBody` block).
**Reported:** 2026-05-25.

## The bug

The model returned this literal string in a chat reply:

```
**https://devx.headwall.tech/demos/**
```

Expected rendering — bold + linkified:

```html
<strong><a href="https://devx.headwall.tech/demos/">https://devx.headwall.tech/demos/</a></strong>
```

Actual rendering — both asterisks pulled into the URL, leading `**` orphaned, link 404s because the path has `**` appended:

```html
**<a href="https://devx.headwall.tech/demos/**">https://devx.headwall.tech/demos/**</a>
```

The trailing punctuation got captured into both the `href` and the visible anchor text. Clicking 404s. The leading `**` renders as literal asterisks because it never finds a closing `**` to pair with (the closing `**` got eaten by the URL match).

## Root cause

Two cooperating shortcomings in `formatAssistantMessage` / `formatMessageBody`:

1. **URL regex is too permissive.** `/https?:\/\/[^\s<>"')]+/g` excludes whitespace, angle brackets, quotes, and `)` — but *not* `*` or `_`. When the model emits `**https://…**`, the regex greedily matches `https://devx.headwall.tech/demos/**` (asterisks included).

2. **Trailing-punctuation strip doesn't know about markdown delimiters.** `/[.,;:!?)\]]+$/` trims sentence-final punctuation off the URL but doesn't trim `*` or `_`, so the captured `**` stays bolted on.

End result: the URL stored in the placeholder has `**` appended. When the surrounding text (`** LINK0 `) goes through the `**...**` bold pass afterwards, the leading `**` has no closing partner and is left as literal text. The placeholder substitution re-emits the broken `<a>` tag.

## Two-line fix

Extend the trailing-strip regex to recognise markdown emphasis delimiters as "trailing punctuation":

```diff
- const trailingMatch = match.match(/[.,;:!?)\]]+$/);
+ const trailingMatch = match.match(/[.,;:!?)\]\*_]+$/);
```

That's it. Trace for the reported input:

| Step | Value |
|------|-------|
| Raw URL match | `https://devx.headwall.tech/demos/**` |
| Trailing strip | `**` is now in the regex's character class → captured + sliced off |
| Cleaned URL | `https://devx.headwall.tech/demos/` |
| Placeholder text | `\x00LINK0\x00**` (the trailing `**` reattached after the placeholder) |
| `**…**` bold pass | Now there's a closing `**` to pair with the leading `**`; the whole bold span wraps cleanly |
| Final | `<strong><a href="https://devx.headwall.tech/demos/">https://devx.headwall.tech/demos/</a></strong>` |

The same one-line change needs to land in `admin.js` (the Sessions tab's duplicate formatter) — same regex, same fix.

## Why not "process markdown first, then linkify"?

The cleaner architectural fix is to do bold/code conversion *before* URL tokenisation, so the linkifier never sees the markdown delimiters at all. Reasons to *not* do that here:

- The current order (linkify → escape → markdown → restore-links) is what makes the NUL-byte sentinel scheme work. Re-ordering forces a redesign of the sentinel handling.
- Bold/code regexes would then need to run on raw (un-escaped) text, which means they'd have to be defensive about HTML-special chars in their captured groups.
- The two-line fix above handles the reported failure and the foreseeable adjacent ones (`*url*`, `_url_`, `__url__`) cleanly. No need to spend the architectural ammo on this.

## Test cases

Land alongside the fix. None of these are automated yet — eyeball-test against the widget's chat panel until we have JS test scaffolding.

| Input | Expected output |
|-------|-----------------|
| `**https://devx.headwall.tech/demos/**` | `<strong><a>…/demos/</a></strong>` (bold link, clean URL) |
| `*https://example.com/path*` | `*<a>…/path</a>*` (literal `*` around link — italic isn't a supported tag in our formatter) |
| `__https://example.com__` | `__<a>…</a>__` (literal underscores; double-underscore italic also unsupported) |
| `text **bold url https://example.com bold** more` | `text <strong>bold url <a>…</a> bold</strong> more` (URL in middle of bold span; already worked, regression check) |
| `Check **https://example.com** and **other text**.` | Bold link, then a comma-free separator, then bold "other text", period at end (no carry-over) |
| `(https://example.com)` | `(<a>…</a>)` (existing closing-paren handling, regression check) |
| `https://example.com.` | `<a>…</a>.` (trailing period, regression check) |
| `https://example.com/foo*bar` | `<a>…/foo*bar</a>` — known limitation: legitimate URLs ending with `*` will have the trailing `*` stripped (the test for this case is "URL ending with `*`" — middle-`*` is preserved by the trailing-only strip) |

## Known limitations (accepted)

URLs that legitimately end with `*` or `_` will have those characters stripped. Two reasons this is acceptable:

- The RFC 3986 sub-delim `*` is legal in URL paths/queries but vanishingly rare in real-world URLs.
- The failure mode is "link goes to the URL minus the trailing markdown chars" — likely still a valid (or close-to-valid) destination. Compare with the current behaviour where the link goes to `<URL>**`, which 404s outright.

If a future customer hits this, the surgical follow-up is to look at the surrounding context (was there a paired leading `**`?) and only strip the trailing chars if a pair exists. That's more code; we'll do it when motivated.

## Related technical debt

This formatter is duplicated across two files (`widget.js` uses NUL-byte sentinels; `admin.js` uses ASCII `__SWWPLINK<n>__` sentinels). The duplication was deliberate — see [`CLAUDE.md`](../CLAUDE.md) for the NUL gotcha — but it means any formatter fix has to land in two places.

A follow-up worth considering (not in scope here): factor the formatter into a shared module that both files load. Two options:

- **Shared file with ASCII sentinels.** Convert widget.js to use the ASCII sentinel scheme too; ship the shared file as a third asset that both contexts enqueue. Buys us out of the NUL-byte gotcha forever.
- **Build-step concatenation.** Authour once, generate `widget.js` and `admin.js` from a template. More tooling, less clear.

The shared-file-with-ASCII-sentinels option is the better trade unless we have a specific reason to keep the NUL sentinel — and I don't think we do. Track as a separate post-1.0.0 entry if the formatter sees another change.

## Implementation checklist

- [ ] Extend the trailing-strip regex in `widget.js::formatAssistantMessage` (around line 150).
- [ ] Extend the trailing-strip regex in `admin.js::formatMessageBody` (matching helper).
- [ ] Eyeball-test each row of the table above.
- [ ] Update CHANGELOG under `[Unreleased]`.
- [ ] Tracker: tick this off under "Polish (post-1.0.0)".

Estimated effort: 15 minutes including manual test.
