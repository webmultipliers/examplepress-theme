/**
 * Theme page entry point.
 * Tabs: Features (default), Design, Config
 */
import '../css/base.css';

import { initLogger, log } from '../lib/logger.js';
import { initApi } from '../lib/api.js';
import { getUrlParams } from '../lib/url.js';
import { initTabs, updateTabCount } from '../lib/tabs.js';
import { initModal, initEscapeHandler } from '../lib/modal.js';
import { renderFeatures } from './features.js';
import { renderConfig } from './config.js';
import { renderDesign } from './design.js';

document.addEventListener('DOMContentLoaded', () => {
	const data = window.ExamplePressData;
	if (!data) return;

	initLogger(data.devMode);
	initApi(data.nonce);
	initModal();
	initEscapeHandler();
	initTabs('features');

	const { features, featureDetails, configFiles, colors, layout, fonts, sizes } = data;

	const urlParams = getUrlParams();
	const featureCount = renderFeatures(features, featureDetails);
	renderConfig(configFiles, urlParams);
	renderDesign(colors || [], layout, fonts || [], sizes || []);

	updateTabCount('t-features', featureCount);

	log.info('[ExamplePress] Theme page ready.');
});
