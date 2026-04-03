/**
 * Dashboard subpage entry point.
 * Overview, Notifications, Demo
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';

import { initModal, initEscapeHandler } from '../lib/modal.js';
import { initNotifications, $activeNotifications } from '../stores/notifications.js';
import { renderNotifications } from './notifications.js';
import { renderDemo } from './demo.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler();

	// Hydrate stores.
	initNotifications(data);

	// Render.
	renderNotifications(() => {});
	renderDemo(data);

	log.info('[ExamplePress] Dashboard page ready.');
});
