/**
 * Reference subpage entry point.
 * Tabs: Docs, Navigation
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { getUrlParams } from '../lib/url.js';
import { initTabs, activateTab, hideTab, updateTabCount } from '../lib/tabs.js';
import { initModal, initEscapeHandler } from '../lib/modal.js';
import { renderDocs, renderHooks } from './docs.js';
import { renderNavigation } from './navigation.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler();
	initTabs();

	const { docs, hooks, navigation, adminTabs } = data;

	// Hidden tabs.
	const hiddenTabs = (adminTabs && adminTabs.hidden) || [];
	hiddenTabs.forEach(hideTab);

	// Render.
	renderDocs(docs || []);
	renderHooks(hooks || []);
	renderNavigation(navigation);

	// Tab counts.
	const navMenuCount = (navigation && navigation.menus) ? navigation.menus.length : 0;
	updateTabCount('t-navigation', navMenuCount);

	// Initial tab from URL.
	const urlParams = getUrlParams();
	activateTab(urlParams.tab || 'docs');

	// Copy system report.
	const copyBtn = document.getElementById('ep-copy-report');
	if (copyBtn) {
		copyBtn.addEventListener('click', () => {
			const report = JSON.stringify(data, null, 2);
			navigator.clipboard.writeText(report).then(() => {
				copyBtn.classList.add('copied');
				copyBtn.textContent = 'Copied!';
				setTimeout(() => {
					copyBtn.classList.remove('copied');
					copyBtn.textContent = 'Copy System Report';
				}, 2000);
			});
		});
	}

	log.info('[ExamplePress] Reference page ready.');
});
