/**
 * System subpage entry point.
 * Tabs: Health, Support
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { initTabs } from '../lib/tabs.js';
import { initHealth } from '../stores/health.js';
import { renderHealth } from '../dashboard/health.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initTabs();

	initHealth(data);
	renderHealth(data.healthChecks);

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

	log.info('[ExamplePress] System page ready.');
});
