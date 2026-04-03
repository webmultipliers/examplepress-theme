/**
 * Dashboard — Demo companion plugin install/uninstall flow.
 */
import { esc } from '../lib/dom.js';
import { log } from '../lib/logger.js';

export function renderDemo(data) {
	const demoPanel = document.getElementById('ep-demo-panel');
	if (!demoPanel || !data.demo) return;

	let demoStatus = data.demo.status || 'not-installed';

	const demoBadge = document.getElementById('ep-demo-badge');
	const demoMessage = document.getElementById('ep-demo-message');
	const demoActions = document.getElementById('ep-demo-actions');

	function render() {
		const badgeMap = {
			'not-installed': { cls: 'badge-off', lbl: 'Not Installed' },
			'installed':     { cls: 'badge-warn', lbl: 'Installed' },
			'active':        { cls: 'badge-on', lbl: 'Active' },
			'foreign':       { cls: 'badge-err', lbl: 'Conflict' },
		};
		const b = badgeMap[demoStatus] || badgeMap['not-installed'];
		demoBadge.className = `ep-badge ${b.cls}`;
		demoBadge.innerHTML = `<span class="ep-dot"></span>${b.lbl}`;

		const messages = {
			'not-installed': 'The demo companion plugin is not installed. Click Install to copy it from the theme and activate it.',
			'installed':     'The demo plugin is installed but not active.',
			'active':        'The demo companion plugin is running. Visit the frontend to see the routing contract in action.',
			'foreign':       'A plugin named examplepress-demo exists but is not the ExamplePress demo. Remove it manually before installing.',
		};
		demoMessage.textContent = messages[demoStatus] || '';

		let html = '';
		if (demoStatus === 'not-installed') {
			html += '<button class="ep-demo-btn ep-demo-btn-primary" id="ep-demo-install">Install &amp; Activate Demo</button>';
		} else if (demoStatus === 'installed') {
			html += '<button class="ep-demo-btn ep-demo-btn-primary" id="ep-demo-install">Activate</button>';
			html += '<button class="ep-demo-btn ep-demo-btn-danger" id="ep-demo-uninstall">Remove</button>';
		} else if (demoStatus === 'active') {
			html += `<a class="ep-demo-btn ep-demo-btn-primary" href="${esc(window.location.origin)}" target="_blank" rel="noopener">View Frontend &rarr;</a>`;
			html += '<button class="ep-demo-btn ep-demo-btn-danger" id="ep-demo-uninstall">Remove Demo</button>';
		}
		demoActions.innerHTML = html;

		// Bind handlers.
		const installBtn = document.getElementById('ep-demo-install');
		if (installBtn) {
			installBtn.addEventListener('click', async () => {
				installBtn.disabled = true;
				installBtn.textContent = 'Installing...';
				log.info('[ExamplePress] Demo install started');
				try {
					const res = await fetch(data.demoInstallUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
					});
					const result = await res.json();
					if (res.ok && result.success) {
						demoStatus = result.status;
						log.info(`[ExamplePress] Demo install success: ${result.status}`);
						render();
					} else {
						const msg = result.message || result.data?.message || 'Install failed.';
						demoMessage.textContent = msg;
						log.error(`[ExamplePress] Demo install error: ${msg}`);
						installBtn.disabled = false;
						installBtn.textContent = 'Retry Install';
					}
				} catch (err) {
					demoMessage.textContent = 'Network error: ' + err.message;
					installBtn.disabled = false;
					installBtn.textContent = 'Retry Install';
				}
			});
		}

		const uninstallBtn = document.getElementById('ep-demo-uninstall');
		if (uninstallBtn) {
			uninstallBtn.addEventListener('click', async () => {
				uninstallBtn.disabled = true;
				uninstallBtn.textContent = 'Removing...';
				log.info('[ExamplePress] Demo uninstall started');
				try {
					const res = await fetch(data.demoUninstallUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
					});
					const result = await res.json();
					if (res.ok && result.success) {
						demoStatus = result.status;
						log.info(`[ExamplePress] Demo uninstall success`);
						render();
					} else {
						const msg = result.message || result.data?.message || 'Uninstall failed.';
						demoMessage.textContent = msg;
						uninstallBtn.disabled = false;
						uninstallBtn.textContent = 'Retry Remove';
					}
				} catch (err) {
					demoMessage.textContent = 'Network error: ' + err.message;
					uninstallBtn.disabled = false;
					uninstallBtn.textContent = 'Retry Remove';
				}
			});
		}
	}

	render();
}
