/**
 * Site Walker — shared assistant-message formatter.
 *
 * Renders assistant replies as HTML: a small subset of markdown (headings,
 * bullet + ordered lists, **strong**, *em* / _em_, inline `code`) plus
 * allowlist-gated auto-linking of bare URLs and markdown-syntax links.
 *
 * Exposed on `window.SiteWalkerFormatter` so both the front-end widget
 * (`widget.js`) and the Sessions admin tab (`admin.js`) can render messages
 * identically without duplicating the regex passes.
 *
 * Pipeline (in order):
 *   0. Tokenise markdown-syntax links `[text](url)` then bare URLs into
 *      `~~SWWPLINK<n>~~` placeholders. URLs matching the trusted-host
 *      allowlist (same-origin OR caller-supplied trustedHosts) become
 *      `<a>` tags; non-allowlisted bare URLs stay as plain text, non-
 *      allowlisted markdown links collapse to their label text.
 *   1. HTML-escape everything. Placeholders survive (they contain only
 *      `~`, alphanumerics).
 *   2. Block-level pass: `# `, `## `, `### ` → `<h3>` (collapse all heading
 *      levels — visitor-side widget shouldn't introduce a page-h1); runs
 *      of `- ` lines → `<ul><li>…</li></ul>`; runs of `\d+. ` lines →
 *      `<ol><li>…</li></ol>`.
 *   3. Inline pass: backtick code, then `**strong**`, then `*em*` / `_em_`.
 *      Bold runs first so its `*` chars aren't eaten by the italic pass.
 *   4. Restore link placeholders.
 *
 * The sentinel is `~~SWWPLINK<n>~~` rather than the previously-used
 * `__SWWPLINK<n>__` because the new italic pass (`_…_`) would otherwise
 * match against the sentinel's own underscores and strip the placeholder.
 * `~` doesn't appear in any markdown delimiter we support, doesn't get
 * HTML-escaped, and stays plain ASCII (so this file stays git-text).
 */
