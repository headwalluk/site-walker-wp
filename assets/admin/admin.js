(function ($) {
	'use strict';

	const config = window.siteWalkerWPAdmin || { restRoot: '', nonce: '', strings: {} };
	const STR = config.strings;

	// Tabs whose body lives inside the Settings-API form — the shared submit
	// button is only shown when one of these is the active tab.
	const SETTINGS_API_TABS = new Set(['widget', 'appearance']);

	// ---------------------------------------------------------------------
	// REST helper
	// ---------------------------------------------------------------------
	async function apiCall(method, path, body) {
		const url = config.restRoot + path;
		const init = {
			method,
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.nonce,
				Accept: 'application/json',
			},
		};
		if (body !== undefined) {
			init.headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify(body);
		}

		let res;
		try {
			res = await fetch(url, init);
		} catch (e) {
			return { ok: false, status: 0, error: 'transport_error', detail: { message: e.message } };
		}

		const text = await res.text();
		let envelope = {};
		try {
			envelope = text ? JSON.parse(text) : {};
		} catch (e) {
			return { ok: false, status: res.status, error: 'parse_error', detail: { message: e.message } };
		}

		// Our REST layer always responds 200 with an envelope; envelope.error
		// signals failure.
		if (envelope.error) {
			return { ok: false, status: envelope.status || res.status, error: envelope.error, detail: envelope.detail || null };
		}

		return { ok: true, status: res.status, data: envelope };
	}

	// Coerce a maybe-array to an actual array. Defends against upstream
	// shape drift or a parse glitch in the proxy layer — a single bad
	// payload shouldn't blow up the admin page with a TypeError.
	function toArray(maybe) {
		return Array.isArray(maybe) ? maybe : [];
	}

	// Map an API error code → friendly user-facing message.
	function errorMessage(result) {
		if (!result || result.ok) return '';
		switch (result.error) {
			case 'bearer_invalid': return STR.bearerInvalid;
			case 'wrong_scope':    return STR.wrongScope;
			case 'not_found':      return STR.notFound;
			case 'transport_error': return STR.transportError;
			case 'validation_failed':
				return (result.detail && result.detail.message) || STR.unexpectedError;
			default:
				return result.detail && result.detail.message ? result.detail.message : STR.unexpectedError;
		}
	}

	// ---------------------------------------------------------------------
	// Color pickers (existing)
	// ---------------------------------------------------------------------
	function initColorPickers() {
		$('.site-walker-wp-color-field').wpColorPicker();
	}

	// ---------------------------------------------------------------------
	// Tabs
	// ---------------------------------------------------------------------
	function initTabs() {
		const tabs = document.querySelectorAll('.site-walker-wp-settings .nav-tab');
		const panels = document.querySelectorAll('.site-walker-wp-settings .tab-panel');
		const submitBtn = document.querySelector('.site-walker-wp-settings .swwp-settings-submit');

		if (!tabs.length || !panels.length) {
			return;
		}

		// Parse the URL hash into a tab name + optional sub-route. For example
		// `#sessions/412` → { tab: 'sessions', sub: '412' }. Tabs that don't
		// use sub-routes (everything except Sessions today) simply ignore the
		// sub value when they handle `swwp:tab-activate`.
		function parseHash() {
			const raw = window.location.hash.substring(1);
			const [name, ...rest] = raw.split('/');
			return {
				tab: name || tabs[0].dataset.tab,
				sub: rest.join('/') || null,
			};
		}

		function activate(name, sub) {
			tabs.forEach((tab) => {
				tab.classList.toggle('nav-tab-active', tab.dataset.tab === name);
			});
			panels.forEach((panel) => {
				const visible = panel.dataset.panel === name;
				panel.style.display = visible ? 'block' : 'none';
				if (visible) {
					panel.dispatchEvent(new CustomEvent('swwp:tab-activate', { detail: { sub: sub || null } }));
				}
			});
			if (submitBtn) {
				submitBtn.style.display = SETTINGS_API_TABS.has(name) ? '' : 'none';
			}
		}

		const initial = parseHash();
		activate(initial.tab, initial.sub);

		tabs.forEach((tab) => {
			tab.addEventListener('click', (e) => {
				e.preventDefault();
				window.location.hash = tab.dataset.tab;
				activate(tab.dataset.tab, null);
			});
		});

		window.addEventListener('hashchange', () => {
			const { tab, sub } = parseHash();
			activate(tab, sub);
		});
	}

	// ---------------------------------------------------------------------
	// Connection tab
	// ---------------------------------------------------------------------
	function initConnectionTab() {
		const panel = document.querySelector('#connection-panel');
		if (!panel) return;

		const apiUrlInput  = panel.querySelector('#swwp-api-url');
		const keyInput     = panel.querySelector('#swwp-admin-key');
		const keySaveBtn   = panel.querySelector('.swwp-key-save');
		const keyClearBtn  = panel.querySelector('.swwp-key-clear');
		const keyReplaceBtn = panel.querySelector('.swwp-key-replace');
		const keyMaskEl    = panel.querySelector('.swwp-key-mask');
		const keyRow       = panel.querySelector('.swwp-key-row');
		const activeChatbot = panel.querySelector('.swwp-active-chatbot');
		const pickerToggle = panel.querySelector('.swwp-chatbot-picker-toggle');
		const picker       = panel.querySelector('.swwp-chatbot-picker');
		const pickerSelect = panel.querySelector('.swwp-chatbot-select');
		const pickerSave   = panel.querySelector('.swwp-chatbot-save');
		const testBtn      = panel.querySelector('.swwp-connection-test');
		const statusEl     = panel.querySelector('.swwp-status');

		function setStatus(text, kind) {
			statusEl.textContent = text || '';
			statusEl.className = 'swwp-status' + (kind ? ' is-' + kind : '');
		}

		function renderChatbot(slug) {
			if (slug) {
				activeChatbot.innerHTML = '';
				const code = document.createElement('code');
				code.textContent = slug;
				activeChatbot.appendChild(code);
				activeChatbot.dataset.slug = slug;
				pickerToggle.hidden = false;
				testBtn.hidden = false;
			} else {
				activeChatbot.textContent = STR.notConnected;
				activeChatbot.dataset.slug = '';
				pickerToggle.hidden = true;
			}
		}

		function populatePicker(chatbots, currentSlug) {
			pickerSelect.innerHTML = '';
			chatbots.forEach((cb) => {
				const opt = document.createElement('option');
				opt.value = cb.slug;
				opt.textContent = cb.name ? `${cb.name} (${cb.slug})` : cb.slug;
				if (cb.slug === currentSlug) opt.selected = true;
				pickerSelect.appendChild(opt);
			});
		}

		// "Save & connect" — POST /connection with admin key (+ optional URL).
		//
		// On success we reload the page rather than mutating DOM state in
		// place. The Connection tab has several pieces of UI whose presence
		// vs. absence depends on whether a key is saved (the Clear / Replace
		// buttons, the masked-key display, the picker toggle); rendering
		// those from the server template on a fresh page load is much more
		// robust than threading the transitions through JS. The flash of a
		// page reload is a fine trade for a once-per-setup action.
		keySaveBtn.addEventListener('click', async () => {
			const key = (keyInput.value || '').trim();
			const apiUrl = (apiUrlInput.value || '').trim();
			if (!key) return;

			keySaveBtn.disabled = true;
			setStatus('Saving…');

			const result = await apiCall('POST', '/connection', { admin_key: key, api_url: apiUrl });

			if (!result.ok) {
				keySaveBtn.disabled = false;
				setStatus(errorMessage(result), 'error');
				return;
			}

			const chatbots = result.data.chatbots || [];
			if (chatbots.length === 0) {
				setStatus(STR.noChatbots, 'warning');
			} else if (chatbots.length === 1) {
				setStatus(STR.savedAndConnected + ' ' + (chatbots[0].name || chatbots[0].slug) + '. Reloading…', 'success');
			} else {
				setStatus('Connected — pick your chatbot after reload…', 'info');
			}

			// Tiny pause so the user sees the success message before reload.
			setTimeout(() => window.location.reload(), 600);
		});

		// "Clear" — DELETE /connection.
		if (keyClearBtn) {
			keyClearBtn.addEventListener('click', async () => {
				if (!window.confirm(STR.clearConfirm)) return;
				const result = await apiCall('DELETE', '/connection');
				if (!result.ok) {
					setStatus(errorMessage(result), 'error');
					return;
				}
				// Simplest correct UX: reload so the server-rendered template
				// reflects the cleared state.
				window.location.reload();
			});
		}

		// "Replace" — reveal the input again.
		if (keyReplaceBtn) {
			keyReplaceBtn.addEventListener('click', () => {
				keyInput.hidden = false;
				keySaveBtn.hidden = false;
				keyInput.focus();
			});
		}

		// "Change…" — open the picker, refresh the list via /connection/test.
		if (pickerToggle) {
			pickerToggle.addEventListener('click', async () => {
				picker.hidden = false;
				setStatus('Loading chatbots…');
				const result = await apiCall('POST', '/connection/test');
				if (!result.ok) {
					setStatus(errorMessage(result), 'error');
					picker.hidden = true;
					return;
				}
				const chatbots = toArray(result.data && result.data.chatbots);
				if (chatbots.length === 0) {
					setStatus(STR.noChatbots, 'warning');
					picker.hidden = true;
					return;
				}
				populatePicker(chatbots, (result.data && result.data.chatbot_slug) || '');
				setStatus('');
			});
		}

		// "Use this chatbot" — POST /connection/slug.
		pickerSave.addEventListener('click', async () => {
			const slug = pickerSelect.value;
			if (!slug) return;
			pickerSave.disabled = true;
			const result = await apiCall('POST', '/connection/slug', { slug });
			pickerSave.disabled = false;
			if (!result.ok) {
				setStatus(errorMessage(result), 'error');
				return;
			}
			renderChatbot(slug);
			picker.hidden = true;
			setStatus(STR.saved, 'success');
		});

		// "Test connection".
		if (testBtn) {
			testBtn.addEventListener('click', async () => {
				setStatus('Testing…');
				const result = await apiCall('POST', '/connection/test');
				if (!result.ok) {
					setStatus(errorMessage(result), 'error');
					return;
				}
				const n = toArray(result.data && result.data.chatbots).length;
				setStatus(`${STR.connectionOk} (${n} chatbot${n === 1 ? '' : 's'} visible.)`, 'success');
			});
		}
	}

	// ---------------------------------------------------------------------
	// Shared helpers for the REST-driven editable tabs (Chatbot, Geo).
	// ---------------------------------------------------------------------

	// Pull a `data-field`-decorated form into a flat object. Number-typed
	// inputs are returned as Number or null (empty string → null); checkboxes
	// as boolean; everything else as string.
	function collectFields(root) {
		const out = {};
		root.querySelectorAll('[data-field]').forEach((el) => {
			const name = el.dataset.field;
			let value;
			if (el.type === 'number') {
				value = el.value === '' ? null : Number(el.value);
			} else if (el.type === 'checkbox') {
				value = el.checked;
			} else {
				value = el.value;
			}
			out[name] = value;
		});
		return out;
	}

	// Reverse of collectFields() — populate `data-field` inputs from a
	// loaded server object. Null / undefined values render as empty.
	function populateFields(root, data) {
		root.querySelectorAll('[data-field]').forEach((el) => {
			const name = el.dataset.field;
			const value = data && data[name];
			if (el.type === 'checkbox') {
				el.checked = !!value;
			} else if (value === null || value === undefined) {
				el.value = '';
			} else {
				el.value = String(value);
			}
		});
	}

	// ---------------------------------------------------------------------
	// Chatbot tab
	// ---------------------------------------------------------------------
	function initChatbotTab() {
		const panel = document.querySelector('#chatbot-panel');
		if (!panel) return;

		const notConfigured = panel.querySelector('.swwp-not-configured');
		const loadingEl     = panel.querySelector('.swwp-tab-loading');
		const formEl        = panel.querySelector('.swwp-tab-form');
		const saveBtn       = panel.querySelector('.swwp-chatbot-save');
		const reloadBtn     = panel.querySelector('.swwp-chatbot-reload');
		const statusEl      = panel.querySelector('.swwp-status');

		function setStatus(text, kind) {
			statusEl.textContent = text || '';
			statusEl.className = 'swwp-status' + (kind ? ' is-' + kind : '');
		}

		async function load() {
			notConfigured.hidden = true;
			formEl.hidden = true;
			loadingEl.hidden = false;
			setStatus('');

			const result = await apiCall('GET', '/chatbot');
			loadingEl.hidden = true;

			if (!result.ok) {
				if (result.error === 'not_configured') {
					notConfigured.hidden = false;
					return;
				}
				setStatus(errorMessage(result), 'error');
				formEl.hidden = false;
				return;
			}

			populateFields(formEl, result.data || {});
			formEl.hidden = false;
		}

		saveBtn.addEventListener('click', async () => {
			const body = collectFields(formEl);
			// Upstream treats null as "clear" for nullable string fields. An
			// empty string is likely to be rejected as validation_failed, so
			// translate empties on the way out.
			['welcome_message', 'persona'].forEach((k) => {
				if (body[k] === '') body[k] = null;
			});
			saveBtn.disabled = true;
			setStatus('Saving…');
			const result = await apiCall('PATCH', '/chatbot', body);
			saveBtn.disabled = false;
			if (!result.ok) {
				setStatus(errorMessage(result), 'error');
				return;
			}
			populateFields(formEl, result.data || {});
			setStatus(STR.saved, 'success');
		});

		reloadBtn.addEventListener('click', load);

		panel.addEventListener('swwp:tab-activate', load);
	}

	// ---------------------------------------------------------------------
	// Geo tab
	// ---------------------------------------------------------------------
	function initGeoTab() {
		const panel = document.querySelector('#geo-panel');
		if (!panel) return;

		const notConfigured = panel.querySelector('.swwp-not-configured');
		const loadingEl     = panel.querySelector('.swwp-tab-loading');
		const formEl        = panel.querySelector('.swwp-tab-form');
		const countriesEl   = panel.querySelector('#swwp-geo-countries');
		const saveBtn       = panel.querySelector('.swwp-geo-save');
		const reloadBtn     = panel.querySelector('.swwp-geo-reload');
		const statusEl      = panel.querySelector('.swwp-status');

		function setStatus(text, kind) {
			statusEl.textContent = text || '';
			statusEl.className = 'swwp-status' + (kind ? ' is-' + kind : '');
		}

		function populateGeo(data) {
			const mode = (data && data.mode) || 'allowall';
			const radio = formEl.querySelector(`input[name="swwp-geo-mode"][value="${mode}"]`);
			if (radio) radio.checked = true;
			const countries = (data && Array.isArray(data.countries)) ? data.countries : [];
			countriesEl.value = countries.join(', ');
		}

		function collectGeo() {
			const modeEl = formEl.querySelector('input[name="swwp-geo-mode"]:checked');
			const mode = modeEl ? modeEl.value : 'allowall';
			// Split on commas, whitespace, or newlines; uppercase; drop empties
			// and anything that isn't exactly two A-Z chars.
			const raw = (countriesEl.value || '').split(/[\s,]+/);
			const countries = raw
				.map((c) => c.trim().toUpperCase())
				.filter((c) => /^[A-Z]{2}$/.test(c));
			return { mode, countries };
		}

		async function load() {
			notConfigured.hidden = true;
			formEl.hidden = true;
			loadingEl.hidden = false;
			setStatus('');

			const result = await apiCall('GET', '/chatbot/geo');
			loadingEl.hidden = true;

			if (!result.ok) {
				if (result.error === 'not_configured') {
					notConfigured.hidden = false;
					return;
				}
				setStatus(errorMessage(result), 'error');
				formEl.hidden = false;
				return;
			}

			populateGeo(result.data || {});
			formEl.hidden = false;
		}

		saveBtn.addEventListener('click', async () => {
			const body = collectGeo();
			saveBtn.disabled = true;
			setStatus('Saving…');
			const result = await apiCall('PATCH', '/chatbot/geo', body);
			saveBtn.disabled = false;
			if (!result.ok) {
				setStatus(errorMessage(result), 'error');
				return;
			}
			populateGeo(result.data || {});
			setStatus(STR.saved, 'success');
		});

		reloadBtn.addEventListener('click', load);

		panel.addEventListener('swwp:tab-activate', load);
	}

	// ---------------------------------------------------------------------
	// Usage tab
	// ---------------------------------------------------------------------
	function initUsageTab() {
		const panel = document.querySelector('#usage-panel');
		if (!panel) return;

		const notConfigured = panel.querySelector('.swwp-not-configured');
		const loadingEl     = panel.querySelector('.swwp-tab-loading');
		const formEl        = panel.querySelector('.swwp-tab-form');
		const sinceEl       = panel.querySelector('#swwp-usage-since');
		const reloadBtn     = panel.querySelector('.swwp-usage-reload');
		const statusEl      = panel.querySelector('.swwp-status');
		const warningsEl    = panel.querySelector('.swwp-usage-warnings');
		const periodEl      = panel.querySelector('.swwp-usage-period');

		function setStatus(text, kind) {
			statusEl.textContent = text || '';
			statusEl.className = 'swwp-status' + (kind ? ' is-' + kind : '');
		}

		function formatCost(n) {
			if (typeof n !== 'number') return '—';
			// Show four decimals when below a dollar so per-message costs are
			// visible; two decimals otherwise.
			return '$' + (n < 1 ? n.toFixed(4) : n.toFixed(2));
		}

		function formatInt(n) {
			if (typeof n !== 'number') return '—';
			return n.toLocaleString();
		}

		function renderUsage(data) {
			data = data || {};
			panel.querySelectorAll('.swwp-usage-value').forEach((cell) => {
				const field = cell.dataset.field;
				const value = data[field];
				if (field === 'cost_usd') {
					cell.textContent = formatCost(value);
				} else {
					cell.textContent = formatInt(value);
				}
			});

			if (data.period && data.period.since) {
				const since = new Date(data.period.since);
				const until = data.period.until ? new Date(data.period.until) : null;
				periodEl.textContent = until
					? `${since.toLocaleString()} → ${until.toLocaleString()}`
					: `since ${since.toLocaleString()}`;
			} else {
				periodEl.textContent = '—';
			}

			const warnings = Array.isArray(data.warnings) ? data.warnings : [];
			if (warnings.length === 0) {
				warningsEl.hidden = true;
				warningsEl.innerHTML = '';
			} else {
				warningsEl.hidden = false;
				warningsEl.innerHTML = '';
				const note = document.createElement('div');
				note.className = 'notice notice-warning inline';
				warnings.forEach((w) => {
					const p = document.createElement('p');
					p.textContent = typeof w === 'string' ? w : (w.message || JSON.stringify(w));
					note.appendChild(p);
				});
				warningsEl.appendChild(note);
			}
		}

		async function load() {
			notConfigured.hidden = true;
			formEl.hidden = true;
			loadingEl.hidden = false;
			setStatus('');

			const since = sinceEl.value || '';
			const path = since ? `/chatbot/usage?since=${encodeURIComponent(since)}` : '/chatbot/usage';
			const result = await apiCall('GET', path);
			loadingEl.hidden = true;

			if (!result.ok) {
				if (result.error === 'not_configured') {
					notConfigured.hidden = false;
					return;
				}
				setStatus(errorMessage(result), 'error');
				formEl.hidden = false;
				return;
			}

			renderUsage(result.data || {});
			formEl.hidden = false;
		}

		reloadBtn.addEventListener('click', load);
		sinceEl.addEventListener('change', load);
		panel.addEventListener('swwp:tab-activate', load);
	}

	// ---------------------------------------------------------------------
	// Message-content formatter (Sessions tab)
	//
	// Renders assistant message bodies the same way the front-end widget
	// does (markdown bold, inline code, same-host + trusted-host auto-
	// linking). Deliberately a duplicate of widget.js's
	// formatAssistantMessage rather than a shared module — see CLAUDE.md
	// for why widget.js uses literal NUL bytes and how that turns the file
	// binary-as-far-as-git-is-concerned. We use a verbose ASCII sentinel
	// here instead so admin.js stays a normal text file. If you change one
	// formatter, update both (the contract is small and stable; the
	// duplication cost is real but bounded).
	// ---------------------------------------------------------------------
	function escapeMessageHtml(s) {
		return s
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function formatMessageBody(raw, role) {
		if (typeof raw !== 'string') return '';
		if (role !== 'assistant') return escapeMessageHtml(raw);

		const trusted = new Set(Array.isArray(config.trustedHosts) ? config.trustedHosts : []);
		const urlPattern = /https?:\/\/[^\s<>"')]+/g;
		const placeholders = [];

		const withPlaceholders = raw.replace(urlPattern, (match) => {
			const trailingMatch = match.match(/[.,;:!?)\]]+$/);
			const trailing = trailingMatch ? trailingMatch[0] : '';
			const url = trailing ? match.slice(0, -trailing.length) : match;

			let parsed;
			try { parsed = new URL(url); } catch (e) { return match; }

			const isSameHost = parsed.host === window.location.host;
			const isTrustedExternal = trusted.has(parsed.host);
			if (!isSameHost && !isTrustedExternal) return match;

			const extraAttrs = isSameHost
				? ''
				: ' target="_blank" rel="noopener noreferrer nofollow"';
			const idx = placeholders.length;
			placeholders.push(
				`<a href="${escapeMessageHtml(parsed.href)}"${extraAttrs}>${escapeMessageHtml(url)}</a>`
			);
			return `__SWWPLINK${idx}__`;
		});

		let html = escapeMessageHtml(withPlaceholders);
		html = html.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/`([^`\n]+?)`/g, '<code>$1</code>');
		html = html.replace(/__SWWPLINK(\d+)__/g, (_, i) => placeholders[Number(i)]);
		return html;
	}

	// ---------------------------------------------------------------------
	// Sessions tab — list + detail
	// ---------------------------------------------------------------------
	function initSessionsTab() {
		const panel = document.querySelector('#sessions-panel');
		if (!panel) return;

		const notConfigured  = panel.querySelector('.swwp-not-configured');
		const loadingEl      = panel.querySelector('.swwp-tab-loading');
		const listEl         = panel.querySelector('.swwp-sessions-list');
		const detailEl       = panel.querySelector('.swwp-session-detail');
		const rowsEl         = panel.querySelector('.swwp-sessions-rows');
		const emptyEl        = panel.querySelector('.swwp-sessions-empty');
		const reloadBtn      = panel.querySelector('.swwp-sessions-reload');
		const prevBtn        = panel.querySelector('.swwp-sessions-prev');
		const nextBtn        = panel.querySelector('.swwp-sessions-next');
		const pageInfoEl     = panel.querySelector('.swwp-sessions-pageinfo');
		const statusEl       = panel.querySelector('.swwp-status');
		const summaryEl      = panel.querySelector('.swwp-session-summary');
		const messagesEl     = panel.querySelector('.swwp-session-messages');

		const PAGE_SIZE = 20;
		let currentPage = 1;
		let totalSessions = 0;

		function setStatus(text, kind) {
			statusEl.textContent = text || '';
			statusEl.className = 'swwp-status' + (kind ? ' is-' + kind : '');
		}

		function showOnly(which) {
			notConfigured.hidden = which !== 'notConfigured';
			loadingEl.hidden     = which !== 'loading';
			listEl.hidden        = which !== 'list';
			detailEl.hidden      = which !== 'detail';
		}

		function formatTs(iso) {
			if (!iso) return '—';
			try {
				return new Date(iso).toLocaleString();
			} catch (e) {
				return iso;
			}
		}

		function formatCost(n) {
			if (typeof n !== 'number') return '—';
			return '$' + (n < 1 ? n.toFixed(4) : n.toFixed(2));
		}

		function badgeHtml(text, kind) {
			return `<span class="swwp-badge swwp-badge-${kind}">${escapeMessageHtml(text)}</span>`;
		}

		function renderRow(s) {
			const tokens = (s.tokens_in || 0) + (s.tokens_out || 0);
			const badges = [];
			if (s.is_admin_mode) badges.push(badgeHtml('Admin mode', 'admin'));
			if (s.terminated_at)  badges.push(badgeHtml('Terminated', 'terminated'));

			const email = s.visitor_email
				? `<a href="mailto:${escapeMessageHtml(s.visitor_email)}">${escapeMessageHtml(s.visitor_email)}</a>`
				: '<span class="swwp-muted">—</span>';

			const idLink = `<a href="#sessions/${s.id}" class="swwp-session-link">#${s.id}</a>`;

			return `
				<tr>
					<td>${idLink}</td>
					<td title="${escapeMessageHtml(s.last_active_at || '')}">${escapeMessageHtml(formatTs(s.last_active_at))}</td>
					<td>${s.message_count || 0}</td>
					<td>${tokens.toLocaleString()}</td>
					<td>${escapeMessageHtml(formatCost(s.cost_usd_estimate))}</td>
					<td>${email}</td>
					<td>${badges.join(' ') || '<span class="swwp-muted">—</span>'}</td>
				</tr>
			`;
		}

		async function loadList(page) {
			showOnly('loading');
			setStatus('');
			currentPage = Math.max(1, page || 1);

			const result = await apiCall('GET', `/chatbot/sessions?page=${currentPage}&page_size=${PAGE_SIZE}`);

			if (!result.ok) {
				if (result.error === 'not_configured') {
					showOnly('notConfigured');
					return;
				}
				showOnly('list');
				setStatus(errorMessage(result), 'error');
				return;
			}

			const data = result.data || {};
			const sessions = Array.isArray(data.sessions) ? data.sessions : [];
			totalSessions = typeof data.total === 'number' ? data.total : sessions.length;

			rowsEl.innerHTML = sessions.map(renderRow).join('');
			emptyEl.hidden = sessions.length > 0;

			const totalPages = Math.max(1, Math.ceil(totalSessions / PAGE_SIZE));
			pageInfoEl.textContent = `Page ${currentPage} of ${totalPages} (${totalSessions} total)`;
			prevBtn.disabled = currentPage <= 1;
			nextBtn.disabled = currentPage >= totalPages;

			showOnly('list');
		}

		async function loadDetail(sessionId) {
			showOnly('loading');
			setStatus('');

			// Fetch metadata + messages in parallel.
			const [metaResult, messagesResult] = await Promise.all([
				apiCall('GET', `/chatbot/sessions/${encodeURIComponent(sessionId)}`),
				apiCall('GET', `/chatbot/sessions/${encodeURIComponent(sessionId)}/messages`),
			]);

			if (!metaResult.ok) {
				if (metaResult.error === 'not_configured') {
					showOnly('notConfigured');
					return;
				}
				showOnly('detail');
				summaryEl.innerHTML = `<p class="swwp-status is-error">${escapeMessageHtml(errorMessage(metaResult))}</p>`;
				messagesEl.innerHTML = '';
				return;
			}

			renderSummary(metaResult.data || {});

			if (!messagesResult.ok) {
				messagesEl.innerHTML = `<p class="swwp-status is-error">${escapeMessageHtml(errorMessage(messagesResult))}</p>`;
			} else {
				const msgs = (messagesResult.data && messagesResult.data.messages) || [];
				renderMessages(Array.isArray(msgs) ? msgs : []);
			}

			showOnly('detail');
		}

		function renderSummary(s) {
			const tokens = (s.tokens_in || 0) + (s.tokens_out || 0);
			const badges = [];
			if (s.is_admin_mode) badges.push(badgeHtml('Admin mode', 'admin'));
			if (s.terminated_at)  badges.push(badgeHtml('Terminated', 'terminated'));

			const email = s.visitor_email
				? `<a href="mailto:${escapeMessageHtml(s.visitor_email)}">${escapeMessageHtml(s.visitor_email)}</a>`
				: '—';

			summaryEl.innerHTML = `
				<h2>Session #${escapeMessageHtml(String(s.id || ''))} ${badges.join(' ')}</h2>
				<dl class="swwp-session-meta">
					<dt>Started</dt><dd>${escapeMessageHtml(formatTs(s.created_at))}</dd>
					<dt>Last active</dt><dd>${escapeMessageHtml(formatTs(s.last_active_at))}</dd>
					<dt>Terminated</dt><dd>${s.terminated_at ? escapeMessageHtml(formatTs(s.terminated_at)) : '—'}</dd>
					<dt>Messages</dt><dd>${s.message_count || 0}</dd>
					<dt>Tokens</dt><dd>${tokens.toLocaleString()} (${(s.tokens_in || 0).toLocaleString()} in / ${(s.tokens_out || 0).toLocaleString()} out)</dd>
					<dt>Cost estimate</dt><dd>${escapeMessageHtml(formatCost(s.cost_usd_estimate))}</dd>
					<dt>Visitor email</dt><dd>${email}</dd>
				</dl>
			`;
		}

		function renderMessages(messages) {
			if (messages.length === 0) {
				messagesEl.innerHTML = '<p class="swwp-muted">No messages in this session.</p>';
				return;
			}
			messagesEl.innerHTML = messages.map((m) => {
				const role = m.role === 'user' || m.role === 'assistant' ? m.role : 'system';
				const body = formatMessageBody(String(m.content || ''), role);
				const ts = formatTs(m.created_at);
				return `
					<div class="swwp-review-message swwp-review-message-${role}">
						<div class="swwp-review-message-meta">${escapeMessageHtml(role)} — ${escapeMessageHtml(ts)}</div>
						<div class="swwp-review-message-body">${body}</div>
					</div>
				`;
			}).join('');
		}

		// React to tab-activate by inspecting the sub-route. With no sub, show
		// the list (preserving the current page if we have one); with a sub,
		// load that session's detail view.
		panel.addEventListener('swwp:tab-activate', (e) => {
			const sub = e.detail && e.detail.sub;
			if (sub) {
				loadDetail(sub);
			} else {
				loadList(currentPage);
			}
		});

		reloadBtn.addEventListener('click', () => loadList(currentPage));
		prevBtn.addEventListener('click', () => loadList(currentPage - 1));
		nextBtn.addEventListener('click', () => loadList(currentPage + 1));
	}

	$(function () {
		initColorPickers();
		initTabs();
		initConnectionTab();
		initChatbotTab();
		initGeoTab();
		initUsageTab();
		initSessionsTab();
	});
})(jQuery);
