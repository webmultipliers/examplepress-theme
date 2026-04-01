/**
 * Reference subpage entry point.
 * Tabs: Docs, Navigation
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';

import { initTabs, hideTab, updateTabCount } from '../lib/tabs.js';
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

	log.info('[ExamplePress] Reference page ready.');
});
