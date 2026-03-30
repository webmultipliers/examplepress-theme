/**
 * ExamplePress Settings Page
 *
 * Renders the read-only configuration dashboard from data
 * localized via wp_add_inline_script (window.ExamplePressData).
 */
document.addEventListener('DOMContentLoaded', () => {
	if (!window.ExamplePressData) {
		return;
	}

	const version = window.ExamplePressData.themeVersion || '?';
	const devMode = !!window.ExamplePressData.devMode;

	// Gated logging — only emits to console when EP_DEV_MODE is active.
	const log = {
		info: (...args) => devMode && console.info(...args),
		warn: (...args) => devMode && console.warn(...args),
		error: (...args) => console.error(...args), // errors always log
	};

	log.info(`[ExamplePress] Settings page initializing — v${version}`);

	const {
		features,
		colors,
		layout,
		fonts,
		sizes,
		blocks,
		dependencies,
		notifications,
		configFiles,
		healthChecks,
		docs,
		hooks,
		navigation,
		adminTabs,
		featureDetails,
	} = window.ExamplePressData;

	let archived = window.ExamplePressData.archived || [];

	/* ── URL Routing ───────────────────────────────────────────────── */

	function getUrlParams() {
		const params = new URLSearchParams(window.location.search);
		return {
			tab: params.get('tab'),
			section: params.get('section'),
		};
	}

	function setUrlParams(tab, section) {
		const params = new URLSearchParams(window.location.search);
		params.set('page', 'examplepress-settings');
		if (tab) {
			params.set('tab', tab);
		} else {
			params.delete('tab');
		}
		if (section) {
			params.set('section', section);
		} else {
			params.delete('section');
		}
		history.replaceState(null, '', '?' + params.toString());
	}

	function activateTab(tabId) {
		const tab = document.querySelector(`.ep-tab[data-tab-id="${tabId}"]`);
		if (!tab) return;
		document.querySelectorAll('.ep-tab').forEach(t => t.setAttribute('aria-selected', 'false'));
		document.querySelectorAll('.ep-panel').forEach(p => p.setAttribute('aria-hidden', 'true'));
		tab.setAttribute('aria-selected', 'true');
		const panel = document.getElementById(tab.getAttribute('aria-controls'));
		if (panel) panel.setAttribute('aria-hidden', 'false');
		setUrlParams(tabId);
		log.info(`[ExamplePress] Tab activated: ${tabId}`);
	}

	/* ── Tab switching ──────────────────────────────────────────────── */

	document.querySelectorAll('.ep-tab').forEach(tab => {
		tab.addEventListener('click', () => {
			const tabId = tab.dataset.tabId;
			if (tabId) {
				activateTab(tabId);
			}
		});
	});

	/* ── Tab visibility ─────────────────────────────────────────────── */

	const hiddenTabs = (adminTabs && adminTabs.hidden) || [];
	if (hiddenTabs.length) {
		hiddenTabs.forEach(tabId => {
			const tabBtn = document.querySelector(`.ep-tab[data-tab-id="${tabId}"]`);
			if (tabBtn) tabBtn.style.display = 'none';
			const panelId = tabBtn ? tabBtn.getAttribute('aria-controls') : `p-${tabId}`;
			const panel = document.getElementById(panelId);
			if (panel) panel.style.display = 'none';
		});
		log.info(`[ExamplePress] Hidden tabs: ${hiddenTabs.join(', ')}`);
	}

	/* ── Initial tab from URL ──────────────────────────────────────── */

	const urlParams = getUrlParams();
	if (urlParams.tab) {
		activateTab(urlParams.tab);
	} else {
		activateTab('overview');
	}

	/* ── Copy system report ─────────────────────────────────────────── */

	const copyBtn = document.getElementById('ep-copy-report');
	if (copyBtn) {
		copyBtn.addEventListener('click', () => {
			const report = JSON.stringify(window.ExamplePressData, null, 2);
			navigator.clipboard.writeText(report).then(() => {
				copyBtn.classList.add('copied');
				copyBtn.textContent = 'Copied!';
				log.info(`[ExamplePress] System report copied (${report.length} chars)`);
				setTimeout(() => {
					copyBtn.classList.remove('copied');
					copyBtn.textContent = 'Copy System Report';
				}, 2000);
			});
		});
	}

	/* ── Render helpers ─────────────────────────────────────────────── */

	function badge(on, label) {
		const cls = on ? 'badge-on' : 'badge-off';
		return `<span class="ep-badge ${cls}"><span class="ep-dot"></span>${label || (on ? 'Enabled' : 'Disabled')}</span>`;
	}

	function srcTag(src, detail) {
		const cls = src === 'json' ? 'src-json' : src === 'php' ? 'src-php' : '';
		const label = src === 'php' ? 'filter' : src;
		const title = detail ? ` title="${esc(detail)}"` : '';
		return `<span class="ep-src ${cls}"${title}>${label}</span>`;
	}

	function esc(str) {
		if (str == null) return '';
		const d = document.createElement('div');
		d.textContent = String(str);
		return d.innerHTML;
	}

	function featureTable(containerId, items) {
		const el = document.getElementById(containerId);
		if (!el || !items || !items.length) return;
		let html = '<div class="ep-row ep-row-head ep-cols-3"><div class="ep-th">Feature</div><div class="ep-th">Status</div><div class="ep-th">Source</div></div>';
		items.forEach(f => {
			const hasDetail = featureDetails && featureDetails[f.id];
			const rowCls = hasDetail ? 'ep-row ep-cols-3 ep-row-clickable' : 'ep-row ep-cols-3';
			const dataAttr = hasDetail ? ` data-feature-id="${esc(f.id)}"` : '';
			html += `<div class="${rowCls}"${dataAttr}>
				<div class="ep-td-label"><span class="ep-name">${esc(f.name)}</span><span class="ep-id">${esc(f.id)}</span>${f.opts ? `<span class="ep-opt"><em>${esc(f.opts)}</em></span>` : ''}</div>
				<div>${badge(f.on)}</div>
				<div>${srcTag(f.src, f.srcDetail)}</div>
			</div>`;
		});
		el.innerHTML = html;
	}

	function highlightJson(obj) {
		return JSON.stringify(obj, null, 2)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"([^"]+)":/g, '<span class="j-key">"$1"</span>:')
			.replace(/: "(.*?)"/g, ': <span class="j-str">"$1"</span>')
			.replace(/: (true|false)/g, ': <span class="j-bool">$1</span>')
			.replace(/: (\d+\.?\d*)/g, ': <span class="j-num">$1</span>')
			.replace(/: (null)/g, ': <span class="j-null">$1</span>');
	}

	function healthTable(containerId, items) {
		const el = document.getElementById(containerId);
		if (!el || !items || !items.length) return;
		let html = '<div class="ep-row ep-row-head ep-cols-health"><div class="ep-th">Check</div><div class="ep-th">Status</div><div class="ep-th">Requirement</div></div>';
		items.forEach(h => {
			const cls = h.status === 'pass' ? 'badge-on' : h.status === 'warn' ? 'badge-warn' : h.status === 'fail' ? 'badge-err' : 'badge-info';
			const lbl = h.status === 'pass' ? 'Pass' : h.status === 'warn' ? 'Warning' : h.status === 'fail' ? 'Fail' : 'Info';
			const noteColor = h.status === 'fail' ? 'var(--red)' : 'var(--amber)';
			html += `<div class="ep-row ep-cols-health">
				<div class="ep-td-label"><span class="ep-name">${esc(h.name)}</span><span class="ep-id">${esc(h.detail)}</span>${h.note ? `<span class="ep-desc-small" style="color:${noteColor}">${esc(h.note)}</span>` : ''}</div>
				<div><span class="ep-badge ${cls}"><span class="ep-dot"></span>${lbl}</span></div>
				<div><span class="ep-id">${esc(h.req)}</span></div>
			</div>`;
		});
		el.innerHTML = html;
	}

	/* ── Features tab ───────────────────────────────────────────────── */

	featureTable('tbl-theme-support', features['theme-support']);
	featureTable('tbl-editor', features.editor);
	featureTable('tbl-guards', features.guards);
	featureTable('tbl-admin', features.admin);
	featureTable('tbl-options', features.options);
	featureTable('tbl-design-features', features.design);

	// Bind feature row click handlers for modal.
	document.querySelectorAll('.ep-row-clickable[data-feature-id]').forEach(row => {
		row.addEventListener('click', () => {
			const id = row.dataset.featureId;
			if (featureDetails && featureDetails[id]) {
				openFeatureModal(id);
			}
		});
	});

	const featureCount = Object.values(features).reduce((sum, arr) => sum + (arr ? arr.length : 0), 0);
	const catCount = Object.keys(features).length;
	log.info(`[ExamplePress] Features rendered: ${featureCount} features across ${catCount} categories`);

	/* ── Feature Detail Modal ──────────────────────────────────────── */

	const modalOverlay = document.getElementById('ep-feature-modal');
	const modalTitle = document.getElementById('ep-modal-title');
	const modalId = document.getElementById('ep-modal-id');
	const modalBody = document.getElementById('ep-modal-body');
	const modalClose = document.getElementById('ep-modal-close');

	function findFeatureData(featureId) {
		for (const cat of Object.values(features)) {
			if (!cat) continue;
			const found = cat.find(f => f.id === featureId);
			if (found) return found;
		}
		return null;
	}

	function openFeatureModal(featureId) {
		if (!modalOverlay || !featureDetails) return;
		const detail = featureDetails[featureId];
		const fData = findFeatureData(featureId);
		if (!detail) return;

		modalTitle.textContent = fData ? fData.name : featureId;
		modalId.textContent = featureId;

		let html = '';

		// Status + Source
		html += '<div class="ep-modal-status">';
		if (fData) {
			html += badge(fData.on);
			html += ' ' + srcTag(fData.src, fData.srcDetail);
		}
		html += '</div>';

		// Description
		if (detail.description) {
			html += '<div class="ep-modal-section">';
			html += '<div class="ep-modal-section-title">What It Does</div>';
			html += `<p class="ep-modal-text">${esc(detail.description)}</p>`;
			html += '</div>';
		}

		// Technical
		if (detail.technical) {
			html += '<div class="ep-modal-section">';
			html += '<div class="ep-modal-section-title">Technical Detail</div>';
			html += `<p class="ep-modal-text">${esc(detail.technical)}</p>`;
			html += '</div>';
		}

		// Override Example
		if (detail.override) {
			html += '<div class="ep-modal-section">';
			html += '<div class="ep-modal-section-title">Override Example</div>';
			html += `<pre class="ep-modal-code">${esc(detail.override)}</pre>`;
			html += '</div>';
		}

		// Current Options
		if (fData && fData.opts) {
			html += '<div class="ep-modal-section">';
			html += '<div class="ep-modal-section-title">Current Options</div>';
			html += `<p class="ep-modal-text"><span class="ep-modal-opt">${esc(fData.opts)}</span></p>`;
			html += '</div>';
		}

		// Filter Hook
		html += '<div class="ep-modal-section">';
		html += '<div class="ep-modal-section-title">Filter Hooks</div>';
		html += `<p class="ep-modal-text">Toggle: <code class="ep-modal-filter">examplepress_feature_${esc(featureId)}</code></p>`;
		html += '</div>';

		modalBody.innerHTML = html;
		modalOverlay.style.display = '';
		log.info(`[ExamplePress] Feature modal opened: ${featureId}`);
	}

	function closeFeatureModal() {
		if (modalOverlay) modalOverlay.style.display = 'none';
	}

	if (modalClose) modalClose.addEventListener('click', closeFeatureModal);
	if (modalOverlay) {
		modalOverlay.addEventListener('click', (e) => {
			if (e.target === modalOverlay) closeFeatureModal();
		});
	}
	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape') {
			closeFeatureModal();
			closeBuildModal();
		}
	});

	/* ── Generic Modal Helper ──────────────────────────────────────── */

	function openGenericModal(title, subtitle, bodyHtml) {
		if (!modalOverlay) return;
		modalTitle.textContent = title;
		modalId.textContent = subtitle || '';
		modalBody.innerHTML = bodyHtml;
		modalOverlay.style.display = '';
	}

	/* ── Build Modal ───────────────────────────────────────────────── */

	const buildModal = document.getElementById('ep-build-modal');
	const buildModalClose = document.getElementById('ep-build-modal-close');
	const buildOpenBtn = document.getElementById('ep-build-open-modal');

	function openBuildModal() {
		if (buildModal) buildModal.style.display = '';
	}

	function closeBuildModal() {
		if (buildModal) buildModal.style.display = 'none';
	}

	if (buildOpenBtn) buildOpenBtn.addEventListener('click', openBuildModal);
	if (buildModalClose) buildModalClose.addEventListener('click', closeBuildModal);
	if (buildModal) {
		buildModal.addEventListener('click', (e) => {
			if (e.target === buildModal) closeBuildModal();
		});
	}

	/* ── Design tab ─────────────────────────────────────────────────── */

	const colorsGrid = document.getElementById('colors-grid');
	if (colorsGrid && colors.length) {
		colorsGrid.innerHTML = colors.map(c => `
			<div class="ep-color-card">
				<div class="ep-color-swatch" style="background:${c.color}"></div>
				<div class="ep-color-info">
					<div class="ep-color-name">${esc(c.name)}</div>
					<div class="ep-color-meta"><span class="ep-color-hex">${esc(c.color)}</span><span class="ep-color-slug">${esc(c.slug)}</span></div>
				</div>
			</div>`).join('');
		log.info(`[ExamplePress] Design: ${colors.length} colors loaded`);
	}

	const layoutEl = document.getElementById('layout-visual');
	if (layoutEl && layout) {
		const wideNum = parseFloat(layout.wideSize) || 1200;
		const contentNum = parseFloat(layout.contentSize) || 800;
		const ratio = wideNum > 0 ? ((contentNum / wideNum) * 100).toFixed(1) : 66.7;
		layoutEl.innerHTML = `
			<div class="ep-layout-bar bar-wide"><span class="ep-layout-lbl">Wide &mdash; ${esc(layout.wideSize)}</span></div>
			<div style="display:flex;justify-content:center;">
				<div class="ep-layout-bar bar-content" style="width:${ratio}%"><span class="ep-layout-lbl">Content &mdash; ${esc(layout.contentSize)}</span></div>
			</div>`;
	}

	const typeStack = document.getElementById('type-stack');
	if (typeStack && fonts.length) {
		typeStack.innerHTML = fonts.map(f => `
			<div class="ep-type-row">
				<div class="ep-type-meta"><span class="ep-type-name">${esc(f.name)}</span><span class="ep-type-slug">${esc(f.slug)}</span></div>
				<div class="ep-type-preview" style="font-family:${f.stack};">The quick brown fox jumps over the lazy dog &mdash; 0123456789</div>
			</div>`).join('');
	}

	const sizeScale = document.getElementById('size-scale');
	if (sizeScale && sizes.length) {
		sizeScale.innerHTML = sizes.map(s => `
			<div class="ep-size-item">
				<span class="ep-size-sample" style="font-size:${s.size}">Aa</span>
				<span class="ep-size-label">${esc(s.name)}<br>${esc(s.slug)} &middot; ${esc(s.size)}</span>
			</div>`).join('');
	}

	/* ── Blocks tab ─────────────────────────────────────────────────── */

	const tblBlocks = document.getElementById('tbl-blocks');
	if (tblBlocks && blocks.length) {
		let html = '<div class="ep-row ep-row-head ep-cols-blocks"><div class="ep-th">Block</div><div class="ep-th">Category</div><div class="ep-th">Source</div><div class="ep-th">Type</div></div>';
		// Group by namespace (text before the /).
		const namespaces = [...new Set(blocks.map(b => b.name.split('/')[0]))];
		namespaces.forEach(ns => {
			html += `<div class="ep-block-cat">${esc(ns)}</div>`;
			blocks.filter(b => b.name.startsWith(ns + '/')).forEach(b => {
				const typeCls = b.type === 'template' ? 'badge-on' : b.type === 'system' ? 'badge-info' : 'badge-off';
				html += `<div class="ep-row ep-cols-blocks ep-row-clickable" data-block-name="${esc(b.name)}">
					<div class="ep-td-label"><span class="ep-name">${esc(b.title)}</span><span class="ep-id">${esc(b.name)}</span></div>
					<div><span class="ep-id">${esc(b.cat)}</span></div>
					<div><span class="ep-src">${esc(b.source)}</span></div>
					<div><span class="ep-badge ${typeCls}"><span class="ep-dot"></span>${esc(b.type)}</span></div>
				</div>`;
			});
		});
		tblBlocks.innerHTML = html;
		log.info(`[ExamplePress] Blocks: ${blocks.length} blocks across ${namespaces.length} namespaces`);

		// Block detail modals.
		tblBlocks.querySelectorAll('.ep-row-clickable[data-block-name]').forEach(row => {
			row.addEventListener('click', () => {
				const name = row.dataset.blockName;
				const b = blocks.find(bl => bl.name === name);
				if (!b) return;
				const typeCls = b.type === 'template' ? 'badge-on' : b.type === 'system' ? 'badge-info' : 'badge-off';
				let body = '<div class="ep-modal-status">';
				body += `<span class="ep-badge ${typeCls}"><span class="ep-dot"></span>${esc(b.type)}</span>`;
				body += `<span class="ep-src">${esc(b.source)}</span>`;
				body += '</div>';
				body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Category</div>';
				body += `<p class="ep-modal-text">${esc(b.cat)}</p></div>`;
				body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Namespace</div>';
				body += `<p class="ep-modal-text"><code class="ep-modal-filter">${esc(b.name.split('/')[0])}</code></p></div>`;
				body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Block Name</div>';
				body += `<pre class="ep-modal-code">${esc(b.name)}</pre></div>`;
				if (b.type === 'template') {
					body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Router</div>';
					body += '<p class="ep-modal-text">This is a template block &mdash; the router can dispatch requests to it based on the resolved route.</p></div>';
				}
				openGenericModal(b.title, b.name, body);
			});
		});
	}

	/* ── Dependencies tab ───────────────────────────────────────────── */

	const tblDeps = document.getElementById('tbl-dependencies');
	if (tblDeps) {
		if (!dependencies || !dependencies.length) {
			tblDeps.innerHTML = '<p class="ep-notif-empty">No dependencies declared in examplepress.json.</p>';
			log.warn('[ExamplePress] No dependencies declared');
		} else {
			let html = '<div class="ep-row ep-row-head ep-cols-4"><div class="ep-th">Dependency</div><div class="ep-th">Tier</div><div class="ep-th">Status</div><div class="ep-th">Source</div></div>';
			dependencies.forEach(p => {
				const statusMap = {
					active:    { cls: 'badge-on',   lbl: 'Active' },
					installed: { cls: 'badge-warn', lbl: 'Installed' },
					fallback:  { cls: 'badge-info', lbl: 'Free Alt.' },
					missing:   { cls: 'badge-err',  lbl: 'Missing' },
				};
				const st = statusMap[p.status] || statusMap.missing;
				const tierMap = { required: 'tier-req', recommended: 'tier-rec', optional: 'tier-opt' };
				const tierCls = tierMap[p.tier] || 'tier-opt';

				let badges = '';
				if (p.pricing === 'paid') badges += '<span class="ep-meta-badge meta-paid">Paid</span>';
				if (p.cloud) badges += '<span class="ep-meta-badge meta-cloud">Cloud</span>';
				if (p.source === 'private') badges += '<span class="ep-meta-badge meta-private">Private</span>';
				if (p.checkType && p.checkType !== 'plugin') badges += `<span class="ep-meta-badge meta-check">${esc(p.checkType)}</span>`;

				let fallbackNote = '';
				if (p.fallback) {
					const fbSt = p.fallback.status === 'active' ? 'active' : p.fallback.status === 'installed' ? 'installed' : 'not installed';
					fallbackNote = `<span class="ep-desc-small">Free alt: ${esc(p.fallback.slug)} (${fbSt})</span>`;
				}

				let sourceLink = '';
				if (p.url) {
					let domain = '';
					try { domain = new URL(p.url).hostname; } catch (e) { domain = p.url; }
					sourceLink = `<a href="${esc(p.url)}" class="ep-link" target="_blank" rel="noopener">${esc(domain)} &rarr;</a>`;
				}

				html += `<div class="ep-row ep-cols-4 ep-row-clickable" data-dep-slug="${esc(p.slug)}">
					<div class="ep-td-label"><span class="ep-name">${esc(p.name)}${badges}</span><span class="ep-id">${esc(p.slug)}</span>${fallbackNote}</div>
					<div><span class="ep-tier ${tierCls}">${esc(p.tier)}</span></div>
					<div><span class="ep-badge ${st.cls}"><span class="ep-dot"></span>${st.lbl}</span></div>
					<div>${sourceLink}</div>
				</div>`;
			});
			tblDeps.innerHTML = html;
			log.info(`[ExamplePress] Dependencies: ${dependencies.length} loaded`);

			// Dependency detail modals.
			tblDeps.querySelectorAll('.ep-row-clickable[data-dep-slug]').forEach(row => {
				row.addEventListener('click', (e) => {
					// Don't open modal if clicking a link.
					if (e.target.closest('a')) return;
					const slug = row.dataset.depSlug;
					const p = dependencies.find(d => d.slug === slug);
					if (!p) return;
					const statusMap = {
						active:    { cls: 'badge-on',   lbl: 'Active' },
						installed: { cls: 'badge-warn', lbl: 'Installed' },
						fallback:  { cls: 'badge-info', lbl: 'Free Alt.' },
						missing:   { cls: 'badge-err',  lbl: 'Missing' },
					};
					const st = statusMap[p.status] || statusMap.missing;
					const tierMap = { required: 'tier-req', recommended: 'tier-rec', optional: 'tier-opt' };
					const tierCls = tierMap[p.tier] || 'tier-opt';

					let body = '<div class="ep-modal-status">';
					body += `<span class="ep-badge ${st.cls}"><span class="ep-dot"></span>${st.lbl}</span>`;
					body += `<span class="ep-tier ${tierCls}">${esc(p.tier)}</span>`;
					if (p.pricing === 'paid') body += '<span class="ep-meta-badge meta-paid">Paid</span>';
					if (p.cloud) body += '<span class="ep-meta-badge meta-cloud">Cloud</span>';
					body += '</div>';

					body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Slug</div>';
					body += `<pre class="ep-modal-code">${esc(p.slug)}</pre></div>`;

					if (p.checkType) {
						body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Detection Method</div>';
						body += `<p class="ep-modal-text">${esc(p.checkType === 'plugin' ? 'WordPress plugin registry scan' : p.checkType === 'class' ? 'class_exists() check (Composer)' : 'function_exists() check')}</p></div>`;
					}

					if (p.fallback) {
						body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Free Alternative</div>';
						body += `<p class="ep-modal-text">${esc(p.fallback.slug)} &mdash; ${esc(p.fallback.status || 'unknown')}</p></div>`;
					}

					if (p.url) {
						body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Source</div>';
						body += `<p class="ep-modal-text"><a href="${esc(p.url)}" class="ep-link" target="_blank" rel="noopener">${esc(p.url)} &rarr;</a></p></div>`;
					}

					openGenericModal(p.name, p.slug, body);
				});
			});
		}
	}

	/* ── Notifications tab ──────────────────────────────────────────── */

	function renderNotifications() {
		const active = (notifications || []).filter(n => !archived.includes(n.id));
		const archivedList = (notifications || []).filter(n => archived.includes(n.id));

		const activeEl = document.getElementById('notices-active');
		const archivedEl = document.getElementById('notices-archived');
		if (!activeEl || !archivedEl) return;

		function renderCard(n, isArchived) {
			const typeMap = { error: 'notif-error', warn: 'notif-warn', info: 'notif-info' };
			const cls = typeMap[n.type] || 'notif-info';
			const action = isArchived ? 'restore' : 'archive';
			const btnLabel = isArchived ? 'Restore' : 'Archive';
			return `<div class="ep-notif-card ${cls}">
				<div class="ep-notif-top">
					<span class="ep-notif-title">${esc(n.title)}</span>
					<button class="ep-notif-action" data-id="${esc(n.id)}" data-action="${action}">${btnLabel}</button>
				</div>
				<p class="ep-notif-msg">${esc(n.message)}</p>
			</div>`;
		}

		activeEl.innerHTML = active.length
			? active.map(n => renderCard(n, false)).join('')
			: '<p class="ep-notif-empty">No active notifications.</p>';

		archivedEl.innerHTML = archivedList.length
			? archivedList.map(n => renderCard(n, true)).join('')
			: '<p class="ep-notif-empty">No archived notifications.</p>';

		// Bind archive/restore buttons.
		document.querySelectorAll('.ep-notif-action').forEach(btn => {
			btn.addEventListener('click', async () => {
				const id = btn.dataset.id;
				const action = btn.dataset.action;

				if (action === 'archive' && !archived.includes(id)) {
					archived.push(id);
					log.info(`[ExamplePress] Notification archived: ${id}`);
				} else if (action === 'restore') {
					archived = archived.filter(i => i !== id);
					log.info(`[ExamplePress] Notification restored: ${id}`);
				}
				renderNotifications();
				updateTabCounts();

				await fetch(window.ExamplePressData.restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.ExamplePressData.nonce,
					},
					body: JSON.stringify({ id, action }),
				});
			});
		});
	}

	// Subtab switching within Notifications.
	document.querySelectorAll('.ep-notif-subtab').forEach(btn => {
		btn.addEventListener('click', () => {
			document.querySelectorAll('.ep-notif-subtab').forEach(b => b.classList.remove('active'));
			btn.classList.add('active');
			const target = btn.dataset.target;
			document.getElementById('notices-active').style.display = target === 'notices-active' ? '' : 'none';
			document.getElementById('notices-archived').style.display = target === 'notices-archived' ? '' : 'none';
		});
	});

	renderNotifications();

	/* ── Config tab ─────────────────────────────────────────────────── */

	const configSwitcher = document.getElementById('config-switcher');
	const configViewer = document.getElementById('config-viewer');
	if (configSwitcher && configViewer && configFiles) {
		const files = Object.keys(configFiles);
		let activeFile = files[0] || null;

		function renderConfig(name) {
			activeFile = name;
			configSwitcher.querySelectorAll('.ep-config-tab').forEach(t =>
				t.classList.toggle('active', t.dataset.file === name)
			);
			configViewer.innerHTML = `<div class="ep-json-block">
				<div class="ep-json-header"><span class="ep-json-filename">${esc(name)}</span><span class="ep-json-badge">read-only</span></div>
				<pre class="ep-json-body">${highlightJson(configFiles[name])}</pre>
			</div>`;
			setUrlParams('config', name);
		}

		files.forEach(f => {
			const btn = document.createElement('button');
			btn.className = 'ep-config-tab';
			btn.textContent = f;
			btn.dataset.file = f;
			btn.addEventListener('click', () => renderConfig(f));
			configSwitcher.appendChild(btn);
		});

		// Restore section from URL.
		const initSection = urlParams.tab === 'config' && urlParams.section;
		if (initSection && files.includes(initSection)) {
			renderConfig(initSection);
		} else if (activeFile) {
			renderConfig(activeFile);
		}
	}

	/* ── Health tab ─────────────────────────────────────────────────── */

	if (healthChecks) {
		const all = [
			...(healthChecks.env || []),
			...(healthChecks.theme || []),
			...(healthChecks.router || []),
			...(healthChecks.security || []),
		];
		const pass = all.filter(h => h.status === 'pass').length;
		const warn = all.filter(h => h.status === 'warn').length;
		const fail = all.filter(h => h.status === 'fail').length;
		const info = all.filter(h => h.status === 'info').length;

		const summary = document.getElementById('health-summary');
		if (summary) {
			summary.innerHTML = `
				<div class="ep-health-stat"><div class="ep-health-num val-ok">${pass}</div><div class="ep-health-lbl">Passing</div></div>
				<div class="ep-health-stat"><div class="ep-health-num" style="color:var(--amber)">${warn}</div><div class="ep-health-lbl">Warnings</div></div>
				<div class="ep-health-stat"><div class="ep-health-num" style="color:var(--red)">${fail}</div><div class="ep-health-lbl">Failing</div></div>
				<div class="ep-health-stat"><div class="ep-health-num" style="color:var(--blue)">${info}</div><div class="ep-health-lbl">Info</div></div>`;
		}

		healthTable('tbl-health-env', healthChecks.env);
		healthTable('tbl-health-theme', healthChecks.theme);
		healthTable('tbl-health-router', healthChecks.router);
		healthTable('tbl-health-security', healthChecks.security);
		log.info(`[ExamplePress] Health: ${pass} pass, ${warn} warn, ${fail} fail, ${info} info`);
	}

	/* ── Docs tab ───────────────────────────────────────────────────── */

	const docsCards = document.getElementById('docs-cards');
	if (docsCards && docs.length) {
		docsCards.innerHTML = docs.map(d => `
			<a class="ep-doc-card" href="${esc(d.link)}" target="_blank" rel="noopener">
				<div class="ep-doc-card-eyebrow">${esc(d.eyebrow)}</div>
				<div class="ep-doc-card-title">${esc(d.title)}</div>
				<div class="ep-doc-card-desc">${esc(d.desc)}</div>
				<div class="ep-doc-card-link">${esc(d.label)} &rarr;</div>
			</a>`).join('');
	}

	const hooksList = document.getElementById('hooks-list');
	if (hooksList && hooks.length) {
		hooksList.innerHTML = hooks.map(h => `
			<div class="ep-hook-item ep-hook-item-clickable" data-hook-name="${esc(h.name)}">
				<span class="ep-hook-type ${h.type === 'filter' ? 'hook-filter' : 'hook-action'}">${esc(h.type)}</span>
				<span class="ep-hook-name">${esc(h.name)}</span>
				<span class="ep-hook-desc">${esc(h.desc)}</span>
			</div>`).join('');

		// Hook detail modals.
		hooksList.querySelectorAll('.ep-hook-item-clickable').forEach(item => {
			item.addEventListener('click', () => {
				const name = item.dataset.hookName;
				const h = hooks.find(hk => hk.name === name);
				if (!h) return;

				const typeCls = h.type === 'filter' ? 'hook-filter' : 'hook-action';
				let body = '<div class="ep-modal-status">';
				body += `<span class="ep-hook-type ${typeCls}">${esc(h.type)}</span>`;
				body += '</div>';

				body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Description</div>';
				body += `<p class="ep-modal-text">${esc(h.desc)}</p></div>`;

				body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Hook Name</div>';
				body += `<pre class="ep-modal-code">${esc(h.name)}</pre></div>`;

				// Usage example.
				let usage = '';
				if (h.type === 'filter') {
					const paramName = h.name.includes('{id}') ? '$value' : '$value';
					usage = `add_filter( '${h.name}', function ( ${paramName} ) {\n    // Your modification here.\n    return ${paramName};\n} );`;
				} else {
					usage = `add_action( '${h.name}', function () {\n    // Your code here.\n} );`;
				}
				body += '<div class="ep-modal-section"><div class="ep-modal-section-title">Usage Example</div>';
				body += `<pre class="ep-modal-code">${esc(usage)}</pre></div>`;

				openGenericModal(h.name, h.type, body);
			});
		});
	}

	/* ── Navigation tab ────────────────────────────────────────────── */

	if (navigation) {
		const tblLocs = document.getElementById('tbl-nav-locations');
		if (tblLocs) {
			if (!navigation.locations || !navigation.locations.length) {
				tblLocs.innerHTML = '<p class="ep-notif-empty">No navigation locations registered. Register locations in your companion plugin via register_nav_menus().</p>';
			} else {
				let html = '<div class="ep-row ep-row-head ep-cols-nav"><div class="ep-th">Location</div><div class="ep-th">Slug</div><div class="ep-th">Status</div></div>';
				navigation.locations.forEach(loc => {
					const assigned = loc.assigned
						? `<span class="ep-badge badge-on"><span class="ep-dot"></span>Assigned</span>`
						: `<span class="ep-badge badge-off"><span class="ep-dot"></span>Empty</span>`;
					html += `<div class="ep-row ep-cols-nav">
						<div class="ep-td-label"><span class="ep-name">${esc(loc.name)}</span></div>
						<div><span class="ep-id">${esc(loc.slug)}</span></div>
						<div>${assigned}</div>
					</div>`;
				});
				tblLocs.innerHTML = html;
			}
		}

		const tblMenus = document.getElementById('tbl-nav-menus');
		if (tblMenus) {
			if (!navigation.menus || !navigation.menus.length) {
				tblMenus.innerHTML = '<p class="ep-notif-empty">No menus created yet. Create menus via Appearance &rarr; Menus.</p>';
			} else {
				let html = '<div class="ep-row ep-row-head ep-cols-nav"><div class="ep-th">Menu</div><div class="ep-th">Items</div><div class="ep-th">Locations</div></div>';
				navigation.menus.forEach(menu => {
					const locTags = menu.locations.length
						? menu.locations.map(l => `<span class="ep-src">${esc(l)}</span>`).join(' ')
						: '<span class="ep-id">Unassigned</span>';
					html += `<div class="ep-row ep-cols-nav">
						<div class="ep-td-label"><span class="ep-name">${esc(menu.name)}</span><span class="ep-id">${esc(menu.slug)}</span></div>
						<div><span class="ep-badge badge-info"><span class="ep-dot"></span>${menu.count}</span></div>
						<div>${locTags}</div>
					</div>`;
				});
				tblMenus.innerHTML = html;
			}
		}
		log.info(`[ExamplePress] Navigation: ${navigation.menus.length} menus, ${navigation.locations.length} locations`);
	}

	/* ── Demo Companion Plugin ─────────────────────────────────────── */

	const demoPanel = document.getElementById('ep-demo-panel');
	if (demoPanel && window.ExamplePressData.demo) {
		let demoStatus = window.ExamplePressData.demo.status || 'not-installed';

		const demoBadge = document.getElementById('ep-demo-badge');
		const demoMessage = document.getElementById('ep-demo-message');
		const demoActions = document.getElementById('ep-demo-actions');

		function renderDemo() {
			// Badge.
			const badgeMap = {
				'not-installed': { cls: 'badge-off', lbl: 'Not Installed' },
				'installed':     { cls: 'badge-warn', lbl: 'Installed' },
				'active':        { cls: 'badge-on', lbl: 'Active' },
				'foreign':       { cls: 'badge-err', lbl: 'Conflict' },
			};
			const b = badgeMap[demoStatus] || badgeMap['not-installed'];
			demoBadge.className = `ep-badge ${b.cls}`;
			demoBadge.innerHTML = `<span class="ep-dot"></span>${b.lbl}`;

			// Message.
			const messages = {
				'not-installed': 'The demo companion plugin is not installed. Click Install to copy it from the theme and activate it.',
				'installed':     'The demo plugin is installed but not active.',
				'active':        'The demo companion plugin is running. Visit the frontend to see the routing contract in action.',
				'foreign':       'A plugin named examplepress-demo exists but is not the ExamplePress demo. Remove it manually before installing.',
			};
			demoMessage.textContent = messages[demoStatus] || '';

			// Actions.
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
						const res = await fetch(window.ExamplePressData.demoInstallUrl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.ExamplePressData.nonce },
						});
						const data = await res.json();
						if (res.ok && data.success) {
							demoStatus = data.status;
							log.info(`[ExamplePress] Demo install success: ${data.status}`);
							renderDemo();
						} else {
							const msg = data.message || data.data?.message || 'Install failed.';
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
						const res = await fetch(window.ExamplePressData.demoUninstallUrl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.ExamplePressData.nonce },
						});
						const data = await res.json();
						if (res.ok && data.success) {
							demoStatus = data.status;
							log.info(`[ExamplePress] Demo uninstall success`);
							renderDemo();
						} else {
							const msg = data.message || data.data?.message || 'Uninstall failed.';
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

		renderDemo();
	}

	/* ── Build tab ──────────────────────────────────────────────────── */

	const buildForm = document.getElementById('ep-build-form-wrap');
	const buildSuccess = document.getElementById('ep-build-success');

	if (buildForm) {
		const appNameInput = document.getElementById('ep-app-name');
		const appSlugInput = document.getElementById('ep-app-slug');
		const troySelect = document.getElementById('ep-troy-server');
		const customUrlWrap = document.getElementById('ep-custom-url-wrap');
		const stagingToggle = document.getElementById('ep-staging-toggle');
		const stagingFields = document.getElementById('ep-staging-fields');
		const submitBtn = document.getElementById('ep-build-submit');
		const submitLabel = submitBtn.querySelector('.ep-build-submit-label');
		const submitSpinner = submitBtn.querySelector('.ep-build-spinner');
		const errorEl = document.getElementById('ep-build-error');

		// Auto-slugify App Name → App Slug.
		let slugManuallyEdited = false;
		appSlugInput.addEventListener('input', () => { slugManuallyEdited = true; });
		appNameInput.addEventListener('input', () => {
			if (!slugManuallyEdited) {
				appSlugInput.value = appNameInput.value
					.toLowerCase()
					.replace(/[^a-z0-9\s-]/g, '')
					.replace(/\s+/g, '-')
					.replace(/-+/g, '-')
					.replace(/^-|-$/g, '');
			}
			// Auto-fill SFTP remote path.
			const sftpPath = document.getElementById('ep-sftp-path');
			if (sftpPath && !sftpPath.value) {
				sftpPath.placeholder = '/wp-content/plugins/' + (appSlugInput.value || 'your-app-slug');
			}
		});

		// Troy server toggle.
		troySelect.addEventListener('change', () => {
			customUrlWrap.style.display = troySelect.value === 'custom' ? '' : 'none';
		});

		// Staging toggle.
		stagingToggle.addEventListener('change', () => {
			stagingFields.style.display = stagingToggle.checked ? '' : 'none';
		});

		function showBuildError(msg) {
			errorEl.textContent = msg;
			errorEl.style.display = '';
		}

		function hideBuildError() {
			errorEl.style.display = 'none';
			errorEl.textContent = '';
		}

		function setBuildLoading(loading) {
			submitBtn.disabled = loading;
			submitLabel.textContent = loading ? 'Provisioning Repository...' : 'Scaffold & Create Repo';
			submitSpinner.style.display = loading ? '' : 'none';
		}

		// Submit handler.
		submitBtn.addEventListener('click', async () => {
			hideBuildError();

			const githubToken = document.getElementById('ep-github-token').value.trim();
			const appName = appNameInput.value.trim();
			const appSlug = appSlugInput.value.trim();
			const troyServer = troySelect.value;
			const customUrl = document.getElementById('ep-custom-url').value.trim();

			// Client-side validation.
			if (!githubToken) { showBuildError('A GitHub Personal Access Token is required.'); return; }
			if (!appName) { showBuildError('App Name is required.'); return; }
			if (!appSlug) { showBuildError('App Slug is required.'); return; }
			if (troyServer === 'custom' && !customUrl) { showBuildError('A custom server URL is required.'); return; }

			const payload = {
				githubToken,
				appName,
				appSlug,
				troyServer,
				customServerUrl: customUrl,
				stagingEnabled: stagingToggle.checked,
			};

			if (stagingToggle.checked) {
				payload.sftpHost = document.getElementById('ep-sftp-host').value.trim();
				payload.sftpPort = parseInt(document.getElementById('ep-sftp-port').value, 10) || 22;
				payload.sftpUser = document.getElementById('ep-sftp-user').value.trim();
				payload.sftpPass = document.getElementById('ep-sftp-pass').value;
				payload.sftpPath = document.getElementById('ep-sftp-path').value.trim();

				if (!payload.sftpHost) { showBuildError('SFTP Host is required when staging is enabled.'); return; }
			}

			setBuildLoading(true);
			log.info(`[ExamplePress] Build started: ${appName} (${appSlug})`);

			try {
				const res = await fetch(window.ExamplePressData.buildUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.ExamplePressData.nonce,
					},
					body: JSON.stringify(payload),
				});

				const data = await res.json();

				if (!res.ok) {
					const errMsg = data.message || data.data?.message || 'An unknown error occurred.';
					showBuildError(errMsg);
					setBuildLoading(false);
					log.error(`[ExamplePress] Build error: ${errMsg}`);
					return;
				}

				// Show success card.
				buildForm.style.display = 'none';
				buildSuccess.style.display = '';
				log.info(`[ExamplePress] Build success: ${data.repoUrl || appSlug}`);

				const successMsg = document.getElementById('ep-build-success-msg');
				successMsg.textContent = data.message || 'Your companion plugin repository has been created.';

				const codespacesLink = document.getElementById('ep-build-codespaces-link');
				const repoLink = document.getElementById('ep-build-repo-link');

				if (data.codespacesUrl) {
					codespacesLink.href = data.codespacesUrl;
					codespacesLink.style.display = '';
				} else if (data.repoUrl) {
					codespacesLink.href = data.repoUrl.replace('github.com/', 'github.com/codespaces/new?repo=');
					codespacesLink.style.display = '';
				} else {
					codespacesLink.style.display = 'none';
				}

				if (data.repoUrl) {
					repoLink.href = data.repoUrl;
					repoLink.style.display = '';
				} else {
					repoLink.style.display = 'none';
				}
			} catch (err) {
				showBuildError('Network error: ' + err.message);
				log.error(`[ExamplePress] Build network error: ${err.message}`);
			}

			setBuildLoading(false);
		});

		// Reset button — create another app.
		const resetBtn = document.getElementById('ep-build-reset');
		if (resetBtn) {
			resetBtn.addEventListener('click', () => {
				buildSuccess.style.display = 'none';
				buildForm.style.display = '';
				hideBuildError();
				slugManuallyEdited = false;
			});
		}
	}

	/* ── Tab counts ─────────────────────────────────────────────────── */

	function updateTabCounts() {
		const activeNotifCount = (notifications || []).filter(n => !archived.includes(n.id)).length;
		const featureCount = Object.values(features).reduce((sum, arr) => sum + (arr ? arr.length : 0), 0);
		const navMenuCount = (navigation && navigation.menus) ? navigation.menus.length : 0;
		const counts = {
			't-features': featureCount,
			't-blocks': blocks ? blocks.length : 0,
			't-dependencies': dependencies ? dependencies.length : 0,
			't-notifications': activeNotifCount,
			't-navigation': navMenuCount,
		};
		Object.entries(counts).forEach(([tabId, count]) => {
			const tab = document.getElementById(tabId);
			if (!tab) return;
			let countEl = tab.querySelector('.ep-tab-count');
			if (!countEl) {
				countEl = document.createElement('span');
				countEl.className = 'ep-tab-count';
				tab.appendChild(countEl);
			}
			countEl.textContent = count;
			// Hide count badge when zero for notifications.
			if (tabId === 't-notifications') {
				countEl.style.display = count > 0 ? '' : 'none';
			}
		});
	}

	updateTabCounts();

	log.info('[ExamplePress] Settings page ready.');
});
