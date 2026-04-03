/**
 * Routing subpage entry point.
 * Tabs: Routes, Blocks, Library
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { initTabs, updateTabCount } from '../lib/tabs.js';
import { initModal, initEscapeHandler } from '../lib/modal.js';
import { renderBlocks } from '../build/blocks.js';
import { renderRoutes } from '../build/routes.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler();
	initTabs();

	const { blocks } = data;

	const blockCount = renderBlocks(blocks);
	const routeCount = renderRoutes();

	updateTabCount('t-blocks', blockCount);
	updateTabCount('t-routes', routeCount);

	log.info('[ExamplePress] Routing page ready.');
});
