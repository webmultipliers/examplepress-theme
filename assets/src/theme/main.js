/**
 * Theme subpage entry point.
 * Tabs: Features, Config, Design, Dependencies
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { getUrlParams } from '../lib/url.js';
import { initTabs, activateTab, hideTab, updateTabCount } from '../lib/tabs.js';
import { initModal, initEscapeHandler } from '../lib/modal.js';
import { renderFeatures } from './features.js';
import { renderConfig } from './config.js';
import { renderDesign } from './design.js';
import { renderDependencies } from './dependencies.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler();
	initTabs();

	const { features, featureDetails, configFiles, colors, layout, fonts, sizes, dependencies, adminTabs } = data;

	// Hidden tabs.
	const hiddenTabs = (adminTabs && adminTabs.hidden) || [];
	hiddenTabs.forEach(hideTab);

	// Render tabs.
	const urlParams = getUrlParams();
	const featureCount = renderFeatures(features, featureDetails);
	renderConfig(configFiles, urlParams);
	renderDesign(colors || [], layout, fonts || [], sizes || []);
	renderDependencies(dependencies);

	// Tab counts.
	updateTabCount('t-features', featureCount);
	updateTabCount('t-dependencies', dependencies ? dependencies.length : 0);

	// Initial tab from URL.
	activateTab(urlParams.tab || 'features');

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

	log.info('[ExamplePress] Theme page ready.');
});
