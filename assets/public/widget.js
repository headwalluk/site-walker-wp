(function () {
	'use strict';

	const config = window.siteWalkerWP;
	if (!config || !config.apiUrl) {
		return;
	}

	const STORAGE = {
		token: `${config.storagePrefix}:${apiHost()}:session-token`,
		probe: `${config.storagePrefix}:${apiHost()}:probe`,
		open: `${config.storagePrefix}:${apiHost()}:open`,
	};

	const PROBE_AVAILABLE = 'available';
	const PROBE_UNAVAILABLE = 'unavailable';

	function apiHost() {
		try {
			return new URL(config.apiUrl).host;
		} catch (e) {
			return 'unknown';
		}
	}

	function readJSON(key) {
		try {
			const raw = localStorage.getItem(key);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	}

	function writeJSON(key, value) {
		try {
			localStorage.setItem(key, JSON.stringify(value));
		} catch (e) {
			/* localStorage unavailable - ignore. */
		}
	}

	function removeKey(key) {
		try {
			localStorage.removeItem(key);
		} catch (e) {
			/* ignore */
		}
	}

	class Widget {
		constructor(root) {
			this.root = root;
			this.launcher = root.querySelector('.swwp-launcher');
			this.panel = root.querySelector('.swwp-panel');
			this.messages = root.querySelector('.swwp-messages');
			this.form = root.querySelector('.swwp-input-row');
			this.input = root.querySelector('.swwp-input');
			this.closeBtn = root.querySelector('.swwp-close');

			this.token = localStorage.getItem(STORAGE.token);
			this.history = [];
			this.busy = false;
			this.expanded = false;

			this.bindEvents();
			this.boot();
		}

		bindEvents() {
			this.launcher.addEventListener('click', () => this.toggle(true));
			this.closeBtn.addEventListener('click', () => this.toggle(false));
			this.form.addEventListener('submit', (e) => this.onSubmit(e));
			this.input.addEventListener('keydown', (e) => {
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					this.form.requestSubmit();
				}
			});
		}

		async boot() {
			// Case 1: We already have a session token. Show the widget and
			// rehydrate when the user opens it.
			if (this.token) {
				this.show();
				const wasOpen = localStorage.getItem(STORAGE.open) === '1';
				if (wasOpen) {
					this.toggle(true);
				}
				return;
			}

			// Case 2 & 3: No active session. Decide whether the API is reachable.
			const probe = readJSON(STORAGE.probe);
			const now = Date.now();

			if (probe && probe.state === PROBE_AVAILABLE && now - probe.at < config.probeTtlMs) {
				this.show();
				return;
			}

			if (probe && probe.state === PROBE_UNAVAILABLE && now - probe.at < config.probeCooldownMs) {
				// Within cooldown - stay hidden.
				return;
			}

			// Probe needed.
			const available = await this.probeApi();
			writeJSON(STORAGE.probe, { state: available ? PROBE_AVAILABLE : PROBE_UNAVAILABLE, at: Date.now() });
			if (available) {
				this.show();
			}
		}

		async probeApi() {
			try {
				const res = await fetch(`${config.apiUrl}/sessions/can-start`, { method: 'GET', mode: 'cors' });
				if (res.status !== 200) return false;
				const body = await res.json().catch(() => null);
				return body && body.ok === true;
			} catch (e) {
				return false;
			}
		}

		show() {
			this.root.hidden = false;
		}

		toggle(open) {
			this.expanded = !!open;
			this.panel.hidden = !this.expanded;
			this.launcher.setAttribute('aria-expanded', this.expanded ? 'true' : 'false');

			if (this.expanded) {
				localStorage.setItem(STORAGE.open, '1');
				this.input.focus();
				this.ensureSession();
			} else {
				removeKey(STORAGE.open);
			}
		}

		async ensureSession() {
			if (this.token) {
				if (this.history.length === 0) {
					await this.loadHistory();
				}
				return;
			}

			this.renderStatus('Connecting…');

			try {
				const res = await fetch(`${config.apiUrl}/sessions`, { method: 'POST', mode: 'cors' });
				if (!res.ok) {
					const body = await res.json().catch(() => ({}));
					this.replaceLastStatus(`Couldn't start chat: ${body.error || res.status}`);
					return;
				}
				const data = await res.json();
				this.token = data.session_token;
				localStorage.setItem(STORAGE.token, this.token);

				this.history = [];
				this.messages.innerHTML = '';
				if (data.welcome_message) {
					this.appendMessage('assistant', data.welcome_message);
				}
			} catch (e) {
				this.replaceLastStatus('Network error - please try again.');
				// A network error here invalidates our cached "available" probe.
				writeJSON(STORAGE.probe, { state: PROBE_UNAVAILABLE, at: Date.now() });
			}
		}

		async loadHistory() {
			this.renderStatus('Loading…');
			try {
				const res = await fetch(`${config.apiUrl}/messages`, {
					headers: { Authorization: `Bearer ${this.token}` },
					mode: 'cors',
				});
				if (res.status === 401) {
					this.discardToken();
					await this.ensureSession();
					return;
				}
				if (!res.ok) {
					this.replaceLastStatus('Could not load conversation.');
					return;
				}
				const data = await res.json();
				this.messages.innerHTML = '';
				this.history = data.messages || [];
				this.history.forEach((m) => this.appendMessage(m.role, m.content));
			} catch (e) {
				this.replaceLastStatus('Network error - please try again.');
			}
		}

		async onSubmit(e) {
			e.preventDefault();
			if (this.busy) {
				return;
			}

			const text = (this.input.value || '').trim();
			if (!text) {
				return;
			}
			if (text.length > config.maxMessageLen) {
				this.appendMessage('system', `Message too long (max ${config.maxMessageLen} characters).`);
				return;
			}
			if (!this.token) {
				await this.ensureSession();
				if (!this.token) {
					return;
				}
			}

			this.busy = true;
			this.input.value = '';
			this.appendMessage('user', text);
			const thinking = this.appendMessage('assistant', '…', { pending: true });

			try {
				const res = await fetch(`${config.apiUrl}/chat`, {
					method: 'POST',
					mode: 'cors',
					headers: {
						'Content-Type': 'application/json',
						Authorization: `Bearer ${this.token}`,
					},
					body: JSON.stringify({ message: text }),
				});
				const body = await res.json().catch(() => ({}));

				if (!res.ok) {
					thinking.remove();
					this.handleChatError(res.status, body);
				} else {
					thinking.textContent = body.reply || '';
					thinking.classList.remove('swwp-pending');
				}
			} catch (e) {
				thinking.remove();
				this.appendMessage('system', 'Network error - please try again.');
			} finally {
				this.busy = false;
			}
		}

		handleChatError(status, body) {
			const code = body && body.error;
			if (status === 401 || code === 'invalid_token') {
				this.discardToken();
				this.appendMessage('system', 'Session expired. Reconnecting…');
				this.ensureSession();
				return;
			}
			if (code === 'context_overflow') {
				this.appendMessage('system', 'This conversation has gotten too long. Please start a new one.');
				return;
			}
			if (code === 'model_error') {
				this.appendMessage('system', 'The assistant had a hiccup. Try again in a moment.');
				return;
			}
			if (code === 'model_not_configured' || code === 'origin_not_allowed') {
				this.appendMessage('system', 'Chat is not available right now.');
				return;
			}
			this.appendMessage('system', `Something went wrong (${code || status}).`);
		}

		discardToken() {
			this.token = null;
			removeKey(STORAGE.token);
			this.history = [];
			this.messages.innerHTML = '';
		}

		renderStatus(text) {
			this.appendMessage('system', text);
		}

		replaceLastStatus(text) {
			const last = this.messages.querySelector('.swwp-message-system:last-child');
			if (last) {
				last.textContent = text;
			} else {
				this.appendMessage('system', text);
			}
		}

		appendMessage(role, content, opts) {
			const el = document.createElement('div');
			el.className = `swwp-message swwp-message-${role}`;
			if (opts && opts.pending) {
				el.classList.add('swwp-pending');
			}
			el.textContent = content;
			this.messages.appendChild(el);
			this.messages.scrollTop = this.messages.scrollHeight;
			return el;
		}
	}

	function init() {
		document.querySelectorAll('.site-walker-wp').forEach((root) => new Widget(root));
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
