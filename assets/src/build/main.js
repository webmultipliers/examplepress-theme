/**
 * Build subpage entry point.
 * Tabs: Build, Connections, Routes, Blocks, Library
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { getUrlParams } from '../lib/url.js';
import { initTabs, activateTab, hideTab, updateTabCount } from '../lib/tabs.js';
import { initModal, closeAppModal, initEscapeHandler } from '../lib/modal.js';
import { initApps } from '../stores/apps.js';
import { initConnections } from '../stores/connections.js';
import { initAppsTable, renderAppsTable, initAppsOutsideClick } from './apps.js';
import { initScaffold } from './scaffold.js';
import { initTroyModal } from './troy-modal.js';
import { renderBlocks } from './blocks.js';
import { renderRoutes } from './routes.js';
import { initConnectionsUI } from './connections.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler(['ep-apps-scaffold-modal', 'ep-apps-troy-modal', 'ep-apps-codespace-modal']);
	initTabs();

	// Hydrate stores.
	initApps(data);
	initConnections(data);

	const { blocks, adminTabs } = data;

	// Hidden tabs.
	const hiddenTabs = (adminTabs && adminTabs.hidden) || [];
	hiddenTabs.forEach(hideTab);

	// Close modals via close buttons.
	document.querySelectorAll('[data-modal]').forEach(btn => {
		btn.addEventListener('click', () => closeAppModal(btn.dataset.modal));
	});

	// Render tabs.
	initAppsTable();
	renderAppsTable();
	initScaffold(data);
	initTroyModal(data);
	const blockCount = renderBlocks(blocks);
	const routeCount = renderRoutes();
	initAppsOutsideClick();
	initConnectionsUI();

	// Tab counts.
	updateTabCount('t-blocks', blockCount);
	if (routeCount) updateTabCount('t-routes', routeCount);

	// Initial tab from URL.
	const urlParams = getUrlParams();
	activateTab(urlParams.tab || 'build');

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

	log.info('[ExamplePress] Build page ready.');
});
