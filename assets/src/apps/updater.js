/**
 * Dashboard — Updater companion plugin install/uninstall flow.
 *
 * Mirrors the demo.js pattern: status badge, contextual message,
 * and action buttons that hit the REST API.
 */
import { log } from '../lib/logger.js';

export function renderUpdater(data) {
	const panel = document.getElementById('ep-updater-panel');
	if (!panel || !data.updater) return;

	let status = data.updater.status || 'not-installed';

	const badge   = document.getElementById('ep-updater-badge');
	const message = document.getElementById('ep-updater-message');
	const actions = document.getElementById('ep-updater-actions');

	function render() {
		const badgeMap = {
			'not-installed': { cls: 'badge-off',  lbl: 'Not Installed' },
			'installed':     { cls: 'badge-warn', lbl: 'Installed' },
			'active':        { cls: 'badge-on',   lbl: 'Active' },
		};
		const b = badgeMap[status] || badgeMap['not-installed'];
		badge.className = `ep-badge ${b.cls}`;
		badge.innerHTML = `<span class="ep-dot"></span>${b.lbl}`;

		const messages = {
			'not-installed': 'The updater plugin is not installed. Click Install to download it from GitHub and activate it.',
			'installed':     'The updater plugin is installed but not active.',
			'active':        'The updater plugin is running. Theme updates will be checked automatically via GitHub Releases.',
		};
		message.textContent = messages[status] || '';

		let html = '';
		if (status === 'not-installed') {
			html += '<button class="ep-demo-btn ep-demo-btn-primary" id="ep-updater-install">Install &amp; Activate</button>';
		} else if (status === 'installed') {
			html += '<button class="ep-demo-btn ep-demo-btn-primary" id="ep-updater-install">Activate</button>';
			html += '<button class="ep-demo-btn ep-demo-btn-danger" id="ep-updater-uninstall">Remove</button>';
		} else if (status === 'active') {
			html += '<button class="ep-demo-btn ep-demo-btn-danger" id="ep-updater-uninstall">Remove Plugin</button>';
		}
		actions.innerHTML = html;

		const installBtn = document.getElementById('ep-updater-install');
		if (installBtn) {
			installBtn.addEventListener('click', async () => {
				installBtn.disabled = true;
				installBtn.textContent = 'Installing...';
				log.info('[ExamplePress] Updater install started');
				try {
					const res = await fetch(data.updaterInstallUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
					});
					const result = await res.json();
					if (res.ok && result.success) {
						status = result.status;
						log.info(`[ExamplePress] Updater install success: ${result.status}`);
						render();
					} else {
						const msg = result.message || result.data?.message || 'Install failed.';
						message.textContent = msg;
						log.error(`[ExamplePress] Updater install error: ${msg}`);
						installBtn.disabled = false;
						installBtn.textContent = 'Retry Install';
					}
				} catch (err) {
					message.textContent = 'Network error: ' + err.message;
					installBtn.disabled = false;
					installBtn.textContent = 'Retry Install';
				}
			});
		}

		const uninstallBtn = document.getElementById('ep-updater-uninstall');
		if (uninstallBtn) {
			uninstallBtn.addEventListener('click', async () => {
				uninstallBtn.disabled = true;
				uninstallBtn.textContent = 'Removing...';
				log.info('[ExamplePress] Updater uninstall started');
				try {
					const res = await fetch(data.updaterUninstallUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
					});
					const result = await res.json();
					if (res.ok && result.success) {
						status = result.status;
						log.info('[ExamplePress] Updater uninstall success');
						render();
					} else {
						const msg = result.message || result.data?.message || 'Uninstall failed.';
						message.textContent = msg;
						uninstallBtn.disabled = false;
						uninstallBtn.textContent = 'Retry Remove';
					}
				} catch (err) {
					message.textContent = 'Network error: ' + err.message;
					uninstallBtn.disabled = false;
					uninstallBtn.textContent = 'Retry Remove';
				}
			});
		}
	}

	render();
}
