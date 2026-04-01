/**
 * Dashboard subpage entry point.
 * Tabs: Overview, Notifications, Health, Support
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { getUrlParams } from '../lib/url.js';
import { initTabs, activateTab, hideTab, updateTabCount } from '../lib/tabs.js';
import { initModal, initEscapeHandler } from '../lib/modal.js';
import { initNotifications, $activeNotifications } from '../stores/notifications.js';
import { initHealth } from '../stores/health.js';
import { renderOverview } from './overview.js';
import { renderNotifications } from './notifications.js';
import { renderHealth } from './health.js';
import { renderDemo } from './demo.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler();
	initTabs();

	// Hydrate stores.
	initNotifications(data);
	initHealth(data);

	const { healthChecks, adminTabs } = data;

	// Hidden tabs.
	const hiddenTabs = (adminTabs && adminTabs.hidden) || [];
	hiddenTabs.forEach(hideTab);

	// Tab counts update function.
	function updateCounts() {
		updateTabCount('t-notifications', $activeNotifications.get().length, true);
	}

	// Render tabs.
	renderOverview();
	renderNotifications(updateCounts);
	renderHealth(healthChecks);
	renderDemo(data);

	// Initial counts.
	updateCounts();

	// Initial tab from URL.
	const urlParams = getUrlParams();
	activateTab(urlParams.tab || 'overview');

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

	log.info('[ExamplePress] Dashboard page ready.');
});