(function () {
	'use strict';

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	// Resolve a URL against the trusted-host allowlist + same-origin rule.
	// Returns the rendered `<a>` HTML on allow, or null on deny so the
	// caller can decide what to render in place of the link.
	function buildAnchor(rawUrl, visibleText, opts) {
		var parsed;
		try { parsed = new URL(rawUrl); } catch (e) { return null; }
		var isSameHost = parsed.host === opts.currentHost;
		var isTrustedExternal = opts.trustedHosts.has(parsed.host);
		if (!isSameHost && !isTrustedExternal) return null;
		var extraAttrs = isSameHost
			? ''
			: ' target="_blank" rel="noopener noreferrer nofollow"';
		return '<a href="' + escapeHtml(parsed.href) + '"' + extraAttrs + '>' + escapeHtml(visibleText) + '</a>';
	}

	// Block-level rendering. Operates on the (already HTML-escaped) text.
	// Non-block lines pass through unchanged, accumulated into "pending"
	// runs that get joined with literal newlines (the `.swwp-message` CSS
	// uses `white-space: pre-wrap` so those newlines render as visual
	// line breaks). Block elements break out of any pending run and emit
	// their own tags; consecutive list items collapse into one wrapper.
	function renderBlocks(text) {
		var lines = text.split('\n');
		var out = [];
		var pending = [];
		var listType = null;

		function flushPending() {
			// Strip leading/trailing blank lines so the `pre-wrap` CSS
			// doesn't render a markdown blank-line separator AS an extra
			// blank line right next to a block element — the block tag
			// already provides its own vertical break via CSS margin.
			while (pending.length && pending[0] === '') pending.shift();
			while (pending.length && pending[pending.length - 1] === '') pending.pop();
			if (pending.length) {
				out.push(pending.join('\n'));
			}
			pending = [];
		}
		function closeList() {
			if (listType) {
				out.push('</' + listType + '>');
				listType = null;
			}
		}

		for (var i = 0; i < lines.length; i++) {
			var line = lines[i];
			var headingMatch = line.match(/^#{1,6}\s+(.*)$/);
			var ulMatch = line.match(/^-\s+(.*)$/);
			var olMatch = line.match(/^\d+\.\s+(.*)$/);

			if (headingMatch) {
				flushPending();
				closeList();
				out.push('<h3>' + headingMatch[1] + '</h3>');
			} else if (ulMatch) {
				flushPending();
				if (listType && listType !== 'ul') closeList();
				if (!listType) { out.push('<ul>'); listType = 'ul'; }
				out.push('<li>' + ulMatch[1] + '</li>');
			} else if (olMatch) {
				flushPending();
				if (listType && listType !== 'ol') closeList();
				if (!listType) { out.push('<ol>'); listType = 'ol'; }
				out.push('<li>' + olMatch[1] + '</li>');
			} else if (listType && line === '') {
				// Blank line inside a list does NOT close the list — the
				// model commonly emits `1. foo\n\n1. bar\n\n1. baz` and we
				// want all three items in one <ol>. A non-blank, non-list
				// line is what actually closes the list (the else branch
				// below).
			} else {
				closeList();
				pending.push(line);
			}
		}
		flushPending();
		closeList();
		return out.join('');
	}

	function formatAssistant(raw, options) {
		if (typeof raw !== 'string') return '';
		options = options || {};
		var opts = {
			trustedHosts: new Set(Array.isArray(options.trustedHosts) ? options.trustedHosts : []),
			currentHost: options.currentHost || (window.location && window.location.host) || ''
		};

		var placeholders = [];

		// Phase 0a: markdown-syntax links `[text](url)`. Must run before
		// the bare-URL pass — otherwise that pass would swallow the URL
		// portion and leave a dangling `[text]()` in the output.
		var text = raw.replace(/\[([^\]\n]+?)\]\((https?:\/\/[^\s)]+)\)/g, function (_match, label, url) {
			var anchor = buildAnchor(url, label, opts);
			if (anchor === null) {
				// Non-allowlisted host: render the label as plain text,
				// drop the URL entirely. The model deliberately hid the
				// URL behind link syntax — surfacing it as a bare URL
				// would override that intent, and surfacing a clickable
				// link to an un-vetted host is what the allowlist exists
				// to prevent.
				return label;
			}
			var idx = placeholders.length;
			placeholders.push(anchor);
			return '~~SWWPLINK' + idx + '~~';
		});

		// Phase 0b: bare URLs.
		text = text.replace(/https?:\/\/[^\s<>"')]+/g, function (match) {
			// Trailing punctuation + markdown emphasis delimiters are
			// almost never part of the URL — strip them and re-attach
			// them after the placeholder so the surrounding inline
			// markdown pass can still match its closing delimiter.
			// (`*` / `_` added per dev-notes/60-formatter-url-markdown-collision.md.)
			var trailingMatch = match.match(/[.,;:!?)\]\*_]+$/);
			var trailing = trailingMatch ? trailingMatch[0] : '';
			var url = trailing ? match.slice(0, -trailing.length) : match;

			var anchor = buildAnchor(url, url, opts);
			if (anchor === null) {
				// Non-allowlisted bare URL: keep as plain text. The model
				// already chose to write the literal URL, so leaving it
				// visible matches its intent — we just don't make it
				// clickable, which is what the allowlist enforces.
				return match;
			}
			var idx = placeholders.length;
			placeholders.push(anchor);
			return '~~SWWPLINK' + idx + '~~' + trailing;
		});

		// Phase 1: HTML-escape. Sentinels are alphanumeric + `~` so they
		// pass through unchanged; URLs are now safely inside placeholders.
		var html = escapeHtml(text);

		// Phase 2: block-level (headings, lists).
		html = renderBlocks(html);

		// Phase 3: inline emphasis + code.
		// Bold before italic so `**foo**` doesn't get half-eaten by `*foo*`.
		html = html.replace(/`([^`\n]+?)`/g, '<code>$1</code>');
		html = html.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
		html = html.replace(/_([^_\n]+?)_/g, '<em>$1</em>');

		// Phase 4: restore link placeholders.
		html = html.replace(/~~SWWPLINK(\d+)~~/g, function (_match, i) {
			return placeholders[Number(i)] || '';
		});

		return html;
	}

	window.SiteWalkerFormatter = {
		escape: escapeHtml,
		formatAssistant: formatAssistant,
		// Convenience dispatcher: user / system messages bypass markdown
		// and just get HTML-escaped (so model output never accidentally
		// renders as an actual <script> tag etc.). Assistant messages get
		// the full pipeline.
		format: function (raw, role, options) {
			if (typeof raw !== 'string') return '';
			if (role !== 'assistant') return escapeHtml(raw);
			return formatAssistant(raw, options);
		}
	};
}());
