/**
 * Build subpage entry point.
 * Tabs: Apps, Connections
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';

import { initTabs } from '../lib/tabs.js';
import { initModal, closeAppModal, initEscapeHandler } from '../lib/modal.js';
import { initApps } from '../stores/apps.js';
import { initConnections } from '../stores/connections.js';
import { initAppsTable, renderAppsTable, initAppsOutsideClick } from './apps.js';
import { initScaffold } from './scaffold.js';
import { initTroyModal } from './troy-modal.js';
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

	// Close modals via close buttons.
	document.querySelectorAll('[data-modal]').forEach(btn => {
		btn.addEventListener('click', () => closeAppModal(btn.dataset.modal));
	});

	// Render tabs.
	initAppsTable();
	renderAppsTable();
	initScaffold(data);
	initTroyModal(data);
	initAppsOutsideClick();
	initConnectionsUI();

	log.info('[ExamplePress] Apps page ready.');
});
