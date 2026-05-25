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

		const apiUrlInput   = panel.querySelector('#swwp-api-url');
		const keyInput      = panel.querySelector('#swwp-admin-key');
		const keySaveBtn    = panel.querySelector('.swwp-key-save');
		const keyClearBtn   = panel.querySelector('.swwp-key-clear');
		const keyReplaceBtn = panel.querySelector('.swwp-key-replace');
		const testBtn       = panel.querySelector('.swwp-connection-test');
		const statusEl      = panel.querySelector('.swwp-status');

		function setStatus(text, kind) {
			statusEl.textContent = text || '';
			statusEl.className = 'swwp-status' + (kind ? ' is-' + kind : '');
		}

		// "Save & connect" — POST /connection with admin key (+ optional URL).
		//
		// Upstream now does origin-scoped matching: it walks the chatbot list
		// and picks the (single) chatbot whose origin allowlist contains this
		// site's URL. Three response shapes the UI cares about:
		//   - ok=true              → saved; reload to refresh server-rendered state
		//   - error=no_origin_match → tell the operator what to add upstream
		//   - any other error       → bubble up via errorMessage()
		//
		// We always reload on success rather than mutating DOM state — the
		// masked key / Clear / Replace buttons are server-conditioned and
		// threading the transition through JS is more error-prone than worth.
		keySaveBtn.addEventListener('click', async () => {
			const key = (keyInput.value || '').trim();
			const apiUrl = (apiUrlInput.value || '').trim();
			if (!key) return;

			keySaveBtn.disabled = true;
			setStatus('Saving…');

			const result = await apiCall('POST', '/connection', { admin_key: key, api_url: apiUrl });

			if (!result.ok) {
				keySaveBtn.disabled = false;
				if (result.error === 'no_origin_match') {
					const detail = result.detail || {};
					const origin = detail.expected_origin || config.expectedOrigin || '';
					setStatus(STR.noOriginMatch.replace(/%s/g, origin), 'error');
				} else {
					setStatus(errorMessage(result), 'error');
				}
				return;
			}

			const name = result.data.chatbot_name || result.data.chatbot_slug;
			const matchCount = result.data.match_count || 1;
			const msg = matchCount > 1
				? `${STR.savedAndConnected} ${name} (warning: ${matchCount} chatbots matched this origin — picked the first). Reloading…`
				: `${STR.savedAndConnected} ${name}. Reloading…`;
			setStatus(msg, matchCount > 1 ? 'warning' : 'success');

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

		// "Test connection" — verifies the key is still valid AND that the
		// saved chatbot's origin allowlist still includes this site. The
		// allowlist could be edited out from under us via ./bin/sw, so
		// surfacing a mismatch here is a useful diagnostic.
		if (testBtn) {
			testBtn.addEventListener('click', async () => {
				setStatus('Testing…');
				const result = await apiCall('POST', '/connection/test');
				if (!result.ok) {
					setStatus(errorMessage(result), 'error');
					return;
				}
				const data = result.data || {};
				if (data.chatbot_slug && data.origin_match) {
					setStatus(`${STR.connectionOk} (${data.chatbot_slug} ⇄ ${data.expected_origin})`, 'success');
				} else if (data.chatbot_slug && !data.origin_match) {
					setStatus(STR.originMismatch, 'warning');
				} else {
					setStatus(STR.notConnected, 'warning');
				}
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
	// ---------------------------------------------------------------------
	// Schedule grid helpers (used by initChatbotTab for `availability`).
	//
	// Upstream JSON shape: { schedule: { mon: ["09:00-17:00"], ... } } or
	// null for "always open". Missing or empty per-day arrays = closed.
	// `24:00` is accepted as end-of-day; `close <= open` is rejected
	// upstream — we do a client-side check too for a friendlier error.
	// ---------------------------------------------------------------------

	const DAYS = [
		{ key: 'mon', label: 'Monday' },
		{ key: 'tue', label: 'Tuesday' },
		{ key: 'wed', label: 'Wednesday' },
		{ key: 'thu', label: 'Thursday' },
		{ key: 'fri', label: 'Friday' },
		{ key: 'sat', label: 'Saturday' },
		{ key: 'sun', label: 'Sunday' },
	];

	const TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d$|^24:00$/;

	function validWindow(open, close) {
		if (!TIME_PATTERN.test(open) || !TIME_PATTERN.test(close)) return false;
		// Convert HH:MM to minute count; 24:00 → 1440.
		const toMin = (s) => {
			const [h, m] = s.split(':').map(Number);
			return h * 60 + m;
		};
		return toMin(close) > toMin(open);
	}

	function renderScheduleGrid(gridEl, schedule) {
		const sched = (schedule && schedule.schedule) || {};
		gridEl.innerHTML = '';
		const table = document.createElement('table');
		table.className = 'widefat swwp-availability-table';
		const tbody = document.createElement('tbody');
		DAYS.forEach(({ key, label }) => {
			const row = document.createElement('tr');
			row.dataset.day = key;
			row.innerHTML = `
				<th scope="row" class="swwp-day-label">${label}</th>
				<td class="swwp-day-windows"></td>
				<td class="swwp-day-actions">
					<button type="button" class="button-link swwp-window-add">+ Add window</button>
				</td>
			`;
			tbody.appendChild(row);
			const windowsEl = row.querySelector('.swwp-day-windows');
			const windows = Array.isArray(sched[key]) ? sched[key] : [];
			windows.forEach((w) => addWindowRow(windowsEl, w));
			if (windows.length === 0) {
				markDayClosed(windowsEl);
			}
		});
		table.appendChild(tbody);
		gridEl.appendChild(table);
	}

	function addWindowRow(windowsEl, value) {
		// Remove any "closed" marker first.
		const closedMarker = windowsEl.querySelector('.swwp-day-closed-marker');
		if (closedMarker) closedMarker.remove();

		const [open, close] = (typeof value === 'string' ? value.split('-') : ['09:00', '17:00'])
			.map((s) => (s || '').trim());
		const span = document.createElement('span');
		span.className = 'swwp-window';
		span.innerHTML = `
			<input type="text" class="swwp-window-open" maxlength="5" placeholder="HH:MM" value="${open || '09:00'}" />
			<span class="swwp-window-dash">–</span>
			<input type="text" class="swwp-window-close" maxlength="5" placeholder="HH:MM" value="${close || '17:00'}" />
			<button type="button" class="button-link swwp-window-remove" aria-label="Remove window">×</button>
		`;
		windowsEl.appendChild(span);
	}

	function markDayClosed(windowsEl) {
		if (windowsEl.querySelector('.swwp-day-closed-marker')) return;
		const m = document.createElement('span');
		m.className = 'swwp-day-closed-marker swwp-muted';
		m.textContent = '(chatbot offline)';
		windowsEl.appendChild(m);
	}

	// Read the grid back into the upstream shape. Returns null for "always
	// open" (no day has any windows OR mode is `always`); returns the
	// object otherwise. Throws on the first invalid window so the caller
	// can surface a useful error.
	function collectSchedule(gridEl, mode) {
		if (mode === 'always') return null;

		const out = {};
		let anyWindows = false;
		DAYS.forEach(({ key }) => {
			const row = gridEl.querySelector(`tr[data-day="${key}"]`);
			if (!row) return;
			const windows = row.querySelectorAll('.swwp-window');
			const list = [];
			windows.forEach((w) => {
				const open = w.querySelector('.swwp-window-open').value.trim();
				const close = w.querySelector('.swwp-window-close').value.trim();
				if (open === '' && close === '') return; // skip blank rows
				if (!validWindow(open, close)) {
					throw new Error(`Invalid window on ${key}: ${open || '?'}–${close || '?'}. Use HH:MM with close after open (24:00 is end-of-day).`);
				}
				list.push(`${open}-${close}`);
				anyWindows = true;
			});
			if (list.length > 0) out[key] = list;
		});

		// No day got any windows — treat as "always open" rather than the
		// (likely-unintended) "always closed" interpretation. UI sets the
		// mode radio back to `always` so this is visible to the operator.
		if (!anyWindows) return null;
		return { schedule: out };
	}

	function initChatbotTab() {
		const panel = document.querySelector('#chatbot-panel');
		if (!panel) return;

		const notConfigured = panel.querySelector('.swwp-not-configured');
		const loadingEl     = panel.querySelector('.swwp-tab-loading');
		const formEl        = panel.querySelector('.swwp-tab-form');
		const saveBtn       = panel.querySelector('.swwp-chatbot-save');
		const reloadBtn     = panel.querySelector('.swwp-chatbot-reload');
		const statusEl      = panel.querySelector('.swwp-status');
		const tzInput       = panel.querySelector('#swwp-chatbot-tz');
		const tzUseSiteBtn  = panel.querySelector('.swwp-tz-use-site');
		const availGrid     = panel.querySelector('.swwp-availability-grid');
		const availModeRadios = panel.querySelectorAll('input[name="swwp-availability-mode"]');

		// Surface the "Use this site's timezone" button only when the WP host
		// actually advertised an IANA zone (not a UTC offset).
		if (tzUseSiteBtn && config.wpTimezone) {
			tzUseSiteBtn.hidden = false;
			tzUseSiteBtn.title = `Set to ${config.wpTimezone}`;
			tzUseSiteBtn.textContent = `Use this site's timezone (${config.wpTimezone})`;
			tzUseSiteBtn.addEventListener('click', () => {
				tzInput.value = config.wpTimezone;
			});
		}

		// Availability mode radios drive the grid's visibility. Switching to
		// `always` doesn't wipe the grid content (so the operator can toggle
		// back without losing edits); we just send `null` on save.
		function setAvailabilityMode(mode) {
			availModeRadios.forEach((r) => { r.checked = r.value === mode; });
			availGrid.hidden = mode !== 'schedule';
		}

		availModeRadios.forEach((r) => {
			r.addEventListener('change', () => setAvailabilityMode(r.value));
		});

		// Delegated handler for + / × buttons inside the grid.
		availGrid.addEventListener('click', (e) => {
			const addBtn = e.target.closest('.swwp-window-add');
			const removeBtn = e.target.closest('.swwp-window-remove');
			if (addBtn) {
				const row = addBtn.closest('tr');
				const windowsEl = row && row.querySelector('.swwp-day-windows');
				if (windowsEl) addWindowRow(windowsEl);
				return;
			}
			if (removeBtn) {
				const win = removeBtn.closest('.swwp-window');
				const windowsEl = win && win.parentElement;
				if (win) win.remove();
				if (windowsEl && !windowsEl.querySelector('.swwp-window')) {
					markDayClosed(windowsEl);
				}
			}
		});

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

			const data = result.data || {};
			populateFields(formEl, data);

			// Availability + mode radio.
			renderScheduleGrid(availGrid, data.availability);
			setAvailabilityMode(data.availability ? 'schedule' : 'always');

			formEl.hidden = false;
		}

		saveBtn.addEventListener('click', async () => {
			const body = collectFields(formEl);
			// Upstream treats null as "clear" for nullable string fields. An
			// empty string is likely to be rejected as validation_failed, so
			// translate empties on the way out.
			['welcome_message', 'persona', 'timezone'].forEach((k) => {
				if (body[k] === '') body[k] = null;
			});

			// Availability — selected mode determines whether we send `null`
			// or the {schedule: {...}} object.
			const mode = (panel.querySelector('input[name="swwp-availability-mode"]:checked') || {}).value || 'always';
			let availability;
			try {
				availability = collectSchedule(availGrid, mode);
			} catch (e) {
				setStatus(e.message, 'error');
				return;
			}
			body.availability = availability;

			saveBtn.disabled = true;
			setStatus('Saving…');
			const result = await apiCall('PATCH', '/chatbot', body);
			saveBtn.disabled = false;
			if (!result.ok) {
				setStatus(errorMessage(result), 'error');
				return;
			}
			const fresh = result.data || {};
			populateFields(formEl, fresh);
			renderScheduleGrid(availGrid, fresh.availability);
			setAvailabilityMode(fresh.availability ? 'schedule' : 'always');
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
			// Three segments per row: combined (top-level fields) + the
			// nested customer / admin sub-objects (M21+). The cell knows
			// which segment via data-segment; missing sub-objects render
			// as 0 / '$0.00' rather than '—' so the table stays uniform.
			const segments = {
				combined: data,
				customer: data.customer || {},
				admin:    data.admin || {},
			};
			panel.querySelectorAll('.swwp-usage-value').forEach((cell) => {
				const field = cell.dataset.field;
				const segment = cell.dataset.segment || 'combined';
				const source = segments[segment] || {};
				const value = source[field];
				if (field === 'cost_usd') {
					cell.textContent = formatCost(typeof value === 'number' ? value : 0);
				} else {
					cell.textContent = formatInt(typeof value === 'number' ? value : 0);
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

	// Message-content formatter shims. Both delegate to the shared module
	// in assets/shared/formatter.js so the Sessions tab renders messages
	// the same way the front-end widget does (markdown headings, lists,
	// emphasis, inline code, same-host + trusted-host auto-linking).
	// Kept as local names so the existing `${escapeMessageHtml(…)}` and
	// `formatMessageBody(…)` call sites don't need to change.
	function escapeMessageHtml(s) {
		return window.SiteWalkerFormatter.escape(s);
	}

	function formatMessageBody(raw, role) {
		return window.SiteWalkerFormatter.format(raw, role, {
			trustedHosts: config.trustedHosts,
		});
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
