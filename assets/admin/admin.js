(function ($) {
	'use strict';

	function initColorPickers() {
		$('.site-walker-wp-color-field').wpColorPicker();
	}

	function initTabs() {
		const tabs = document.querySelectorAll('.site-walker-wp-settings .nav-tab');
		const panels = document.querySelectorAll('.site-walker-wp-settings .tab-panel');

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
		}

		const initial = (window.location.hash.substring(1) || tabs[0].dataset.tab);
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

	$(function () {
		initColorPickers();
		initTabs();
	});
})(jQuery);
