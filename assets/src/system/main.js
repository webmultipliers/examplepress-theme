/**
 * System page entry point.
 * Tabs: Health (default), Routes, Blocks, Notifications
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { initTabs, updateTabCount } from '../lib/tabs.js';
import { initModal, initEscapeHandler } from '../lib/modal.js';
import { initNotifications } from '../stores/notifications.js';
import { renderHealth } from './health.js';
import { renderRoutes } from './routes.js';
import { renderBlocks } from './blocks.js';
import { renderNotifications } from './notifications.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler();
	initTabs('health');

	initNotifications(data);

	renderHealth(data.healthChecks);
	const routeCount = renderRoutes();
	const blockCount = renderBlocks(data.blocks);
	renderNotifications(() => {});

	updateTabCount('t-routes', routeCount);
	updateTabCount('t-blocks', blockCount);

	// Copy system report button.
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

	log.info('[ExamplePress] System page ready.');
});
