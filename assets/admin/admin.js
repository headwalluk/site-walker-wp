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

		function activate(name) {
			tabs.forEach((tab) => {
				tab.classList.toggle('nav-tab-active', tab.dataset.tab === name);
			});
			panels.forEach((panel) => {
				panel.style.display = panel.dataset.panel === name ? 'block' : 'none';
			});
			if (submitBtn) {
				submitBtn.style.display = SETTINGS_API_TABS.has(name) ? '' : 'none';
			}
		}

		const initial = window.location.hash.substring(1) || tabs[0].dataset.tab;
		activate(initial);

		tabs.forEach((tab) => {
			tab.addEventListener('click', (e) => {
				e.preventDefault();
				window.location.hash = tab.dataset.tab;
				activate(tab.dataset.tab);
			});
		});

		window.addEventListener('hashchange', () => {
			activate(window.location.hash.substring(1) || tabs[0].dataset.tab);
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
		keySaveBtn.addEventListener('click', async () => {
			const key = (keyInput.value || '').trim();
			const apiUrl = (apiUrlInput.value || '').trim();
			if (!key) return;

			keySaveBtn.disabled = true;
			setStatus('Saving…');

			const result = await apiCall('POST', '/connection', { admin_key: key, api_url: apiUrl });

			keySaveBtn.disabled = false;

			if (!result.ok) {
				setStatus(errorMessage(result), 'error');
				return;
			}

			// Swap the input for the masked display.
			keyRow.dataset.state = 'saved';
			keyInput.value = '';
			keyInput.hidden = true;
			keySaveBtn.hidden = true;

			// Add the masked display + clear/replace buttons if not already
			// present (first save after a fresh page load).
			if (!keyMaskEl) {
				const code = document.createElement('code');
				code.className = 'swwp-key-mask';
				code.textContent = 'sw_••••••…';
				keyInput.parentNode.insertBefore(code, keyInput);
			}

			const chatbots = result.data.chatbots || [];
			if (chatbots.length === 0) {
				setStatus(STR.noChatbots, 'warning');
				renderChatbot('');
			} else if (chatbots.length === 1) {
				renderChatbot(result.data.chatbot_slug || chatbots[0].slug);
				setStatus(STR.savedAndConnected + ' ' + (chatbots[0].name || chatbots[0].slug), 'success');
			} else {
				renderChatbot(result.data.chatbot_slug || '');
				populatePicker(chatbots, result.data.chatbot_slug || '');
				picker.hidden = false;
				setStatus('Pick which chatbot this WordPress install manages.', 'info');
			}

			// Reload the page after a short delay so the server-rendered key
			// markup (clear/replace buttons) is correct on next interaction.
			// Skipping for now — the in-page state is consistent enough.
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
				populatePicker(result.data.chatbots || [], result.data.chatbot_slug || '');
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
				const n = (result.data.chatbots || []).length;
				setStatus(`${STR.connectionOk} (${n} chatbot${n === 1 ? '' : 's'} visible.)`, 'success');
			});
		}
	}

	$(function () {
		initColorPickers();
		initTabs();
		initConnectionTab();
	});
})(jQuery);
